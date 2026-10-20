<?php

use App\Models\Category;
use App\Models\CompoundRecurringComponent;
use App\Models\CompoundRecurringEntry;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\ReminderDelivery;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\AllocateOccurrencePaymentService;
use App\Services\Recurring\CompoundRecurringEntryService;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Recurring\RecurringEntryPeriodService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\UpdateRecurringOccurrenceAmountService;
use App\Services\Reminders\DailyRecurringReminderService;
use App\Services\Transactions\RecurringEntryLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(now()->setDate(2026, 1, 10));
    $this->compoundUser = User::factory()->create();
    $this->compoundAccount = createTestAccount($this->compoundUser, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $this->compoundCategory = Category::query()->create(['user_id' => $this->compoundUser->id, 'name' => 'Imposte', 'slug' => 'compound-tax', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->compoundData = ['name' => 'IMU', 'account_uuid' => $this->compoundAccount->uuid, 'category_uuid' => $this->compoundCategory->uuid, 'direction' => 'expense', 'components' => [
        ['name' => 'Acconto', 'date' => '2026-06-16', 'amount' => '300.00'],
        ['name' => 'Saldo', 'date' => '2026-12-16', 'amount' => '350.00'],
    ]];
    $this->actingAs($this->compoundUser);
});

test('compound creation uses independent annual children without economic side effects and remains idempotent on regeneration', function () {
    $accountBefore = $this->compoundAccount->fresh()->getAttributes();
    $this->post(route('compound-recurring-entries.store'), $this->compoundData)->assertSessionHasNoErrors();
    $parent = CompoundRecurringEntry::query()->with('components.recurringEntry')->sole();
    expect($parent->components)->toHaveCount(2)->and($parent->derivedStatus())->toBe('active');
    expect(RecurringEntry::count())->toBe(2)->and(Transaction::count())->toBe(0)
        ->and($this->compoundAccount->fresh()->getAttributes())->toBe($accountBefore);
    foreach ($parent->components as $component) {
        $child = $component->recurringEntry;
        expect($child->entry_type->value)->toBe('recurring')->and($child->recurrence_type->value)->toBe('yearly')
            ->and($child->is_amount_variable)->toBeTrue()->and($child->auto_create_transaction)->toBeFalse()
            ->and($child->account_id)->toBe($parent->account_id)->and($child->currency)->toBe($parent->currency);
        $ids = $child->occurrences()->pluck('id')->all();
        app(RecurringEntryOccurrenceGeneratorService::class)->generate($child, $child->generationHorizon(CarbonImmutable::today()));
        expect($child->occurrences()->pluck('id')->all())->toBe($ids);
    }
    $this->get(route('recurring-entries.index', ['year' => 2026, 'month' => 6]))->assertInertia(fn (Assert $page) => $page
        ->has('compoundEntries', 1)->where('compoundEntries.0.name', 'IMU')
        ->has('monthlyCalendar.days.0.occurrences', 1)
        ->where('recurringEntries.0.is_compound_component', true));
    $this->get(route('recurring-entries.show', $parent->uuid))->assertInertia(fn (Assert $page) => $page
        ->component('transactions/recurring/Show')->has('compound.components', 2)->has('recurringEntry.occurrences', 2));
});

test('a failing child rolls back the parent siblings occurrences and membership', function () {
    $data = $this->compoundData;
    $data['components'][1]['amount'] = 0;
    expect(fn () => app(CompoundRecurringEntryService::class)->create($this->compoundUser, $data))->toThrow(ValidationException::class);
    expect(CompoundRecurringEntry::count())->toBe(0)->and(CompoundRecurringComponent::count())->toBe(0)
        ->and(RecurringEntry::count())->toBe(0)->and(RecurringEntryOccurrence::count())->toBe(0)->and(Transaction::count())->toBe(0);
});

test('future components preserve registered payments and allocations and cannot be deleted through existing child routes', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $child = $parent->components()->firstOrFail()->recurringEntry;
    $row = $child->occurrences()->orderBy('id')->firstOrFail();
    $this->travelTo(now()->setDate(2026, 6, 16));
    $payment = app(RecurringEntryPostingService::class)->post($row, $this->compoundUser);
    $second = $parent->components()->orderBy('id')->get()[1]->recurringEntry;
    $secondRow = $second->occurrences()->orderBy('id')->firstOrFail();
    $this->travelTo(now()->setDate(2026, 12, 16));
    app(UpdateRecurringOccurrenceAmountService::class)->update($secondRow, $this->compoundUser, 350);
    $partial = Transaction::query()->create([
        'user_id' => $this->compoundUser->id, 'account_id' => $this->compoundAccount->id,
        'category_id' => $this->compoundCategory->id, 'direction' => 'expense', 'amount' => 100,
        'currency' => 'EUR', 'transaction_date' => '2026-12-16', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed',
    ]);
    $allocation = app(AllocateOccurrencePaymentService::class)->allocate($secondRow->fresh(), $partial, $this->compoundUser, '100.00');
    $allocationBefore = $allocation->fresh()->getAttributes();
    $before = $payment->fresh()->getAttributes();
    $rowsBefore = $child->occurrences()->get()->map->getAttributes()->all();
    $this->post(route('compound-recurring-entries.components.store', $parent->uuid), ['components' => [['name' => 'Conguaglio', 'date' => '2027-01-10', 'amount' => '30.00']]])->assertSessionHasNoErrors();
    expect($payment->fresh()->getAttributes())->toBe($before)->and($child->occurrences()->get()->map->getAttributes()->all())->toBe($rowsBefore);
    $this->delete(route('recurring-entries.destroy', $child->uuid))->assertForbidden();
    $this->assertModelExists($child);
    $this->assertModelExists($payment);
    expect($allocation->fresh()->getAttributes())->toBe($allocationBefore);
    $this->assertModelExists($partial);
    $this->getJson(route('recurring-entries.show', $child->uuid))->assertJsonPath('recurringEntry.actions.can_delete', false);
    $this->get(route('recurring-entries.show', $parent->uuid))->assertInertia(fn (Assert $page) => $page->has('compound.components', 3));
});

