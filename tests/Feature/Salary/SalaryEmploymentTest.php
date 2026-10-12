<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\RecurringEntry;
use App\Models\SalaryEmployment;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Categories\CategoryFoundationService;
use App\Services\Recurring\AllocateOccurrencePaymentService;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\UpdateRecurringOccurrenceAmountService;
use App\Services\Salary\SalaryEmploymentRevisionService;
use App\Services\Salary\SalaryEmploymentService;
use App\Services\Salary\SalaryOccurrenceService;
use App\Services\Salary\SalaryStatisticsService;
use App\Services\Transactions\RecurringEntryLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
});

function salaryContext(): array
{
    $user = User::factory()->create();
    foreach ([2025, 2026, 2027] as $year) {
        UserYear::create(['user_id' => $user->id, 'year' => $year, 'is_closed' => false]);
    }
    $type = AccountType::firstOrCreate(['code' => 'payment_account'], ['name' => 'Conto', 'balance_nature' => 'asset']);
    $account = Account::create(['user_id' => $user->id, 'account_type_id' => $type->id, 'name' => 'Salary account', 'currency' => 'EUR', 'currency_code' => 'EUR', 'opening_balance' => 1000, 'current_balance' => 1000, 'is_manual' => true, 'is_active' => true]);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Salary', 'slug' => 'salary', 'direction_type' => 'income', 'group_type' => 'income', 'is_active' => true, 'is_selectable' => true]);

    return compact('user', 'account', 'category');
}

function salaryData(array $context, array $overrides = []): array
{
    return array_replace_recursive([
        'title' => 'Ordinary salary', 'account_id' => $context['account']->id, 'category_id' => $context['category']->id,
        'direction' => 'income', 'entry_type' => 'recurring', 'currency' => 'EUR', 'status' => 'active', 'is_active' => true,
        'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 1],
        'start_date' => '2026-01-01', 'expected_amount' => 2000, 'auto_create_transaction' => false, 'auto_generate_occurrences' => true,
        'reminder_days_before' => [], 'salary' => ['name' => 'Employer A', 'kind' => 'ordinary', 'additional_schedules' => []],
    ], $overrides);
}

function createSalary(array $context, array $overrides = []): RecurringEntry
{
    return app(SalaryEmploymentService::class)->create($context['user'], salaryData($context, $overrides));
}

test('multiple employments and schedules stay separate and ordinary salary creates twelve monthly snapshots', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $second = createSalary($c, ['salary' => ['name' => 'Employer B']]);
    expect(SalaryEmployment::count())->toBe(2)->and($entry->salarySchedule->employment->id)->not->toBe($second->salarySchedule->employment->id);
    expect($entry->occurrences()->count())->toBe(12)->and($entry->salarySchedule->details()->count())->toBe(12)->and(Transaction::count())->toBe(0);
    $same = createSalary($c, ['salary' => ['employment_uuid' => $entry->salarySchedule->employment->uuid, 'kind' => 'other']]);
    expect($same->salarySchedule->salary_employment_id)->toBe($entry->salarySchedule->salary_employment_id);
});

test('generic additional payments reuse the existing recurrence engine', function (array $months) {
    $c = salaryContext();
    $additional = array_map(fn ($month) => ['label' => 'Additional '.$month, 'amount' => 1500, 'start_date' => sprintf('2026-%02d-01', $month), 'recurrence_type' => 'yearly', 'recurrence_rule' => ['mode' => 'month_day', 'month' => $month, 'day' => 1]], $months);
    $entry = createSalary($c, ['salary' => ['additional_schedules' => $additional]]);
    expect($entry->salarySchedule->employment->schedules()->count())->toBe(1 + count($months));
    foreach ($entry->salarySchedule->employment->schedules()->where('kind', 'additional')->get() as $schedule) {
        expect($schedule->recurringEntry->recurrence_type->value)->toBe('yearly')->and($schedule->details()->first()->pay_period_start->month)->toBeIn($months);
    }
})->with([[[]], [[12]], [[12, 6]], [[2, 5, 9]]]);

