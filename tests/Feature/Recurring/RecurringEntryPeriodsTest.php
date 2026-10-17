<?php

use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Recurring\RecurringEntryPeriodService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Transactions\RecurringEntryLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Arr;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(CarbonImmutable::parse('2026-01-01'));
    $this->actor = User::factory()->create();
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2026, 'is_closed' => false]);
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $category = Category::create(['user_id' => $this->actor->id, 'name' => 'Subscription', 'slug' => 'subscription', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->payload = ['title' => 'Subscription', 'description' => 'Subscription', 'account_id' => $this->account->id, 'category_id' => $category->id, 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 1], 'start_date' => '2026-01-01', 'end_mode' => 'never', 'expected_amount' => 10, 'auto_generate_occurrences' => true, 'auto_create_transaction' => false, 'is_active' => true];
    $this->actingAs($this->actor);
});

test('period restart after years preserves due history and creates no inactive arrears', function (bool $automatic): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    $uuid = $entry->uuid;
    $due = $entry->occurrences()->first();
    $protected = $entry->occurrences()->where('sequence_number', 2)->first();
    $protected->update(['due_date' => '2026-02-05', 'amount_confirmed_at' => now()]);
    $before = $protected->fresh()->getAttributes();
    app(RecurringEntryManagementService::class)->cancel($entry);
    expect($due->fresh()->status->value)->toBe('pending')->and($protected->fresh()->getAttributes())->toBe($before);
    $this->travelTo(CarbonImmutable::parse('2030-04-01'));
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2030, 'is_closed' => false]);
    $entry->update(['auto_create_transaction' => $automatic]);
    $restart = [...$this->payload, 'start_date' => '2030-04-01', 'expected_amount' => 15, 'auto_create_transaction' => $automatic];
    $this->patch(route('recurring-entries.reactivate', $uuid), $restart)->assertSessionHasNoErrors();
    expect($entry->fresh()->uuid)->toBe($uuid)->and($entry->fresh()->start_date->toDateString())->toBe('2026-01-01')
        ->and($entry->occurrences()->whereBetween('expected_date', ['2027-01-01', '2030-03-31'])->count())->toBe(0)
        ->and($protected->fresh()->getAttributes())->toBe($before)
        ->and($entry->amountChanges()->sole()->effective_from->toDateString())->toBe('2030-04-01')
        ->and($entry->amountChanges()->sole()->percentage())->toBe(50.0);
    $count = $entry->occurrences()->count();
    $this->patch(route('recurring-entries.reactivate', $uuid), $restart)->assertSessionHasNoErrors();
    app(RecurringEntryOccurrenceGeneratorService::class)->generate($entry->fresh());
    expect($entry->occurrences()->count())->toBe($count)->and($entry->periods()->count())->toBe(2)
        ->and(Transaction::count())->toBe(0)->and((float) $this->account->fresh()->current_balance)->toBe(1000.0);
    $dates = app(RecurringEntryOccurrenceGeneratorService::class)->previewDatesWithinRange($entry->fresh(), CarbonImmutable::parse('2027-01-01'), CarbonImmutable::parse('2030-04-30'));
    expect($dates->map->toDateString()->all())->toBe(['2030-04-01']);
})->with([false, true]);

test('repeated interruptions preserve sequences and compare only homogeneous prices', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    app(RecurringEntryManagementService::class)->cancel($entry);
    $this->travelTo(CarbonImmutable::parse('2026-04-01'));
    app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry, [...$this->payload, 'start_date' => '2026-04-01', 'expected_amount' => 100, 'recurrence_type' => 'yearly', 'recurrence_rule' => ['mode' => 'month_day', 'month' => 4, 'day' => 1]]);
    expect($entry->amountChanges()->sole()->percentage())->toBeNull();
    $this->travelTo(CarbonImmutable::parse('2026-05-01'));
    app(RecurringEntryManagementService::class)->cancel($entry->fresh());
    $this->travelTo(CarbonImmutable::parse('2026-08-01'));
    app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry->fresh(), [...$this->payload, 'start_date' => '2026-08-01', 'expected_amount' => 100, 'recurrence_type' => 'yearly', 'recurrence_rule' => ['mode' => 'month_day', 'month' => 8, 'day' => 1]]);
    expect($entry->amountChanges()->count())->toBe(1)->and($entry->periods()->count())->toBe(3);
    $sequences = $entry->occurrences()->pluck('sequence_number');
    expect($sequences->unique()->count())->toBe($sequences->count());
    if ($directory = getenv('RECURRING_PERIOD_FIXTURE')) {
        app(RecurringEntryManagementService::class)->cancel($entry->fresh());
        file_put_contents($directory.'/detail.html', $this->get(route('recurring-entries.show', $entry->uuid))->assertOk()->getContent());
    }

});