test('compound authorization validation and absence of conversion endpoints preserve existing recurrences', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $this->actingAs(User::factory()->create());
    $this->get(route('compound-recurring-entries.show', $parent->uuid))->assertNotFound();
    $this->post(route('compound-recurring-entries.components.store', $parent->uuid), ['components' => [$this->compoundData['components'][0]]])->assertNotFound();
    $this->post(route('compound-recurring-entries.store'), $this->compoundData)->assertSessionHasErrors('account_uuid');
    $this->actingAs($this->compoundUser);
    $this->post(route('compound-recurring-entries.store'), [...$this->compoundData, 'components' => [['name' => 'Acconto', 'date' => '2026-02-31', 'amount' => '10.001']]])->assertSessionHasErrors(['components.0.date', 'components.0.amount']);
    $this->post(route('compound-recurring-entries.components.store', $parent->uuid), ['components' => [$this->compoundData['components'][0]]])->assertSessionHasErrors('components.0.name');
    expect(CompoundRecurringEntry::count())->toBe(1)->and(RecurringEntry::count())->toBe(2);
});

test('component invariants are protected and removing parent metadata leaves children intact', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $child = $parent->components()->firstOrFail()->recurringEntry;
    foreach (['currency' => 'USD', 'entry_type' => 'installment', 'recurrence_type' => 'monthly', 'is_amount_variable' => false] as $field => $value) {
        expect(fn () => app(RecurringEntryManagementService::class)->update($this->compoundUser, $child, [$field => $value]))->toThrow(ValidationException::class);
    }
    $child->update(['status' => 'paused', 'is_active' => false]);
    expect($parent->fresh()->derivedStatus())->toBe('mixed');
    $before = $child->fresh()->getAttributes();
    expect(fn () => app(RecurringEntryPeriodService::class)->reactivate($this->compoundUser, $child, ['start_date' => '2026-06-16', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'expected_amount' => 300]))->toThrow(ValidationException::class);
    expect($child->fresh()->getAttributes())->toBe($before);
    $ids = RecurringEntryOccurrence::query()->pluck('id')->all();
    $parent->delete();
    $this->assertModelExists($child);
    expect(RecurringEntry::count())->toBe(2)->and(RecurringEntryOccurrence::query()->pluck('id')->all())->toBe($ids);
});

test('compound reminders apply to every new child and existing per-occurrence dispatch deduplicates each reminder', function () {
    config()->set('features.push_notifications.enabled', false);
    $this->compoundData['reminder_days_before'] = [7, 3];
    $this->compoundData['components'][1]['date'] = '2026-06-16';
    $this->post(route('compound-recurring-entries.store'), $this->compoundData)->assertSessionHasNoErrors();
    $parent = CompoundRecurringEntry::query()->sole();
    foreach ($parent->components as $component) {
        expect($component->recurringEntry->reminder_days_before)->toBe([3, 7]);
    }
    $this->travelTo(now()->setDate(2026, 6, 13));
    $first = app(DailyRecurringReminderService::class)->run();
    $second = app(DailyRecurringReminderService::class)->run();
    expect($first)->toMatchArray(['notified' => 2, 'duplicates' => 0])
        ->and($second)->toMatchArray(['notified' => 0, 'duplicates' => 2]);
    $deliveries = ReminderDelivery::query()->get();
    expect($deliveries->pluck('remindable_id')->unique())->toHaveCount(2);
    $this->post(route('compound-recurring-entries.components.store', $parent->uuid), [
        'components' => [['name' => 'Conguaglio', 'date' => '2027-01-10', 'amount' => '20.00']],
        'reminder_days_before' => [2],
    ])->assertSessionHasNoErrors();
    expect($parent->components()->reorder('id', 'desc')->first()->recurringEntry->reminder_days_before)->toBe([2]);
    expect($parent->components()->first()->recurringEntry->reminder_days_before)->toBe([3, 7]);
});

test('compound reminder validation matches ordinary limits and rejects duplicates before creation', function () {
    foreach ([[0], [16], [3, 3], [1, 2, 3, 4]] as $days) {
        $this->post(route('compound-recurring-entries.store'), [...$this->compoundData, 'reminder_days_before' => $days])->assertSessionHasErrors();
    }
    expect(CompoundRecurringEntry::count())->toBe(0)->and(RecurringEntry::count())->toBe(0);
});