test('signed components form one authoritative total and one idempotent transaction', function (array $components, float $expected, bool $automatic) {
    $c = salaryContext();
    $entry = createSalary($c, ['auto_create_transaction' => false]);
    $occurrence = $entry->occurrences()->whereDate('expected_date', '2026-06-01')->firstOrFail();
    expect($occurrence->amount_confirmed_at)->toBeNull()->and(Transaction::count())->toBe(0);
    app(SalaryOccurrenceService::class)->update($occurrence->salaryDetail, $c['user'], ['base_expected_amount' => 2000, 'pay_period_start' => '2026-06-01', 'pay_period_end' => '2026-06-30', 'components' => $components]);
    expect((float) $occurrence->fresh()->expected_amount)->toBe($expected)->and($occurrence->fresh()->amount_confirmed_at)->not->toBeNull();
    if ($automatic) {
        $entry->occurrences()->whereKeyNot($occurrence->id)->whereDate('expected_date', '<=', today())->update(['automatic_posting_blocked_at' => now()]);
        $entry->update(['auto_create_transaction' => true]);
        app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    } else {
        app(RecurringEntryPostingService::class)->post($occurrence, $c['user']);
    }
    $transaction = $occurrence->fresh()->convertedTransaction;
    app(RecurringEntryPostingService::class)->post($occurrence);
    expect(Transaction::count())->toBe(1)->and((float) $transaction->amount)->toBe($expected)->and((float) $c['account']->fresh()->current_balance)->toBe(1000 + $expected);
    expect((float) $occurrence->salaryDetail->fresh()->base_expected_amount)->toBe(2000.0);
    $this->actingAs($c['user'])->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (Assert $page) => $page->where('recurringEntry.occurrences.5.can_update_amount', true));
})->with([
    [[['label' => 'Bonus', 'amount' => 100]], 2100.0, false],
    [[['label' => 'Deduction', 'amount' => -50]], 1950.0, false],
    [[['label' => 'Bonus', 'amount' => 100], ['label' => 'Deduction', 'amount' => -50]], 2050.0, true],
]);

test('future base changes preserve past snapshots and explicit monthly overrides', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $past = $entry->occurrences()->whereDate('expected_date', '2026-06-01')->firstOrFail();
    $future = $entry->occurrences()->whereDate('expected_date', '2026-07-01')->firstOrFail();
    app(UpdateRecurringOccurrenceAmountService::class)->update($future, $c['user'], 2300);
    app(RecurringEntryManagementService::class)->update($c['user'], $entry, ['expected_amount' => 2200]);
    expect((float) $past->salaryDetail->fresh()->base_expected_amount)->toBe(2000.0)
        ->and((float) $future->salaryDetail->fresh()->base_expected_amount)->toBe(2300.0)
        ->and((float) $entry->occurrences()->whereDate('expected_date', '2026-08-01')->first()->salaryDetail->base_expected_amount)->toBe(2200.0);
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry->fresh());
    expect($entry->occurrences()->count())->toBe(12)->and((float) $past->salaryDetail->fresh()->base_expected_amount)->toBe(2000.0);
});

test('ending employment preserves posted and overdue occurrences and stops future generation and posting', function () {
    $c = salaryContext();
    $entry = createSalary($c, ['auto_create_transaction' => false]);
    $past = $entry->occurrences()->whereDate('expected_date', '2026-01-01')->firstOrFail();
    $arrears = $entry->occurrences()->whereDate('expected_date', '2026-02-01')->firstOrFail();
    $arrears->forceFill(['automatic_posting_blocked_at' => now()])->save();
    app(UpdateRecurringOccurrenceAmountService::class)->update($past, $c['user'], 2000);
    $transaction = app(RecurringEntryPostingService::class)->post($past);
    $entry->occurrences()->whereNull('converted_transaction_id')->whereDate('expected_date', '<=', '2026-06-30')->update(['automatic_posting_blocked_at' => now()]);
    $existingCount = $entry->occurrences()->count();
    $entry->update(['auto_create_transaction' => true]);
    app(SalaryEmploymentService::class)->end($entry->salarySchedule->employment, '2026-06-30');
    expect($past->fresh()->converted_transaction_id)->toBe($transaction->id)->and($arrears->fresh()->status->value)->toBe('pending');
    $this->travelTo(CarbonImmutable::parse('2027-03-01'));
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect($entry->occurrences()->whereDate('expected_date', '>', '2026-06-30')->whereNotIn('status', ['cancelled'])->count())->toBe(0);
    expect(Transaction::count())->toBe(1)->and($entry->occurrences()->count())->toBe($existingCount);
    $arrears->refresh()->forceFill(['due_date' => '2027-03-01', 'automatic_posting_blocked_at' => null])->save();
    app(UpdateRecurringOccurrenceAmountService::class)->update($arrears, $c['user'], 2000);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(2);
});