test('reactivation requires explicit future date and edit permissions and excludes installment plans', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    app(RecurringEntryManagementService::class)->cancel($entry);
    $this->patch(route('recurring-entries.reactivate', $entry->uuid), [])->assertSessionHasErrors('start_date');
    $this->actingAs(User::factory()->create())->patch(route('recurring-entries.reactivate', $entry->uuid), $this->payload)->assertNotFound();
    expect($entry->fresh()->status->value)->toBe('cancelled');
});

test('period protections retain allocations and manual overrides and automatic posting only starts in the new period', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    $future = $entry->occurrences()->where('sequence_number', 2)->first();
    $payment = Transaction::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'direction' => 'expense', 'amount' => 3, 'currency' => 'EUR', 'transaction_date' => '2026-01-01', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
    $allocation = $future->paymentAllocations()->create(['transaction_id' => $payment->id, 'amount_cents' => 300]);
    $before = $future->fresh()->getAttributes();
    app(RecurringEntryManagementService::class)->cancel($entry);
    expect($future->fresh()->getAttributes())->toBe($before)->and($allocation->fresh()->amount_cents)->toBe(300);
    $this->travelTo(CarbonImmutable::parse('2026-04-01'));
    $entry->update(['auto_create_transaction' => true]);
    app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry->fresh(), [...$this->payload, 'start_date' => '2026-04-01', 'expected_amount' => 12, 'auto_create_transaction' => true]);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect($future->fresh()->getAttributes())->toBe($before)->and($allocation->fresh()->getAttributes())->toEqual($allocation->getAttributes())
        ->and(Transaction::where('kind', 'scheduled')->count())->toBe(1)
        ->and(Transaction::where('kind', 'scheduled')->sole()->transaction_date->toDateString())->toBe('2026-04-01');
    $this->getJson(route('recurring-entries.show', $entry->uuid))->assertOk()->assertJsonCount(2, 'recurringEntry.activity_periods');
});

test('restart price edits use the existing future synchronizer without changing previous periods', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    app(RecurringEntryManagementService::class)->cancel($entry);
    $this->travelTo(CarbonImmutable::parse('2026-04-01'));
    app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry->fresh(), [...$this->payload, 'start_date' => '2026-04-01', 'expected_amount' => 12]);
    $originalDue = $entry->occurrences()->whereDate('expected_date', '2026-01-01')->sole();
    $old = $entry->periods()->first()->getAttributes();
    app(RecurringEntryManagementService::class)->update($this->actor, $entry->fresh(), ['expected_amount' => 13]);
    expect($originalDue->fresh()->expected_amount)->toBe('10.00')
        ->and($entry->occurrences()->whereDate('expected_date', '2026-05-01')->where('status', 'pending')->sole()->expected_amount)->toBe('13.00')
        ->and($entry->periods()->first()->getAttributes())->toBe($old)
        ->and($entry->amountChanges()->reorder('id', 'desc')->first()->effective_from->toDateString())->toBe('2026-05-01');
    expect((float) $entry->periods()->where('kind', 'reactivated')->sole()->terms['expected_amount'])->toBe(12.0);
    app(RecurringEntryManagementService::class)->update($this->actor, $entry->fresh(), ['expected_amount' => 14]);
    expect($entry->occurrences()->whereDate('expected_date', '2026-05-01')->where('status', 'pending')->sole()->expected_amount)->toBe('14.00');
});