test('aggregated actions reuse ordinary occurrence permissions and child highlight routes', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $row = $parent->components()->first()->recurringEntry->occurrences()->orderBy('id')->first();
    $this->get(route('recurring-entries.show', $parent->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('recurringEntry.occurrences.0.uuid', $row->uuid)
        ->where('recurringEntry.occurrences.0.can_update_occurrence', true)
        ->where('recurringEntry.occurrences.0.can_update_amount', true)
        ->where('recurringEntry.occurrences.0.can_register_payment', false));
    $this->travelTo(now()->setDate(2026, 6, 16));
    app(UpdateRecurringOccurrenceAmountService::class)->update($row, $this->compoundUser, 315);
    $this->get(route('recurring-entries.show', $parent->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('recurringEntry.occurrences.0.can_register_payment', true));
});

test('retroactive compound starts reuse ordinary open-year validation without posting transactions', function () {
    UserYear::query()->firstOrCreate(['user_id' => $this->compoundUser->id, 'year' => 2026], ['is_closed' => false]);
    $data = $this->compoundData;
    $data['components'][0]['date'] = '2026-01-01';
    $before = $this->compoundAccount->fresh()->getAttributes();
    $this->post(route('compound-recurring-entries.store'), $data)->assertSessionHasNoErrors();
    $child = CompoundRecurringEntry::query()->sole()->components()->first()->recurringEntry;
    expect($child->start_date->toDateString())->toBe('2026-01-01')
        ->and($child->occurrences()->orderBy('id')->first()->expected_date->toDateString())->toBe('2026-01-01')
        ->and(Transaction::count())->toBe(0)
        ->and($this->compoundAccount->fresh()->getAttributes())->toBe($before);
    UserYear::query()->create(['user_id' => $this->compoundUser->id, 'year' => 2025, 'is_closed' => true]);
    $data['components'][1]['date'] = '2025-12-16';
    $this->post(route('compound-recurring-entries.store'), $data)->assertSessionHasErrors('components.1.date');
    expect(CompoundRecurringEntry::count())->toBe(1)->and(RecurringEntry::count())->toBe(2);
});

test('compound validation uses translated field messages for missing and invalid payment values', function () {
    app()->setLocale('it');
    $this->compoundUser->update(['locale' => 'it']);
    $data = $this->compoundData;
    $data['components'][0] = ['name' => '', 'date' => '', 'amount' => '0'];
    $data['components'][1]['date'] = '16/12/2026';
    $response = $this->postJson(route('compound-recurring-entries.store'), $data)->assertUnprocessable();
    $response->assertJsonValidationErrors(['components.0.name', 'components.0.date', 'components.0.amount', 'components.1.date']);
    foreach (Arr::flatten($response->json('errors')) as $message) {
        expect($message)->not->toContain('components.')->not->toContain('The ');
    }
    expect(RecurringEntry::count())->toBe(0);
});

test('compound children keep ordinary date amount skip posting allocation and payment history flows', function () {
    UserYear::query()->firstOrCreate(['user_id' => $this->compoundUser->id, 'year' => 2026], ['is_closed' => false]);
    $data = $this->compoundData;
    $data['components'][0]['date'] = '2026-01-01';
    $data['components'][1]['date'] = '2026-01-05';
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $data);
    [$first, $second] = $parent->components()->get()->map->recurringEntry->all();
    $row = $first->occurrences()->orderBy('id')->first();
    $secondRow = $second->occurrences()->orderBy('id')->first();
    $this->patch(route('recurring-entries.occurrences.override.update', [$second->uuid, $secondRow->uuid]), ['due_date' => '2026-01-08', 'account_uuid' => $this->compoundAccount->uuid])->assertSessionHasNoErrors();
    $this->patch(route('recurring-entries.occurrences.skip', [$second->uuid, $secondRow->uuid]))->assertSessionHasNoErrors();
    expect($secondRow->fresh()->status->value)->toBe('skipped');
    $this->patch(route('recurring-entries.occurrences.restore', [$second->uuid, $secondRow->uuid]))->assertSessionHasNoErrors();
    $this->patch(route('recurring-entries.occurrences.amount.update', [$first->uuid, $row->uuid]), ['amount' => '310.00'])->assertSessionHasNoErrors();
    expect($row->fresh()->amount_confirmed_at)->not->toBeNull();
    foreach ([100, 210] as $amount) {
        $transaction = Transaction::query()->create([
            'user_id' => $this->compoundUser->id, 'account_id' => $this->compoundAccount->id,
            'category_id' => $this->compoundCategory->id, 'direction' => 'expense', 'amount' => $amount,
            'currency' => 'EUR', 'transaction_date' => '2026-01-10', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed',
        ]);
        $this->post(route('recurring-entries.occurrences.payments.store', [$first->uuid, $row->uuid]), ['transaction_uuid' => $transaction->uuid, 'amount' => $amount])->assertSessionHasNoErrors();
        if ($amount === 100) {
            $this->getJson(route('recurring-entries.show', ['recurringEntry' => $first->uuid, 'highlight' => $row->uuid]))
                ->assertJsonPath('recurringEntry.occurrences.0.remaining_amount', 210)
                ->assertJsonPath('recurringEntry.occurrences.0.settlement_status', 'partial');
        }
    }
    $this->getJson(route('recurring-entries.show', ['recurringEntry' => $first->uuid, 'highlight' => $row->uuid]))
        ->assertJsonPath('recurringEntry.occurrences.0.remaining_amount', 0)
        ->assertJsonCount(2, 'recurringEntry.occurrences.0.payments');
    $this->patch(route('recurring-entries.occurrences.amount.update', [$second->uuid, $secondRow->uuid]), ['amount' => '360.00'])->assertSessionHasNoErrors();
    $this->post(route('recurring-entries.occurrences.convert', [$second->uuid, $secondRow->uuid]))->assertSessionHasNoErrors();
    expect($secondRow->fresh()->converted_transaction_id)->not->toBeNull()->and(Transaction::count())->toBe(3);
});