test('salary endpoints enforce ownership and closed year rules without partial component saves', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $employment = $entry->salarySchedule->employment;
    $detail = $entry->occurrences()->first()->salaryDetail;
    $other = User::factory()->create();
    $this->actingAs($other)->get(route('salary-employments.show', $employment->uuid))->assertNotFound();
    $this->actingAs($other)->patch(route('salary-employments.end', $employment->uuid), ['end_date' => '2026-12-31'])->assertNotFound();
    $data = ['base_expected_amount' => 2000, 'pay_period_start' => '2026-01-01', 'pay_period_end' => '2026-01-31', 'components' => [['label' => 'Bonus', 'amount' => 100]]];
    $this->actingAs($other)->patch(route('salary-employments.occurrences.update', [$employment->uuid, $detail->id]), $data)->assertNotFound();
    UserYear::where('user_id', $c['user']->id)->where('year', 2026)->update(['is_closed' => true]);
    $this->actingAs($c['user'])->patch(route('salary-employments.occurrences.update', [$employment->uuid, $detail->id]), $data)->assertSessionHasErrors('amount');
    expect($detail->components()->count())->toBe(0)->and((float) $detail->occurrence->fresh()->expected_amount)->toBe(2000.0);
});

test('year on year comparison is isolated by employment and missing periods are unavailable', function () {
    $c = salaryContext();
    $entry = createSalary($c, ['start_date' => '2025-06-01']);
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry, CarbonImmutable::parse('2026-06-30'));
    $employment = $entry->salarySchedule->employment;
    createSalary($c, ['expected_amount' => 9000, 'salary' => ['name' => 'Second job']]);
    $current = $entry->occurrences()->whereDate('expected_date', '2026-06-01')->firstOrFail();
    app(SalaryOccurrenceService::class)->update($current->salaryDetail, $c['user'], ['base_expected_amount' => 2200, 'pay_period_start' => '2026-06-01', 'pay_period_end' => '2026-06-30', 'components' => [['label' => 'Bonus', 'amount' => 100]]]);
    app(RecurringEntryPostingService::class)->post($current);
    $stats = app(SalaryStatisticsService::class)->comparison($employment, '2026-06');
    expect($stats['base_percentage'])->toBe(10.0)->and($stats['current']['base'])->toBe(2200.0)->and($stats['current']['components'])->toBe(100.0)->and($stats['current']['actual'])->toBe(2300.0)->and($stats['previous']['actual'])->toBeNull();
    expect(app(SalaryStatisticsService::class)->comparison($employment, '2025-01')['previous'])->toBeNull();
    $this->actingAs($c['user'])->get(route('salary-employments.show', ['salaryEmployment' => $employment->uuid, 'period' => '2026-06']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('transactions/salary/Show')->where('employment.name', 'Employer A')->has('details.data', 1)->where('details.data.0.pay_period_start', '2026-06-01'));
});

test('salary creation through the recurring endpoint validates and links the domain', function () {
    $c = salaryContext();
    $this->actingAs($c['user'])->post(route('recurring-entries.store'), salaryData($c))->assertSessionHasNoErrors()->assertRedirect();
    expect(SalaryEmployment::count())->toBe(1);
    $this->actingAs($c['user'])->post(route('recurring-entries.store'), salaryData($c, ['direction' => 'expense']))->assertSessionHasErrors('salary');
});