test('existing plan suggestions include all states and preserve reference identity and access isolation', function (): void {
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Provider', 'slug' => 'provider', 'type' => 'subscription', 'is_active' => true]);
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'tracked_item_id' => $reference->id]);
    app(RecurringEntryManagementService::class)->cancel($entry);
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 1]))->assertOk()
        ->assertJsonPath('formOptions.existing_plan_candidates.0.uuid', $entry->uuid)
        ->assertJsonPath('formOptions.existing_plan_candidates.0.reference_uuid', $reference->uuid)
        ->assertJsonPath('formOptions.existing_plan_candidates.0.reference_logo_url', null)
        ->assertJsonPath('formOptions.existing_plan_candidates.0.installment_amount', null);
    app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'tracked_item_id' => $reference->id]);
    expect(RecurringEntry::where('tracked_item_id', $reference->id)->count())->toBe(2);
    $paused = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'tracked_item_id' => $reference->id]);
    $paused->update(['status' => 'paused', 'is_active' => false]);
    $candidates = $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 1]))->assertOk()->json('formOptions.existing_plan_candidates');
    expect(array_column($candidates, 'status'))->toBe(['cancelled', 'active', 'paused'])
        ->and(array_column($candidates, 'reference_uuid'))->toBe([$reference->uuid, $reference->uuid, $reference->uuid])
        ->and(array_column($candidates, 'can_reactivate'))->toBe([true, false, true])
        ->and($candidates[1]['amount'])->toBe(10)
        ->and($candidates[1]['recurrence_type'])->toBe('monthly')
        ->and($candidates[1]['next_due_on'])->toBe('2026-01-01')
        ->and($candidates[1]['activated_on'])->toBe('2026-01-01');

    $this->actingAs(User::factory()->create())->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 1]))->assertOk()->assertJsonCount(0, 'formOptions.existing_plan_candidates');
    $otherReference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Provider', 'slug' => 'another-provider', 'type' => 'subscription', 'is_active' => true]);
    $reference->compatibleCategories()->attach($this->payload['category_id']);
    $otherReference->compatibleCategories()->attach($this->payload['category_id']);
    $otherPlan = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'tracked_item_id' => $otherReference->id, 'entry_type' => 'installment', 'total_amount' => 199.99, 'expected_amount' => null, 'installments_count' => 5, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 5]);
    $candidates[] = ['uuid' => $otherPlan->uuid];
    $completedPlan = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'tracked_item_id' => $reference->id, 'entry_type' => 'installment', 'total_amount' => 100, 'expected_amount' => null, 'installments_count' => 2, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 2]);
    $completedPlan->update(['status' => 'completed', 'is_active' => false]);
    $finitePlan = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'tracked_item_id' => $reference->id, 'end_mode' => 'until_date', 'end_date' => '2026-12-31']);
    $filtered = $this->actingAs($this->actor)->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 1]))->assertOk()->json('formOptions.existing_plan_candidates');
    expect(array_column($filtered, 'uuid'))->toBe(array_column($candidates, 'uuid'));

    if ($directory = getenv('RECURRING_PERIOD_FIXTURE')) {
        $this->actingAs($this->actor);
        $response = $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 1]))->assertOk();
        file_put_contents($directory.'/options.json', json_encode($response->json('formOptions'), JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/candidates.html', $this->get(route('recurring-entries.show', $entry->uuid))->assertOk()->getContent());
    }
    $viewer = User::factory()->create();
    AccountMembership::create([
        'account_id' => $this->account->id, 'user_id' => $viewer->id,
        'role' => 'viewer', 'status' => 'active', 'source' => 'direct',
        'granted_by_user_id' => $this->actor->id, 'joined_at' => now(),
    ]);
    $visible = $this->actingAs($viewer)->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 1]))->assertOk()->json('formOptions.existing_plan_candidates');
    expect(array_column($visible, 'uuid'))->toBe(array_column($candidates, 'uuid'))
        ->and(array_unique(array_column($visible, 'can_reactivate')))->toBe([false]);

});