test('visible plan count groups compound children and preserves monthly occurrences and search filters', function () {
    $data = $this->compoundData;
    $data['components'][0]['amount'] = '550.00';
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $data);
    $ordinary = $parent->components()->firstOrFail()->recurringEntry->replicate(['uuid']);
    $ordinary->title = 'Ordinaria';
    $ordinary->save();

    foreach ([6 => ['Acconto', 550], 12 => ['Saldo', 350]] as $month => [$name, $amount]) {
        $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => $month, 'search' => 'IMU']))
            ->assertOk()
            ->assertJsonPath('monthlyCalendar.summary.entries_count', 1)
            ->assertJsonPath('monthlyCalendar.days.0.occurrences.0.recurring_entry.can_delete', false)
            ->assertJsonPath('monthlyCalendar.summary.occurrences_count', 1)
            ->assertJsonCount(1, 'monthlyCalendar.days.0.occurrences')
            ->assertJsonPath('monthlyCalendar.days.0.occurrences.0.expected_amount', $amount);
    }
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 6]))
        ->assertOk()->assertJsonPath('monthlyCalendar.summary.entries_count', 2);
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 6, 'search' => 'Acconto']))
        ->assertOk()->assertJsonPath('monthlyCalendar.summary.entries_count', 1);
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 6, 'search' => 'Assente']))
        ->assertOk()->assertJsonPath('monthlyCalendar.summary.entries_count', 0);
});

test('unified detail keeps annual history real owners and canonical legacy redirects without changing saved titles', function () {
    UserYear::query()->firstOrCreate(['user_id' => $this->compoundUser->id, 'year' => 2025], ['is_closed' => false]);
    $data = $this->compoundData;
    $data['name'] = 'IMU 2026';
    $data['components'] = [
        ['name' => 'IMU 2026 Acconto', 'date' => '2025-06-16', 'amount' => '550.00'],
        ['name' => 'IMU 2026 Saldo', 'date' => '2025-12-16', 'amount' => '350.00'],
    ];
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $data);
    [$advance, $balance] = $parent->components()->orderBy('id')->get()->map->recurringEntry->all();
    $before = [$advance->fresh()->getAttributes(), $balance->fresh()->getAttributes(), $this->compoundAccount->fresh()->getAttributes()];
    $target = $balance->occurrences()->whereDate('expected_date', '2025-12-16')->firstOrFail();
    $canonical = route('recurring-entries.show', $parent->uuid);
    $this->get($canonical)->assertInertia(fn (Assert $page) => $page->component('transactions/recurring/Show')
        ->where('compound.name', 'IMU 2026')->has('recurringEntry.occurrences', 2)
        ->where('recurringEntry.occurrences.0.component_name', 'Acconto')->where('recurringEntry.occurrences.0.entry_uuid', $advance->uuid)
        ->where('recurringEntry.occurrences.1.component_name', 'Saldo')->where('recurringEntry.occurrences.1.entry_uuid', $balance->uuid)
        ->where('recurringEntry.occurrence_years.0.year', 2025)->where('recurringEntry.occurrence_years.0.count', 2)
        ->where('recurringEntry.occurrence_years.1.year', 2026)->where('recurringEntry.occurrence_years.1.count', 2));
    $this->getJson(route('recurring-entries.show', ['recurringEntry' => $parent->uuid, 'highlight' => $target->uuid]))->assertOk()->assertJsonPath('recurringEntry.entry.uuid', $balance->uuid);
    $this->getJson(route('recurring-entries.show', ['recurringEntry' => $parent->uuid, 'occurrence_year' => 2025, 'highlight' => $target->uuid]))
        ->assertOk()->assertJsonCount(2, 'occurrences')->assertJsonPath('occurrences.1.uuid', $target->uuid)->assertJsonPath('occurrences.1.entry_uuid', $balance->uuid);
    $this->get(route('compound-recurring-entries.show', ['compoundRecurringEntry' => $parent->uuid, 'highlight' => $target->uuid]))
        ->assertRedirect(route('recurring-entries.show', ['recurringEntry' => $parent->uuid, 'highlight' => $target->uuid]));
    $this->get(route('recurring-entries.show', ['recurringEntry' => $balance->uuid, 'highlight' => $target->uuid]))
        ->assertRedirect(route('recurring-entries.show', ['recurringEntry' => $parent->uuid, 'highlight' => $target->uuid, 'component' => $balance->uuid]));
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 6]))->assertJsonPath('monthlyCalendar.days.0.occurrences.0.title', 'IMU 2026 · Acconto');
    expect([$advance->fresh()->getAttributes(), $balance->fresh()->getAttributes(), $this->compoundAccount->fresh()->getAttributes()])->toBe($before)
        ->and(Transaction::count())->toBe(0);
});