test('posted salary composition is immutable and posting respects closed years', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $occurrence = $entry->occurrences()->firstOrFail();
    UserYear::where('user_id', $c['user']->id)->where('year', 2026)->update(['is_closed' => true]);
    expect(fn () => app(RecurringEntryPostingService::class)->post($occurrence))->toThrow(ValidationException::class);
    expect(Transaction::count())->toBe(0);
    UserYear::where('user_id', $c['user']->id)->where('year', 2026)->update(['is_closed' => false]);
    app(RecurringEntryPostingService::class)->post($occurrence);
    app(UpdateRecurringOccurrenceAmountService::class)->update($occurrence, $c['user'], 2400);
    expect((float) $occurrence->fresh()->convertedTransaction->amount)->toBe(2400.0);
    expect((float) $occurrence->salaryDetail->fresh()->base_expected_amount)->toBe(2000.0);
    $this->actingAs($c['user'])->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (Assert $page) => $page->where('recurringEntry.occurrences.0.can_update_amount', true));
});

test('negative total rolls back every component and an existing component can be edited and removed', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $detail = $entry->occurrences()->first()->salaryDetail;
    $data = ['base_expected_amount' => 2000, 'pay_period_start' => '2026-01-01', 'pay_period_end' => '2026-01-31', 'components' => [['label' => 'Bonus', 'amount' => 100]]];
    $service = app(SalaryOccurrenceService::class);
    $service->update($detail, $c['user'], $data);
    $data['components'][0]['amount'] = -5000;
    expect(fn () => $service->update($detail, $c['user'], $data))->toThrow(ValidationException::class);
    expect((float) $detail->components()->first()->amount)->toBe(100.0);
    $data['components'][0]['amount'] = -50;
    $service->update($detail, $c['user'], $data);
    expect((float) $detail->occurrence->fresh()->expected_amount)->toBe(1950.0);
    $data['components'] = [];
    $service->update($detail, $c['user'], $data);
    expect($detail->components()->count())->toBe(0)->and((float) $detail->occurrence->fresh()->expected_amount)->toBe(2000.0);
});

test('existing employment cannot be attached across users and invalid additional schedule rolls back the whole employment', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $other = salaryContext();
    $this->actingAs($other['user'])->post(route('recurring-entries.store'), salaryData($other, ['salary' => ['employment_uuid' => $entry->salarySchedule->employment->uuid]]))->assertSessionHasErrors('salary.employment_uuid');
    $data = salaryData($c, ['salary' => ['additional_schedules' => [['label' => 'Bad', 'amount' => 100, 'start_date' => '2026-12-01', 'recurrence_type' => 'yearly', 'account_uuid' => $other['account']->uuid]]]]);
    expect(fn () => app(SalaryEmploymentService::class)->create($c['user'], $data))->toThrow(ValidationException::class);
    expect(SalaryEmployment::count())->toBe(1);
});

test('salary category default is derived from the existing catalog and stays editable', function () {
    $c = salaryContext();
    $c['category']->update(['slug' => CategoryFoundationService::childSlugForKey('income_salary')]);
    $response = $this->actingAs($c['user'])->getJson(route('recurring-entries.index'))->assertOk();
    $categories = $response->json('formOptions.categories.'.$c['account']->uuid);
    expect(collect($categories)->firstWhere('value', $c['category']->uuid)['is_salary_default'])->toBeTrue();
    $entry = createSalary($c, ['salary' => ['name' => null]]);
    expect($entry->salarySchedule->employment->name)->toBe('Ordinary salary');
});