test('legacy cancelled plans restart without reconstructing missing activity history', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    $entry->periods()->delete();
    $entry->occurrences()->update(['status' => 'cancelled']);
    $entry->update(['status' => 'cancelled', 'is_active' => false]);
    $old = $entry->occurrences()->get()->mapWithKeys(fn ($row) => [$row->uuid => $row->getAttributes()])->all();
    $this->travelTo(CarbonImmutable::parse('2026-06-01'));
    app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry->fresh(), [...$this->payload, 'start_date' => '2026-06-01']);
    expect($entry->periods()->count())->toBe(1)->and($entry->periods()->sole()->starts_on->toDateString())->toBe('2026-06-01');
    foreach ($old as $uuid => $attributes) {
        expect($entry->occurrences()->where('uuid', $uuid)->sole()->getAttributes())->toBe($attributes);
    }
});

test('installment cancellation preserves protected obligations and rejects subscription restart', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'entry_type' => 'installment', 'expected_amount' => null, 'total_amount' => 120, 'installments_count' => 3, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 3]);
    [$partial, $recorded, $unlinked] = $entry->occurrences()->orderBy('sequence_number')->get()->all();
    $payment = Transaction::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'direction' => 'expense', 'amount' => 5, 'currency' => 'EUR', 'transaction_date' => '2026-01-01', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
    $allocation = $partial->paymentAllocations()->create(['transaction_id' => $payment->id, 'amount_cents' => 500]);
    app(RecurringEntryPostingService::class)->post($recorded, $this->actor);
    $partialBefore = $partial->fresh()->getAttributes();
    $recordedBefore = $recorded->fresh()->getAttributes();
    $unlinkedBefore = Arr::except($unlinked->getAttributes(), ['status', 'updated_at']);
    $transactionsBefore = Transaction::orderBy('id')->get()->map->getAttributes()->all();
    $allocationBefore = $allocation->fresh()->getAttributes();
    $balanceBefore = $this->account->fresh()->current_balance;

    app(RecurringEntryManagementService::class)->cancel($entry);
    expect($entry->fresh()->status->value)->toBe('cancelled')
        ->and($entry->fresh()->next_occurrence_date)->toBeNull()
        ->and($entry->occurrences()->orderBy('sequence_number')->pluck('uuid')->all())->toBe([$partial->uuid, $recorded->uuid, $unlinked->uuid])
        ->and($entry->occurrences()->orderBy('sequence_number')->pluck('status')->map->value->all())->toBe(['pending', 'completed', 'cancelled'])
        ->and($partial->fresh()->getAttributes())->toBe($partialBefore)
        ->and($partial->fresh()->remainingPaymentCents())->toBe(3500)
        ->and($recorded->fresh()->getAttributes())->toBe($recordedBefore)
        ->and(Arr::except($unlinked->fresh()->getAttributes(), ['status', 'updated_at']))->toBe($unlinkedBefore);
    $cancelledEntry = $entry->fresh()->getAttributes();
    $cancelledRows = $entry->occurrences()->orderBy('sequence_number')->get()->map->getAttributes()->all();
    $periodsBefore = $entry->periods()->orderBy('id')->get()->map->getAttributes()->all();

    $this->patch(route('recurring-entries.reactivate', $entry->uuid), $this->payload)->assertSessionHasErrors('entry');
    expect($entry->fresh()->getAttributes())->toBe($cancelledEntry)
        ->and($entry->occurrences()->orderBy('sequence_number')->get()->map->getAttributes()->all())->toBe($cancelledRows)
        ->and($entry->periods()->orderBy('id')->get()->map->getAttributes()->all())->toBe($periodsBefore)
        ->and(Transaction::orderBy('id')->get()->map->getAttributes()->all())->toBe($transactionsBefore)
        ->and($allocation->fresh()->getAttributes())->toBe($allocationBefore)
        ->and($this->account->fresh()->current_balance)->toBe($balanceBefore);
});