test('unified rows preserve independent permissions and reject mutations with the wrong owner', function () {
    UserYear::query()->firstOrCreate(['user_id' => $this->compoundUser->id, 'year' => 2026], ['is_closed' => false]);
    $this->travelTo(now()->setDate(2026, 12, 17));
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    [$advance, $balance] = $parent->components()->orderBy('id')->get()->map->recurringEntry->all();
    $otherCategory = Category::query()->create(['user_id' => $this->compoundUser->id, 'name' => 'Altre imposte', 'slug' => 'other-compound-tax', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $balance->update(['category_id' => $otherCategory->id]);
    $advanceRow = $advance->occurrences()->orderBy('id')->firstOrFail();
    $balanceRow = $balance->occurrences()->orderBy('id')->firstOrFail();
    $this->patch(route('recurring-entries.occurrences.amount.update', [$advance->uuid, $balanceRow->uuid]), ['amount' => '360.00'])->assertNotFound();
    $this->patch(route('recurring-entries.occurrences.amount.update', [$balance->uuid, $balanceRow->uuid]), ['amount' => '360.00'])->assertSessionHasNoErrors();
    $advanceTransaction = app(RecurringEntryPostingService::class)->post($advanceRow, $this->compoundUser);
    $balanceTransaction = app(RecurringEntryPostingService::class)->post($balanceRow, $this->compoundUser);
    $attributes = [$advanceTransaction->fresh()->getAttributes(), $balanceTransaction->fresh()->getAttributes()];
    $this->getJson(route('recurring-entries.show', $parent->uuid))->assertOk()
        ->assertJsonPath('recurringEntry.occurrences.0.payment_defaults.category_uuid', $this->compoundCategory->uuid)
        ->assertJsonPath('recurringEntry.occurrences.1.payment_defaults.category_uuid', $otherCategory->uuid)
        ->assertJsonPath('recurringEntry.occurrences.0.converted_transaction.uuid', $advanceTransaction->uuid)
        ->assertJsonPath('recurringEntry.occurrences.1.converted_transaction.uuid', $balanceTransaction->uuid)
        ->assertJsonPath('recurringEntry.occurrences.0.converted_transaction.can_refund', true)
        ->assertJsonPath('recurringEntry.occurrences.1.converted_transaction.can_refund', true)
        ->assertJsonPath('recurringEntry.summary.paid_amount', 660)
        ->assertJsonPath('recurringEntry.summary.paid_count', 2);
    expect([$advanceTransaction->fresh()->getAttributes(), $balanceTransaction->fresh()->getAttributes()])->toBe($attributes);
    $this->actingAs(User::factory()->create())->get(route('recurring-entries.show', $parent->uuid))->assertNotFound();
});

test('compound annual creation and automation extend the same twelve month window without distant years or duplicates', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    [$advance, $balance] = $parent->components()->orderBy('id')->get()->map->recurringEntry->all();
    expect($advance->occurrences()->pluck('expected_date')->map->toDateString()->all())->toBe(['2026-06-16'])
        ->and($balance->occurrences()->pluck('expected_date')->map->toDateString()->all())->toBe(['2026-12-16'])
        ->and(Transaction::count())->toBe(0);
    $initialRows = RecurringEntryOccurrence::query()->orderBy('id')->get()->map->getAttributes()->all();
    $pipeline = app(RecurringEntryLifecycleService::class);
    $pipeline->runAutomationPipeline();
    expect(RecurringEntryOccurrence::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($initialRows);
    $this->travelTo(now()->setDate(2026, 6, 16));
    $pipeline->runAutomationPipeline();
    expect($advance->occurrences()->orderBy('expected_date')->pluck('expected_date')->map->toDateString()->all())->toBe(['2026-06-16', '2027-06-16'])
        ->and($balance->occurrences()->pluck('expected_date')->map->toDateString()->all())->toBe(['2026-12-16']);
    $this->travelTo(now()->setDate(2026, 12, 16));
    $pipeline->runAutomationPipeline();
    expect($balance->occurrences()->orderBy('expected_date')->pluck('expected_date')->map->toDateString()->all())->toBe(['2026-12-16', '2027-12-16'])
        ->and(Transaction::count())->toBe(0);
    $allRows = RecurringEntryOccurrence::query()->orderBy('id')->get()->map->getAttributes()->all();
    $pipeline->runAutomationPipeline();
    expect(RecurringEntryOccurrence::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($allRows)
        ->and(RecurringEntryOccurrence::query()->whereIn('id', array_column($initialRows, 'id'))->orderBy('id')->get()->map->getAttributes()->all())->toBe($initialRows);
});

test('archiving a component preserves protected history and cancels only pristine future occurrences', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $component = $parent->components()->firstOrFail();
    $child = $component->recurringEntry;
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($child, CarbonImmutable::parse('2031-12-31'));
    $rows = $child->occurrences()->orderBy('sequence_number')->get();
    $this->travelTo(now()->setDate(2026, 6, 16));
    $payment = app(RecurringEntryPostingService::class)->post($rows[0], $this->compoundUser);
    $rows[1]->update(['due_date' => '2027-06-18']);
    $rows[2]->forceFill(['amount_confirmed_at' => now()])->save();
    $rows[3]->update(['expected_amount' => 310]);
    UserYear::query()->firstOrCreate(['user_id' => $this->compoundUser->id, 'year' => 2030], ['is_closed' => false]);
    $this->travelTo(now()->setDate(2030, 6, 16));
    app(UpdateRecurringOccurrenceAmountService::class)->update($rows[4], $this->compoundUser, 300);
    $partial = Transaction::query()->create([
        'user_id' => $this->compoundUser->id, 'account_id' => $this->compoundAccount->id,
        'category_id' => $this->compoundCategory->id, 'direction' => 'expense', 'amount' => 100,
        'currency' => 'EUR', 'transaction_date' => '2030-06-16', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed',
    ]);
    $allocation = app(AllocateOccurrencePaymentService::class)->allocate($rows[4]->fresh(), $partial, $this->compoundUser, '100.00');
    $this->travelTo(now()->setDate(2026, 6, 17));
    $snapshots = $rows->take(5)->map(fn ($row) => $row->fresh()->getAttributes())->all();
    $paymentBefore = $payment->fresh()->getAttributes();
    $partialBefore = $partial->fresh()->getAttributes();
    $allocationBefore = $allocation->fresh()->getAttributes();
    $accountBefore = $this->compoundAccount->fresh()->getAttributes();
    $sibling = $parent->components()->get()[1]->recurringEntry;
    $siblingBefore = $sibling->getAttributes();
    $ids = $child->occurrences()->pluck('id')->all();

    $this->patch(route('recurring-entries.components.archive', [$parent->uuid, $component->uuid]))->assertRedirect(route('recurring-entries.show', $parent->uuid));
    expect($component->fresh()->archived_at)->not->toBeNull()
        ->and($child->fresh()->is_active)->toBeFalse()->and($child->fresh()->auto_generate_occurrences)->toBeFalse()
        ->and($child->fresh()->status->value)->toBe('cancelled')
        ->and($rows->take(5)->map(fn ($row) => $row->fresh()->getAttributes())->all())->toBe($snapshots)
        ->and($rows[5]->fresh()->status->value)->toBe('cancelled')
        ->and($payment->fresh()->getAttributes())->toBe($paymentBefore)
        ->and($partial->fresh()->getAttributes())->toBe($partialBefore)
        ->and($allocation->fresh()->getAttributes())->toBe($allocationBefore)
        ->and($this->compoundAccount->fresh()->getAttributes())->toBe($accountBefore)
        ->and($sibling->fresh()->getAttributes())->toBe($siblingBefore)
        ->and($child->occurrences()->pluck('id')->all())->toBe($ids)
        ->and($child->periods()->latest('id')->first()->ended_reason)->toBe('cancelled')
        ->and($parent->fresh()->derivedStatus())->toBe('active');
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($child->fresh(), CarbonImmutable::parse('2035-01-01'));
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect($child->occurrences()->pluck('id')->all())->toBe($ids);
    $this->postJson(route('recurring-transactions.refund', $payment->uuid))->assertUnprocessable();
    $this->deleteJson(route('recurring-entries.occurrences.undo-conversion', [$child->uuid, $rows[0]->uuid]))->assertUnprocessable();
    expect($payment->fresh()->getAttributes())->toBe($paymentBefore);
    $archivedAt = $component->fresh()->archived_at;
    $periods = $child->periods()->get()->map->getAttributes()->all();
    $this->patch(route('recurring-entries.components.archive', [$parent->uuid, $component->uuid]))->assertSessionHasNoErrors();
    expect($component->fresh()->archived_at->equalTo($archivedAt))->toBeTrue()->and($child->periods()->get()->map->getAttributes()->all())->toBe($periods);
    $this->getJson(route('recurring-entries.show', [$parent->uuid, 'component' => $child->uuid]))
        ->assertJsonCount(1, 'compound.components')->assertJsonCount(1, 'compound.archived_components')
        ->assertJsonPath('compound.is_archived', true)->assertJsonPath('recurringEntry.entry.can_edit', false)
        ->assertJsonPath('recurringEntry.actions.can_reactivate', false)->assertJsonPath('recurringEntry.actions.can_delete', false)
        ->assertJsonPath('recurringEntry.summary.remaining_amount', 350);
    $this->getJson(route('recurring-entries.show', $parent->uuid))->assertJsonPath('recurringEntry.entry.uuid', $sibling->uuid);
    expect(RecurringEntryOccurrence::query()->withoutArchivedFutureComponents()->where('recurring_entry_id', $child->id)->pluck('id')->all())->toBe([$rows[0]->id]);
});

test('archived component mutation endpoints reject changes and history stays readable', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $component = $parent->components()->firstOrFail();
    $child = $component->recurringEntry;
    $row = $child->occurrences()->firstOrFail();
    app(CompoundRecurringEntryService::class)->archive($this->compoundUser, $parent, $component);
    $before = $row->fresh()->getAttributes();
    foreach (['pause', 'resume', 'cancel'] as $action) {
        $this->patchJson(route('recurring-entries.'.$action, $child->uuid), ['periods' => 1])->assertUnprocessable()->assertJsonValidationErrors('entry');
    }
    foreach (['skip', 'restore', 'cancel'] as $action) {
        $this->patchJson(route('recurring-entries.occurrences.'.$action, [$child->uuid, $row->uuid]))->assertUnprocessable()->assertJsonValidationErrors('occurrence');
    }
    $this->postJson(route('recurring-entries.occurrences.convert', [$child->uuid, $row->uuid]))->assertUnprocessable()->assertJsonValidationErrors('occurrence');
    $this->postJson(route('recurring-entries.manual-costs.store', $child->uuid), ['date' => '2026-01-01', 'amount' => 100])->assertForbidden();
    expect($row->fresh()->getAttributes())->toBe($before)->and(Transaction::count())->toBe(0);
    $this->getJson(route('recurring-entries.show', [$parent->uuid, 'component' => $child->uuid, 'show_cancelled' => 1]))
        ->assertSuccessful()->assertJsonPath('recurringEntry.occurrences.0.can_skip', false)
        ->assertJsonPath('recurringEntry.occurrences.0.can_update_amount', false)
        ->assertJsonPath('recurringEntry.occurrences.0.can_register_payment', false);
});

test('archiving requires edit access and membership of the specified parent', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $component = $parent->components()->firstOrFail();
    $other = app(CompoundRecurringEntryService::class)->create($this->compoundUser, [...$this->compoundData, 'name' => 'Altro']);
    $this->patch(route('recurring-entries.components.archive', [$other->uuid, $component->uuid]))->assertNotFound();
    $this->actingAs(User::factory()->create());
    $this->patch(route('recurring-entries.components.archive', [$parent->uuid, $component->uuid]))->assertForbidden();
    expect($component->fresh()->archived_at)->toBeNull();
});