test('existing manual payment allocations count actual salary once and protect composition', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $occurrence = $entry->occurrences()->firstOrFail();
    app(UpdateRecurringOccurrenceAmountService::class)->update($occurrence, $c['user'], 2000);
    $transaction = Transaction::create(['user_id' => $c['user']->id, 'account_id' => $c['account']->id, 'direction' => 'income', 'amount' => 2000, 'currency' => 'EUR', 'transaction_date' => '2026-01-01', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
    $service = app(AllocateOccurrencePaymentService::class);
    $service->allocate($occurrence, $transaction, $c['user'], '2000');
    $service->allocate($occurrence, $transaction, $c['user'], '2000');
    $stats = app(SalaryStatisticsService::class)->comparison($entry->salarySchedule->employment, '2026-01');
    expect($stats['current']['actual'])->toBe(2000.0)->and(Transaction::count())->toBe(1)->and($occurrence->paymentAllocations()->count())->toBe(1);
    expect(fn () => app(SalaryOccurrenceService::class)->update($occurrence->salaryDetail, $c['user'], ['base_expected_amount' => 2200, 'pay_period_start' => '2026-01-01', 'pay_period_end' => '2026-01-31', 'components' => []]))->toThrow(ValidationException::class);
});

test('salary name is optional and validation uses readable translated field names', function () {
    $c = salaryContext();
    $data = salaryData($c, ['salary' => ['name' => null, 'end_date' => '2026-12-31']]);
    $this->actingAs($c['user'])->post(route('recurring-entries.store'), $data)->assertSessionHasNoErrors();
    expect(SalaryEmployment::firstOrFail()->name)->toBe('Ordinary salary')->and(SalaryEmployment::firstOrFail()->end_date)->toBeNull();
    app()->setLocale('it');
    $this->postJson(route('recurring-entries.store'), salaryData($c, ['salary' => ['name' => str_repeat('a', 151)]]))->assertUnprocessable()->assertJsonValidationErrors('salary.name');
    $response = $this->postJson(route('recurring-entries.store'), salaryData($c, ['salary' => ['name' => ['invalid']]]));
    expect($response->json('errors')['salary.name'][0])->not->toContain('salary.name')->toContain('nome dello stipendio');
});

test('all salary payments share one registration mode during creation and editing', function () {
    $c = salaryContext();
    $entry = createSalary($c, ['auto_create_transaction' => true, 'salary' => ['additional_schedules' => [['label' => 'Extra', 'amount' => 1500, 'start_date' => '2026-12-01', 'recurrence_type' => 'yearly', 'auto_create_transaction' => false]]]]);
    $employment = $entry->salarySchedule->employment;
    expect($employment->schedules()->with('recurringEntry')->get()->every(fn ($schedule) => $schedule->recurringEntry->auto_create_transaction))->toBeTrue();
    $extra = $employment->schedules()->where('kind', 'additional')->firstOrFail()->recurringEntry;
    app(RecurringEntryManagementService::class)->update($c['user'], $extra, ['auto_create_transaction' => false]);
    expect($employment->schedules()->with('recurringEntry')->get()->every(fn ($schedule) => ! $schedule->recurringEntry->auto_create_transaction))->toBeTrue();
    app(RecurringEntryManagementService::class)->update($c['user'], $entry, ['auto_create_transaction' => true]);
    expect($extra->fresh()->auto_create_transaction)->toBeTrue();
});

test('salary uses the existing tracked reference without creating a separate merchant', function () {
    $c = salaryContext();
    $reference = TrackedItem::create(['user_id' => $c['user']->id, 'account_id' => $c['account']->id, 'name' => 'Example employer', 'slug' => 'example-employer', 'is_active' => true, 'settings' => ['transaction_group_keys' => ['income']]]);
    $entry = createSalary($c, ['tracked_item_id' => $reference->id, 'salary' => ['name' => null]]);
    expect($entry->tracked_item_id)->toBe($reference->id)->and($entry->salarySchedule->employment->name)->toBe('Example employer')->and(Merchant::count())->toBe(0);
});

test('creating an automatic salary in the past immediately posts every due payment once', function () {
    $c = salaryContext();
    $entry = createSalary($c, ['auto_create_transaction' => true]);
    expect(Transaction::count())->toBe(6)->and($entry->occurrences()->whereNotNull('converted_transaction_id')->count())->toBe(6);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(Transaction::count())->toBe(6);
    $transaction = $entry->occurrences()->firstOrFail()->convertedTransaction;
    expect($transaction->payment_verification_started_at)->not->toBeNull()->and($transaction->payment_verified_at)->toBeNull();
});

function salaryRevisionData(RecurringEntry $entry, string $effective = '2026-07-01'): array
{
    return ['effective_from' => $effective, 'name' => 'Updated salary', 'automatic' => false, 'payments' => $entry->salarySchedule->employment->schedules()->whereNull('valid_to')->with('recurringEntry.account', 'recurringEntry.category', 'recurringEntry.trackedItem')->get()->map(fn ($schedule) => [
        'schedule_id' => $schedule->id, 'kind' => $schedule->kind, 'label' => $schedule->label, 'amount' => (float) $schedule->recurringEntry->expected_amount,
        'start_date' => $schedule->recurringEntry->start_date->toDateString(), 'recurrence_type' => $schedule->recurringEntry->recurrence_type->value,
        'account_uuid' => $schedule->recurringEntry->account->uuid, 'category_uuid' => $schedule->recurringEntry->category->uuid, 'reference_uuid' => $schedule->recurringEntry->trackedItem?->uuid,
    ])->all()];
}

test('salary reconciliation keeps composition and received money separate including a legacy inconsistent expected total', function () {
    $c = salaryContext();
    $entry = createSalary($c, ['expected_amount' => 1800]);
    $occurrence = $entry->occurrences()->whereDate('expected_date', '2026-06-01')->firstOrFail();
    app(SalaryOccurrenceService::class)->update($occurrence->salaryDetail, $c['user'], ['base_expected_amount' => 1800, 'pay_period_start' => '2026-06-01', 'pay_period_end' => '2026-06-30', 'components' => [['label' => 'Extra', 'amount' => 1800]]]);
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence);
    app(UpdateRecurringOccurrenceAmountService::class)->update($occurrence, $c['user'], 3500);
    expect((float) $occurrence->fresh()->expected_amount)->toBe(3600.0)->and((float) $transaction->fresh()->amount)->toBe(3500.0);
    $occurrence->update(['expected_amount' => 3500]);
    $stats = app(SalaryStatisticsService::class)->comparison($entry->salarySchedule->employment, '2026-06');
    expect($stats['current']['base'])->toBe(1800.0)->and($stats['current']['components'])->toBe(1800.0)->and($stats['current']['expected'])->toBe(3600.0)->and($stats['current']['actual'])->toBe(3500.0)->and($stats['current']['difference'])->toBe(-100.0);
    $this->actingAs($c['user'])->get(route('salary-employments.show', ['salaryEmployment' => $entry->salarySchedule->employment->uuid, 'period' => '2026-06']))->assertInertia(fn (Assert $page) => $page->where('details.data.0.total', 3600)->where('details.data.0.difference', -100));
    app(SalaryOccurrenceService::class)->explain($occurrence->salaryDetail, $c['user'], 'Withholding', 'deduction');
    expect($occurrence->salaryDetail->fresh()->expectedTotal())->toBe(3500.0)->and((float) $occurrence->salaryDetail->fresh()->base_expected_amount)->toBe(1800.0)->and((float) $transaction->fresh()->amount)->toBe(3500.0);
    expect(fn () => app(SalaryOccurrenceService::class)->explain($occurrence->salaryDetail, $c['user'], 'Duplicate'))->toThrow(ValidationException::class);
});