test('finite restart progress excludes settled occurrences from previous activity periods', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    app(RecurringEntryPostingService::class)->post($entry->occurrences()->where('sequence_number', 1)->sole(), $this->actor);
    app(RecurringEntryManagementService::class)->cancel($entry);
    $this->travelTo(CarbonImmutable::parse('2026-04-01'));
    app(RecurringEntryPeriodService::class)->reactivate($this->actor, $entry->fresh(), [...$this->payload, 'start_date' => '2026-04-01', 'end_mode' => 'after_occurrences', 'occurrences_limit' => 2]);
    $this->getJson(route('recurring-entries.show', $entry->uuid))->assertOk()
        ->assertJsonPath('recurringEntry.summary.settled_occurrences', 1)
        ->assertJsonPath('recurringEntry.summary.current_period_settled_occurrences', 0)
        ->assertJsonPath('recurringEntry.summary.paid_amount', 10);
});

test('future occurrences retain archived settlement history when a plan is cancelled', function (): void {
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, $this->payload);
    $row = $entry->occurrences()->where('sequence_number', 2)->sole();
    $transaction = Transaction::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'recurring_entry_occurrence_id' => $row->id, 'direction' => 'expense', 'amount' => 10, 'currency' => 'EUR', 'transaction_date' => '2026-01-01', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
    $transaction->delete();
    $before = $row->fresh()->getAttributes();
    app(RecurringEntryManagementService::class)->cancel($entry);
    expect($row->fresh()->getAttributes())->toBe($before)->and($transaction->fresh()->trashed())->toBeTrue();
});

test('existing installment previews show the actual next installment separately from the total without changing it', function (): void {
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Shop', 'slug' => 'shop', 'type' => 'subscription', 'is_active' => true]);
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'tracked_item_id' => $reference->id, 'entry_type' => 'installment', 'total_amount' => 199.99, 'expected_amount' => null, 'installments_count' => 5, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 5]);
    $next = $entry->occurrences()->orderBy('expected_date')->first();
    $next->update(['expected_amount' => 35]);
    $before = $next->fresh()->getAttributes();
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 1]))->assertOk()
        ->assertJsonPath('formOptions.existing_plan_candidates.0.total_amount', 199.99)
        ->assertJsonPath('formOptions.existing_plan_candidates.0.installment_amount', 35)
        ->assertJsonPath('formOptions.existing_plan_candidates.0.can_reactivate', false);
    expect($next->fresh()->getAttributes())->toBe($before);
});

test('installment previews retain overdue amounts and skip zero or fully settled rows', function (): void {
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Shop', 'slug' => 'shop', 'type' => 'subscription', 'is_active' => true]);
    $entry = app(RecurringEntryManagementService::class)->store($this->actor, [...$this->payload, 'tracked_item_id' => $reference->id, 'entry_type' => 'installment', 'total_amount' => 200, 'expected_amount' => null, 'installments_count' => 5, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 5]);
    $rows = $entry->occurrences()->orderBy('sequence_number')->get();
    $rows[0]->update(['expected_amount' => 0]);
    $rows[1]->update(['expected_amount' => 35]);
    $payment = Transaction::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id, 'direction' => 'expense', 'amount' => 35, 'currency' => 'EUR', 'transaction_date' => '2026-01-01', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
    $rows[1]->paymentAllocations()->create(['transaction_id' => $payment->id, 'amount_cents' => 1000]);
    $before = $rows[1]->fresh()->getAttributes();
    $this->travelTo(CarbonImmutable::parse('2026-06-01'));
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 6]))->assertOk()
        ->assertJsonPath('formOptions.existing_plan_candidates.0.installment_amount', 35)
        ->assertJsonPath('formOptions.existing_plan_candidates.0.installment_remaining_amount', 25)
        ->assertJsonPath('formOptions.existing_plan_candidates.0.next_due_on', '2026-02-01');
    expect($rows[1]->fresh()->getAttributes())->toBe($before);
    $rows[1]->paymentAllocations()->update(['amount_cents' => 3500]);
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 6]))->assertOk()
        ->assertJsonPath('formOptions.existing_plan_candidates.0.installment_amount', 40)
        ->assertJsonPath('formOptions.existing_plan_candidates.0.next_due_on', '2026-03-01');
    $entry->occurrences()->update(['status' => 'completed']);
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 6]))->assertOk()->assertJsonCount(0, 'formOptions.existing_plan_candidates');
});