test('cancelled occurrence filtering is annual and preserves both same-date saldo sequences', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $child = $parent->components()->get()[1]->recurringEntry;
    $first = $child->occurrences()->firstOrFail();
    $first->update(['status' => 'cancelled']);
    $second = $child->occurrences()->create(['sequence_number' => 13, 'expected_date' => '2026-12-16', 'due_date' => '2026-12-16', 'expected_amount' => 350, 'status' => 'pending']);
    $future = $child->occurrences()->create(['sequence_number' => 14, 'expected_date' => '2027-12-16', 'due_date' => '2027-12-16', 'expected_amount' => 350, 'status' => 'cancelled']);
    $snapshot = $child->occurrences()->get()->map->getAttributes()->all();
    $this->travelTo(now()->setDate(2027, 1, 10));
    $base = route('recurring-entries.show', $parent->uuid);
    $summary = $this->getJson($base)->assertJsonCount(0, 'recurringEntry.occurrences')->json('recurringEntry.summary');
    $this->getJson($base.'?occurrence_year=2026')->assertJsonCount(2, 'occurrences')->assertJsonPath('show_cancelled', false);
    $this->getJson($base.'?occurrence_year=2026&show_cancelled=1')->assertJsonCount(3, 'occurrences')->assertJsonPath('occurrences.1.uuid', $first->uuid)->assertJsonPath('occurrences.2.uuid', $second->uuid);
    $this->getJson($base.'?occurrence_year=2027')->assertJsonCount(0, 'occurrences');
    $this->getJson($base.'?occurrence_year=2027&show_cancelled=1')->assertJsonCount(1, 'occurrences')->assertJsonPath('occurrences.0.uuid', $future->uuid);
    $this->getJson($base.'?highlight='.$first->uuid)->assertJsonPath('recurringEntry.occurrence_page.show_cancelled', true)->assertJsonPath('recurringEntry.summary', $summary);
    expect($child->occurrences()->get()->map->getAttributes()->all())->toBe($snapshot);
});