test('salary future revision replaces fourteen with thirteen while preserving recorded history and year comparison', function () {
    $c = salaryContext();
    $entry = createSalary($c, ['start_date' => '2025-06-01', 'salary' => ['additional_schedules' => [
        ['label' => 'Extra December', 'amount' => 1800, 'start_date' => '2025-12-01', 'recurrence_type' => 'yearly'],
        ['label' => 'Extra June', 'amount' => 1800, 'start_date' => '2025-06-01', 'recurrence_type' => 'yearly'],
    ]]]);
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry, CarbonImmutable::parse('2026-12-31'));
    $employment = $entry->salarySchedule->employment;
    $june = $employment->schedules()->where('label', 'Extra June')->firstOrFail()->recurringEntry;
    $history = $june->occurrences()->whereDate('expected_date', '2025-06-01')->firstOrFail();
    $transaction = app(RecurringEntryPostingService::class)->post($history);
    $attributes = $history->fresh()->getAttributes();
    $txAttributes = $transaction->getAttributes();
    $snapshot = $history->salaryDetail->getAttributes();
    $data = salaryRevisionData($entry);
    $data['payments'] = array_values(array_filter($data['payments'], fn ($row) => $row['label'] !== 'Extra June'));
    $data['payments'][0]['amount'] = 2200;
    app(SalaryEmploymentRevisionService::class)->update($c['user'], $employment, $data);
    expect($history->fresh()->getAttributes())->toBe($attributes)->and($transaction->fresh()->getAttributes())->toBe($txAttributes)->and($history->salaryDetail->fresh()->getAttributes())->toBe($snapshot);
    expect($employment->schedules()->whereNull('valid_to')->count())->toBe(2)->and($june->occurrences()->whereDate('expected_date', '>=', '2026-07-01')->whereNotIn('status', ['cancelled'])->count())->toBe(0);
    $ordinary = $employment->schedules()->whereNull('valid_to')->where('kind', 'ordinary')->firstOrFail();
    expect((float) $ordinary->recurringEntry->expected_amount)->toBe(2200.0)->and($ordinary->recurringEntry->start_date->toDateString())->toBe('2026-07-01');
    $stats = app(SalaryStatisticsService::class)->comparison($employment, '2026-07');
    expect($stats['base_percentage'])->toBe(10.0);
});

test('salary revision adds and moves only future additional dates and preserves earlier overrides', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $employment = $entry->salarySchedule->employment;
    $data = salaryRevisionData($entry);
    $extra = $data['payments'][0];
    $extra['schedule_id'] = null;
    $extra['kind'] = 'additional';
    $extra['label'] = 'Summer';
    $extra['start_date'] = '2026-06-01';
    $extra['recurrence_type'] = 'yearly';
    $data['payments'][] = $extra;
    app(SalaryEmploymentRevisionService::class)->update($c['user'], $employment, $data);
    $summer = $employment->schedules()->whereNull('valid_to')->where('kind', 'additional')->firstOrFail();
    expect($summer->recurringEntry->start_date->toDateString())->toBe('2027-06-01')->and($summer->recurringEntry->occurrences()->whereDate('expected_date', '<', '2026-07-01')->count())->toBe(0);
    $current = $employment->schedules()->whereNull('valid_to')->where('kind', 'ordinary')->firstOrFail()->recurringEntry;
    $data = salaryRevisionData($current, '2026-08-01');
    $data['payments'][1]['start_date'] = '2027-09-01';
    app(SalaryEmploymentRevisionService::class)->update($c['user'], $employment, $data);
    expect($employment->schedules()->whereNull('valid_to')->where('kind', 'additional')->firstOrFail()->recurringEntry->start_date->toDateString())->toBe('2027-09-01');
    $current = $employment->schedules()->whereNull('valid_to')->where('kind', 'ordinary')->firstOrFail()->recurringEntry;
    $future = $current->occurrences()->firstOrFail();
    $future->salaryDetail->update(['is_overridden' => true]);
    expect(fn () => app(SalaryEmploymentRevisionService::class)->update($c['user'], $employment, salaryRevisionData($current, '2026-09-01')))->not->toThrow(ValidationException::class);
});

test('future salary overrides and cross user changes are protected with atomic rollback', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $employment = $entry->salarySchedule->employment;
    $future = $entry->occurrences()->whereDate('expected_date', '2026-07-01')->firstOrFail();
    $future->salaryDetail->update(['is_overridden' => true]);
    $data = salaryRevisionData($entry);
    $original = $entry->getAttributes();
    expect(fn () => app(SalaryEmploymentRevisionService::class)->update($c['user'], $employment, $data))->toThrow(ValidationException::class);
    expect($entry->fresh()->getAttributes())->toBe($original)->and($employment->schedules()->count())->toBe(1)->and($future->fresh()->status->value)->toBe('pending');
    $this->actingAs(User::factory()->create())->patch(route('salary-employments.update', $employment->uuid), $data)->assertNotFound();
    $this->post(route('salary-employments.occurrences.explain', [$employment->uuid, $future->salaryDetail->id]), ['label' => 'Malicious'])->assertNotFound();
    $this->actingAs($c['user'])->patch(route('salary-employments.update', $employment->uuid), [...$data, 'effective_from' => '2026-06-01'])->assertSessionHasErrors('effective_from');
});

test('salary calendar payload only includes the chosen month and excludes cancelled versions', function () {
    $c = salaryContext();
    $entry = createSalary($c, ['salary' => ['additional_schedules' => [['label' => 'December', 'amount' => 1800, 'start_date' => '2026-12-01', 'recurrence_type' => 'yearly'], ['label' => 'June', 'amount' => 1800, 'start_date' => '2026-06-01', 'recurrence_type' => 'yearly']]]]);
    $employment = $entry->salarySchedule->employment;
    $this->actingAs($c['user'])->get(route('salary-employments.show', ['salaryEmployment' => $employment->uuid, 'period' => '2026-10']))->assertInertia(fn (Assert $page) => $page->has('calendarDays', 1)->where('calendarDays.0.date', '2026-10-01')->has('details.data', 1)->has('employment.schedules', 3));
    $this->get(route('salary-employments.show', ['salaryEmployment' => $employment->uuid, 'period' => '2026-10', 'day' => '2026-10-02']))->assertInertia(fn (Assert $page) => $page->has('details.data', 0)->has('calendarDays', 1)->where('selectedDay', '2026-10-02'));
    $this->get(route('salary-employments.show', ['salaryEmployment' => $employment->uuid, 'period' => '2026-10', 'day' => '2026-10-01']))->assertInertia(fn (Assert $page) => $page->has('details.data', 1)->where('selectedDay', '2026-10-01'));
});