test('archived protected future occurrences leave projections and reminders while deliveries and rows remain intact', function () {
    config()->set('features.push_notifications.enabled', false);
    $this->compoundData['reminder_days_before'] = [3, 7];
    $this->compoundData['components'][1]['date'] = '2026-06-16';
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $component = $parent->components()->firstOrFail();
    $child = $component->recurringEntry;
    $row = $child->occurrences()->firstOrFail();
    $row->forceFill(['amount_confirmed_at' => now()])->save();
    $this->travelTo(now()->setDate(2026, 6, 9));
    expect(app(DailyRecurringReminderService::class)->run()['notified'])->toBe(2);
    $deliveries = ReminderDelivery::query()->get()->map->getAttributes()->all();
    app(CompoundRecurringEntryService::class)->archive($this->compoundUser, $parent, $component);
    $this->travelTo(now()->setDate(2026, 6, 13));
    expect(app(DailyRecurringReminderService::class)->run()['notified'])->toBe(1)
        ->and(ReminderDelivery::query()->where('remindable_id', $row->id)->count())->toBe(1)
        ->and(ReminderDelivery::query()->whereIn('id', array_column($deliveries, 'id'))->get()->map->getAttributes()->all())->toBe($deliveries)
        ->and($row->fresh()->status->value)->toBe('pending');
    $this->get(route('dashboard', ['year' => 2026, 'month' => 6]))->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.scheduled_summary.upcoming', fn ($items): bool => ! collect($items)->contains('id', 'recurring-'.$row->id))
        ->where('dashboard.recurring_summary.planned_count', 1));
    $sheet = app(MonthlyTransactionSheetService::class)->build($this->compoundUser, 2026, 6);
    expect($sheet['planned_occurrences'])->toHaveCount(1);
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 6]))
        ->assertJsonCount(1, 'recurringEntries')->assertJsonPath('compoundEntries.0.components_count', 1)
        ->assertJsonCount(1, 'monthlyCalendar.days.0.occurrences');
});

test('unpaid component editing ignores sibling payments and synchronizes only its mutable future occurrences', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    app(CompoundRecurringEntryService::class)->add($this->compoundUser, $parent, [['name' => 'Test', 'date' => '2026-11-16', 'amount' => 250]]);
    $advance = $parent->components()->first()->recurringEntry;
    $this->travelTo(now()->setDate(2026, 6, 16));
    $payment = app(RecurringEntryPostingService::class)->post($advance->occurrences()->first(), $this->compoundUser);
    $this->travelTo(now()->setDate(2026, 10, 20));
    $component = $parent->components()->where('name', 'Test')->firstOrFail();
    $child = $component->recurringEntry;
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($child, CarbonImmutable::parse('2029-12-31'));
    $rows = $child->occurrences()->orderBy('sequence_number')->get();
    $rows[1]->update(['status' => 'skipped']);
    $rows[2]->update(['due_date' => '2028-11-18']);
    $rows[3]->forceFill(['amount_confirmed_at' => now()])->save();
    $protected = $rows->skip(1)->map(fn ($row) => $row->fresh()->getAttributes())->values()->all();
    $otherRows = RecurringEntryOccurrence::query()->where('recurring_entry_id', '!=', $child->id)->get()->map->getAttributes()->all();
    $paymentBefore = $payment->fresh()->getAttributes();
    $balanceBefore = $this->compoundAccount->fresh()->getAttributes();
    $reference = TrackedItem::query()->create(['user_id' => $this->compoundUser->id, 'name' => 'Comune', 'slug' => 'comune', 'is_active' => true]);
    $this->getJson(route('recurring-entries.show', [$parent->uuid, 'component' => $child->uuid]))
        ->assertJsonPath('recurringEntry.entry.stats.converted_occurrences', 1)
        ->assertJsonPath('recurringEntry.entry.component_edit_locked', false)
        ->assertJsonPath('recurringEntry.entry.component_name', 'Test');
    $this->getJson(route('recurring-entries.show', [$parent->uuid, 'component' => $advance->uuid]))
        ->assertJsonPath('recurringEntry.entry.component_edit_locked', true);
    $payload = [
        'title' => 'Conguaglio', 'description' => 'Conguaglio', 'account_uuid' => $this->compoundAccount->uuid,
        'category_uuid' => $this->compoundCategory->uuid, 'tracked_item_uuid' => $reference->uuid,
        'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'is_amount_variable' => true,
        'expected_amount' => 275, 'start_date' => '2026-11-30', 'recurrence_type' => 'yearly', 'recurrence_interval' => 1,
        'recurrence_rule' => ['mode' => 'month_day', 'month' => 11, 'day' => 30], 'end_mode' => 'never',
        'reminder_days_before' => [], 'auto_create_transaction' => false, 'auto_generate_occurrences' => true, 'is_active' => true,
    ];
    $this->patch(route('recurring-entries.update', $child->uuid), $payload)->assertSessionHasNoErrors();
    expect($component->fresh()->name)->toBe('Conguaglio')->and($child->fresh()->title)->toBe('IMU · Conguaglio')
        ->and($child->fresh()->tracked_item_id)->toBe($reference->id)
        ->and($rows[0]->fresh()->expected_date->toDateString())->toBe('2026-11-30')
        ->and((float) $rows[0]->fresh()->expected_amount)->toBe(275.0)
        ->and($rows->skip(1)->map(fn ($row) => $row->fresh()->getAttributes())->values()->all())->toBe($protected)
        ->and(RecurringEntryOccurrence::query()->where('recurring_entry_id', '!=', $child->id)->get()->map->getAttributes()->all())->toBe($otherRows)
        ->and($payment->fresh()->getAttributes())->toBe($paymentBefore)
        ->and($this->compoundAccount->fresh()->getAttributes())->toBe($balanceBefore)
        ->and($child->occurrences()->pluck('id')->all())->toBe($rows->modelKeys());
    $this->patch(route('recurring-entries.update', $child->uuid), $payload)->assertSessionHasNoErrors();
    expect($child->occurrences()->pluck('id')->all())->toBe($rows->modelKeys())->and(Transaction::count())->toBe(1);
});

test('component renames reject duplicate names and roll back with invalid ordinary updates', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $component = $parent->components()->firstOrFail();
    $child = $component->recurringEntry;
    $before = $child->getAttributes();
    expect(fn () => app(RecurringEntryManagementService::class)->update($this->compoundUser, $child, ['title' => 'saldo']))->toThrow(ValidationException::class);
    expect(fn () => app(RecurringEntryManagementService::class)->update($this->compoundUser, $child, ['title' => 'Nuovo', 'expected_amount' => -1]))->toThrow(ValidationException::class);
    expect($component->fresh()->name)->toBe('Acconto')->and($child->fresh()->getAttributes())->toBe($before);
});

test('component edit protections apply to its own partial and matched payments', function (string $link) {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $child = $parent->components()->firstOrFail()->recurringEntry;
    $row = $child->occurrences()->firstOrFail();
    $transaction = Transaction::query()->create([
        'user_id' => $this->compoundUser->id, 'account_id' => $this->compoundAccount->id,
        'category_id' => $this->compoundCategory->id, 'direction' => 'expense', 'amount' => 100,
        'currency' => 'EUR', 'transaction_date' => '2026-01-10', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed',
    ]);
    if ($link === 'allocation') {
        $row->paymentAllocations()->create(['transaction_id' => $transaction->id, 'amount_cents' => 10000]);
    } else {
        $row->update(['matched_transaction_id' => $transaction->id]);
    }
    $before = $row->fresh()->getAttributes();
    $this->getJson(route('recurring-entries.show', $parent->uuid))->assertJsonPath('recurringEntry.entry.component_edit_locked', true);
    foreach ([['title' => 'Nuovo'], ['recurrence_rule' => ['mode' => 'month_day', 'month' => 6, 'day' => 30]], ['tracked_item_id' => 999999]] as $changes) {
        expect(fn () => app(RecurringEntryManagementService::class)->update($this->compoundUser, $child, $changes))->toThrow(ValidationException::class);
    }
    expect($row->fresh()->getAttributes())->toBe($before);
})->with(['allocation', 'matched']);

test('unchanged legacy component names do not rewrite paid plan titles or membership', function () {
    $parent = app(CompoundRecurringEntryService::class)->create($this->compoundUser, $this->compoundData);
    $component = $parent->components()->firstOrFail();
    $component->update(['name' => 'IMU Acconto']);
    $child = $component->recurringEntry;
    $child->update(['title' => 'IMU · IMU Acconto']);
    $this->travelTo(now()->setDate(2026, 6, 16));
    $payment = app(RecurringEntryPostingService::class)->post($child->occurrences()->first(), $this->compoundUser);
    $before = $payment->fresh()->getAttributes();
    app(RecurringEntryManagementService::class)->update($this->compoundUser, $child->fresh(), ['title' => $child->title, 'reminder_days_before' => [3]]);
    expect($child->fresh()->title)->toBe('IMU · IMU Acconto')->and($component->fresh()->name)->toBe('IMU Acconto')
        ->and($payment->fresh()->getAttributes())->toBe($before);
});