test('retired salary schedules cannot post restored future occurrences or generate outside their validity', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $future = $entry->occurrences()->whereDate('expected_date', '2026-07-01')->firstOrFail();
    app(SalaryEmploymentRevisionService::class)->update($c['user'], $entry->salarySchedule->employment, salaryRevisionData($entry));
    $future->update(['status' => 'pending']);
    expect(app(SalaryOccurrenceService::class)->isBeyondEmployment($future->fresh()))->toBeTrue();
    expect(fn () => app(RecurringEntryPostingService::class)->post($future->fresh()))->toThrow(ValidationException::class);
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry->fresh(), CarbonImmutable::parse('2027-12-31'));
    expect($entry->occurrences()->whereDate('expected_date', '>', '2026-12-31')->count())->toBe(0);
});

test('salary annual received excludes refunded occurrences and calendar never sums different currencies', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $employment = $entry->salarySchedule->employment;
    $occurrence = $entry->occurrences()->whereDate('expected_date', '2026-06-01')->firstOrFail();
    app(RecurringEntryPostingService::class)->post($occurrence);
    $occurrence->update(['status' => 'refunded']);
    expect(app(SalaryStatisticsService::class)->annual($employment, 2026)['received'])->toBeNull();
    $july = $entry->occurrences()->whereDate('expected_date', '2026-07-01')->firstOrFail()->salaryDetail;
    $july->update(['pay_period_start' => '2026-05-01', 'currency' => 'USD']);
    $july->occurrence->update(['expected_date' => '2026-05-15']);
    $this->actingAs($c['user'])->get(route('salary-employments.show', ['salaryEmployment' => $employment->uuid, 'period' => '2026-05']))->assertInertia(fn (Assert $page) => $page->has('calendarDays', 2)->where('calendarDays.0.income_total', 0)->where('calendarDays.1.income_total', 0));
});

test('salary recurrence can be renamed after posting while preserving transaction and salary snapshots', function () {
    $c = salaryContext();
    $entry = createSalary($c);
    $occurrence = $entry->occurrences()->firstOrFail();
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence);
    $recorded = $transaction->getAttributes();
    $snapshot = $occurrence->salaryDetail->getAttributes();
    app(RecurringEntryManagementService::class)->update($c['user'], $entry, ['title' => 'My monthly income', 'description' => 'My monthly income']);
    expect($entry->fresh()->title)->toBe('My monthly income')->and($entry->fresh()->description)->toBe('My monthly income');
    expect($transaction->fresh()->getAttributes())->toBe($recorded)->and($occurrence->salaryDetail->fresh()->getAttributes())->toBe($snapshot);
    $reference = TrackedItem::create(['user_id' => $c['user']->id, 'account_id' => $c['account']->id, 'name' => 'New employer', 'slug' => 'new-employer', 'is_active' => true, 'settings' => ['transaction_group_keys' => ['income']]]);
    $data = salaryRevisionData($entry);
    $data['payments'][0]['reference_uuid'] = $reference->uuid;
    app(SalaryEmploymentRevisionService::class)->update($c['user'], $entry->salarySchedule->employment, $data);
    $futureEntry = $entry->salarySchedule->employment->schedules()->whereNull('valid_to')->where('kind', 'ordinary')->firstOrFail()->recurringEntry;
    expect($futureEntry->tracked_item_id)->toBe($reference->id)->and($transaction->fresh()->getAttributes())->toBe($recorded);
    $this->actingAs($c['user'])->getJson(route('recurring-entries.show', $futureEntry->uuid))->assertOk()->assertJsonPath('recurringEntry.entry.tracked_item.uuid', $reference->uuid);
});
