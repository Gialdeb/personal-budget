<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\ReminderDelivery;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Recurring\AutomaticPaymentVerificationService;
use App\Services\Recurring\DeleteRecurringEntryService;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\Reminders\DailyRecurringReminderService;
use App\Services\Transactions\RecurringEntryLifecycleService;
use App\Services\Transactions\TransactionMutationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(CarbonImmutable::parse('2026-01-20'));
    $this->actor = User::factory()->create();
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2026, 'is_closed' => false]);
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $this->category = Category::create(['user_id' => $this->actor->id, 'name' => 'Plan expense', 'slug' => 'plan-expense', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->plan = RecurringEntry::create([
        'user_id' => $this->actor->id, 'account_id' => $this->account->id, 'category_id' => $this->category->id, 'title' => 'Plan for trash',
        'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'is_active' => true,
        'start_date' => '2026-01-15', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1,
        'end_mode' => 'never', 'expected_amount' => 50, 'auto_create_transaction' => true, 'auto_generate_occurrences' => true,
        'next_occurrence_date' => '2026-05-15', 'notes' => 'Keep all history',
    ]);
    foreach (['pending', 'generated', 'skipped', 'cancelled'] as $index => $status) {
        $row = $this->plan->occurrences()->create(['sequence_number' => $index + 1,
            'expected_date' => '2026-0'.($index + 1).'-15', 'due_date' => '2026-0'.($index + 1).'-15',
            'expected_amount' => 50, 'status' => $status, 'notes' => 'Original notes '.$index]);
        if ($status === 'skipped') {
            $row->forceFill(['not_due' => ['previous_status' => 'pending', 'reason' => 'Not supplied']])->save();
        }
    }
    $row = $this->plan->occurrences()->orderBy('id')->firstOrFail();
    $this->reminder = ReminderDelivery::create(['user_id' => $this->actor->id,
        'remindable_type' => $row->getMorphClass(), 'remindable_id' => $row->id,
        'reminder_type' => 'recurring_advance_3', 'due_date' => '2026-01-15', 'delivery_date' => '2026-01-12', 'notification_kind' => 'recurring_due']);
    $this->actingAs($this->actor);
});

test('trash and restore retain every occurrence state and do not change movements or accounts', function (string $status): void {
    $this->plan->forceFill(['status' => $status, 'is_active' => $status === 'active'])->save();
    $rowsBefore = DB::table('recurring_entry_occurrences')->where('recurring_entry_id', $this->plan->id)->orderBy('id')->get()->toArray();
    $planBefore = $this->plan->fresh()->getAttributes();
    $accountBefore = $this->account->fresh()->getAttributes();
    $reminderBefore = $this->reminder->fresh()->getAttributes();
    $this->delete(route('recurring-entries.destroy', $this->plan->uuid))->assertSessionHasNoErrors();
    $this->assertSoftDeleted($this->plan);
    expect(RecurringEntry::find($this->plan->id))->toBeNull()
        ->and(RecurringEntryOccurrence::where('recurring_entry_id', $this->plan->id)->count())->toBe(0)
        ->and(DB::table('recurring_entry_occurrences')->where('recurring_entry_id', $this->plan->id)->orderBy('id')->get()->toArray())->toEqual($rowsBefore)
        ->and($this->reminder->fresh()->getAttributes())->toBe($reminderBefore);
    $this->getJson(route('transactions.show', ['year' => 2026, 'month' => 1]))->assertSuccessful()
        ->assertJsonPath('deleted_recurring_plans.0.uuid', $this->plan->uuid)
        ->assertJsonPath('deleted_recurring_plans.0.can_restore', true);
    $this->get(route('recurring-entries.show', $this->plan->uuid))->assertNotFound();
    $this->travelTo(CarbonImmutable::parse('2026-02-20'));
    $this->from(route('transactions.show', ['year' => 2026, 'month' => 1]))
        ->patch(route('recurring-entries.restore', $this->plan->uuid))->assertSessionHasNoErrors()
        ->assertRedirect(route('transactions.show', ['year' => 2026, 'month' => 1]));
    $restored = $this->plan->fresh();
    expect($restored->trashed())->toBeFalse()
        ->and(collect($restored->getAttributes())->except(['updated_at', 'deleted_at'])->all())->toBe(collect($planBefore)->except(['updated_at', 'deleted_at'])->all())
        ->and(DB::table('recurring_entry_occurrences')->where('recurring_entry_id', $this->plan->id)->orderBy('id')->get()->toArray())->toEqual($rowsBefore)
        ->and($this->account->fresh()->getAttributes())->toBe($accountBefore)
        ->and(Transaction::withTrashed()->count())->toBe(0);
    $this->patch(route('recurring-entries.restore', $this->plan->uuid))->assertSessionHasNoErrors();
    expect($this->plan->occurrences()->count())->toBe(4)->and(Transaction::withTrashed()->count())->toBe(0);
    $this->getJson(route('transactions.show', ['year' => 2026, 'month' => 1]))->assertJsonPath('deleted_recurring_plans', []);
})->with(['active', 'paused', 'cancelled', 'completed']);

test('trashed plans are absent from operational queries calendar agenda and generation', function (): void {
    app(DeleteRecurringEntryService::class)->delete($this->plan);
    $before = DB::table('recurring_entry_occurrences')->orderBy('id')->get()->toArray();
    $this->getJson(route('recurring-entries.index', ['year' => 2026, 'month' => 1]))->assertSuccessful()
        ->assertJsonPath('recurringEntries', [])
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('monthlyCalendar.days', fn ($days): bool => collect($days)->flatMap(fn ($day) => $day['occurrences'])->isEmpty())->etc());
    $this->get(route('dashboard', ['year' => 2026, 'month' => 1]))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.pending_actions.items', [])
        ->where('dashboard.scheduled_summary.upcoming', [])
        ->where('dashboard.credits_debts.arrears', []));
    $this->getJson(route('transactions.show', ['year' => 2026, 'month' => 1]))->assertSuccessful()->assertJsonPath('planned_occurrences', []);
    app(RecurringEntryLifecycleService::class)->runAutomationPipeline();
    expect(fn () => app(RecurringEntryOccurrenceGeneratorService::class)->generate($this->plan, CarbonImmutable::parse('2027-01-01')))->toThrow(ModelNotFoundException::class);
    expect(DB::table('recurring_entry_occurrences')->orderBy('id')->get()->toArray())->toEqual($before)
        ->and(Transaction::withTrashed()->count())->toBe(0);
});

test('plan trash is visible only to accessible accounts and restoration requires edit access', function (): void {
    app(DeleteRecurringEntryService::class)->delete($this->plan);
    $other = User::factory()->create();
    UserYear::create(['user_id' => $other->id, 'year' => 2026, 'is_closed' => false]);
    $this->actingAs($other)->getJson(route('transactions.show', ['year' => 2026, 'month' => 1]))->assertSuccessful()->assertJsonPath('deleted_recurring_plans', []);
    $this->patch(route('recurring-entries.restore', $this->plan->uuid))->assertNotFound();
    AccountMembership::create(['account_id' => $this->account->id, 'user_id' => $other->id, 'household_id' => null,
        'role' => AccountMembershipRoleEnum::VIEWER, 'status' => 'active', 'permissions' => null,
        'granted_by_user_id' => $this->actor->id, 'source' => 'direct', 'joined_at' => now()]);
    $this->getJson(route('transactions.show', ['year' => 2026, 'month' => 1]))->assertSuccessful()->assertJsonPath('deleted_recurring_plans.0.can_restore', false);
    $this->patch(route('recurring-entries.restore', $this->plan->uuid))->assertSessionHasErrors('entry');
    $this->assertSoftDeleted($this->plan);
});

test('restore retains preserved rows and new economic links without changing money', function (string $link): void {
    app(DeleteRecurringEntryService::class)->delete($this->plan);
    $id = DB::table('recurring_entry_occurrences')->where('recurring_entry_id', $this->plan->id)->orderBy('id')->value('id');
    $transaction = null;
    if ($link === 'matched') {
        $transaction = Transaction::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id,
            'direction' => 'expense', 'currency' => 'EUR', 'amount' => 10, 'transaction_date' => '2026-01-15',
            'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
        DB::table('recurring_entry_occurrences')->where('id', $id)->update(['matched_transaction_id' => $transaction->id]);
    } else {
        $this->plan->manualCosts()->create(['date' => '2026-01-15', 'amount' => 10, 'currency' => 'EUR']);
    }
    $before = DB::table('recurring_entry_occurrences')->orderBy('id')->get()->toArray();
    $accountBefore = $this->account->fresh()->getAttributes();
    $transactionBefore = $transaction?->fresh()->getAttributes();
    $this->patch(route('recurring-entries.restore', $this->plan->uuid))->assertSessionHasNoErrors();
    expect($this->plan->fresh()->trashed())->toBeFalse();
    expect(DB::table('recurring_entry_occurrences')->orderBy('id')->get()->toArray())->toEqual($before)
        ->and($this->account->fresh()->getAttributes())->toBe($accountBefore)
        ->and($transaction?->fresh()->getAttributes())->toBe($transactionBefore);
})->with(['matched', 'manual_cost']);

test('linked transactions stay editable and refunds synchronize historical occurrences while a plan is trashed', function (bool $variable): void {
    $this->plan->forceFill(['is_amount_variable' => $variable])->save();
    $row = $this->plan->occurrences()->orderBy('id')->firstOrFail();
    $transaction = app(RecurringEntryPostingService::class)->post($row, $this->actor);
    $before = $transaction->fresh()->getAttributes();
    $balanceBefore = $this->account->fresh()->getAttributes();
    $rowBefore = $row->fresh()->getAttributes();
    $this->delete(route('recurring-entries.destroy', $this->plan->uuid))->assertSessionHasNoErrors();
    expect($transaction->fresh()->getAttributes())->toBe($before)
        ->and($this->account->fresh()->getAttributes())->toBe($balanceBefore)
        ->and($row->fresh()->getAttributes())->toBe($rowBefore)
        ->and($transaction->fresh()->recurringOccurrence->id)->toBe($row->id)
        ->and($transaction->fresh()->recurringOccurrence->recurringEntry->trashed())->toBeTrue();
    $updated = app(TransactionMutationService::class)->update($this->actor, $transaction->fresh(), [
        'account_id' => $this->account->id, 'category_id' => $this->category->id, 'direction' => 'expense', 'type_key' => 'expense',
        'amount' => 30, 'transaction_date' => '2026-01-15', 'description' => 'Corrected payment', 'notes' => null,
    ]);
    expect($updated->amount)->toBe('30.00')
        ->and($this->account->fresh()->current_balance)->toBe('970.00')
        ->and($row->fresh()->status->value)->toBe($variable ? 'completed' : 'pending')
        ->and($row->fresh()->remainingPaymentCents())->toBe($variable ? 0 : 2000);
    $this->from(route('transactions.show', ['year' => 2026, 'month' => 1]))->post(route('recurring-transactions.refund', $transaction->uuid), ['transaction_date' => '2026-01-20'])->assertSessionHasNoErrors();
    $refund = $transaction->fresh()->refundTransaction;
    expect($refund)->not->toBeNull()
        ->and($refund->trashed())->toBeFalse()
        ->and($refund->amount)->toBe('30.00')
        ->and($this->account->fresh()->current_balance)->toBe('1000.00')
        ->and($row->fresh()->status->value)->toBe('pending')
        ->and($row->fresh()->remainingPaymentCents())->toBe($variable ? 3000 : 5000);
    expect($this->plan->fresh()->trashed())->toBeTrue();
    $history = $row->paymentEvents()->orderBy('id')->get()->map->getAttributes()->all();
    $rowsBeforeRestore = DB::table('recurring_entry_occurrences')->where('recurring_entry_id', $this->plan->id)->orderBy('id')->get()->toArray();
    $moneyBeforeRestore = Transaction::withTrashed()->orderBy('id')->get()->map->getAttributes()->all();
    $balanceBeforeRestore = $this->account->fresh()->getAttributes();
    $this->patch(route('recurring-entries.restore', $this->plan->uuid))->assertSessionHasNoErrors();
    $this->patch(route('recurring-entries.restore', $this->plan->uuid))->assertSessionHasNoErrors();
    expect(DB::table('recurring_entry_occurrences')->where('recurring_entry_id', $this->plan->id)->orderBy('id')->get()->toArray())->toEqual($rowsBeforeRestore)
        ->and(Transaction::withTrashed()->orderBy('id')->get()->map->getAttributes()->all())->toBe($moneyBeforeRestore)
        ->and($this->account->fresh()->getAttributes())->toBe($balanceBeforeRestore)
        ->and($row->paymentEvents()->orderBy('id')->get()->map->getAttributes()->all())->toBe($history)
        ->and($this->plan->occurrences()->count())->toBe(4);
    $this->getJson(route('recurring-entries.show', [$this->plan->uuid, 'highlight' => $row->uuid]))->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) => $json->where('recurringEntry.occurrences', fn ($items): bool => collect($items)->contains(fn ($item): bool => $item['uuid'] === $row->uuid && $item['status'] === 'pending' && (float) $item['remaining_amount'] === ($variable ? 30.0 : 50.0)))->etc());
})->with([false, true]);

test('allocated transaction validation retains historical access and its existing limits', function (): void {
    $row = $this->plan->occurrences()->firstOrFail();
    $transaction = Transaction::create(['user_id' => $this->actor->id, 'account_id' => $this->account->id,
        'direction' => 'expense', 'amount' => 50, 'currency' => 'EUR', 'transaction_date' => '2026-01-15',
        'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);
    $row->paymentAllocations()->create(['transaction_id' => $transaction->id, 'amount_cents' => 5000]);
    $row->synchronizeSettlementState();
    app(DeleteRecurringEntryService::class)->delete($this->plan);
    $data = ['account_id' => $this->account->id, 'category_id' => $this->category->id, 'direction' => 'expense', 'type_key' => 'expense',
        'amount' => 80, 'transaction_date' => '2026-01-15', 'description' => 'Updated allocated payment', 'notes' => null];
    app(TransactionMutationService::class)->update($this->actor, $transaction->fresh(), $data);
    expect($this->account->fresh()->current_balance)->toBe('920.00')
        ->and($transaction->occurrencePaymentAllocations()->firstOrFail()->occurrence->recurringEntry->trashed())->toBeTrue();
    expect(fn () => app(TransactionMutationService::class)->update($this->actor, $transaction->fresh(), [...$data, 'amount' => 30]))->toThrow(ValidationException::class);
    expect($transaction->fresh()->amount)->toBe('80.00')->and($this->account->fresh()->current_balance)->toBe('920.00');
    app(DeleteRecurringEntryService::class)->restore($this->plan->fresh());
    expect($row->fresh()->allocatedPaymentCents())->toBe(5000)->and($this->account->fresh()->current_balance)->toBe('920.00');
});

test('historical refund checks still restrict refunds to the latest converted occurrence', function (): void {
    $rows = $this->plan->occurrences()->orderBy('id')->limit(2)->get();
    $first = app(RecurringEntryPostingService::class)->post($rows[0], $this->actor);
    app(RecurringEntryPostingService::class)->post($rows[1], $this->actor, ['confirm_future_date' => true]);
    app(DeleteRecurringEntryService::class)->delete($this->plan);
    $before = Transaction::withTrashed()->orderBy('id')->get()->map->getAttributes()->all();
    expect(fn () => app(TransactionRefundService::class)->refund($first))->toThrow(ValidationException::class);
    expect(Transaction::withTrashed()->orderBy('id')->get()->map->getAttributes()->all())->toBe($before);
});

test('paid historical links do not make trashed occurrences operational or send notifications', function (): void {
    $row = $this->plan->occurrences()->firstOrFail();
    $transaction = app(RecurringEntryPostingService::class)->post($row, $this->actor);
    $transaction->forceFill(['payment_verification_started_at' => now(), 'payment_verification_due_at' => now(), 'payment_verified_at' => null])->save();
    $row->forceFill(['payment_management_state' => 'pending', 'payment_reminder_date' => today()])->save();
    app(DeleteRecurringEntryService::class)->delete($this->plan);
    $this->get(route('dashboard', ['year' => 2026, 'month' => 1]))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->where('dashboard.pending_actions.items', [])->where('dashboard.scheduled_summary.upcoming', []));
    $this->getJson(route('transactions.show', ['year' => 2026, 'month' => 1]))->assertSuccessful()
        ->assertJson(fn (AssertableJson $json) => $json->where('transactions', fn ($items): bool => collect($items)->contains(fn ($item): bool => $item['uuid'] === $transaction->uuid && $item['is_recurring_transaction']))->etc())
        ->assertJsonPath('planned_occurrences', []);
    $result = app(DailyRecurringReminderService::class)->run();
    expect($result['notified'])->toBe(0)->and(RecurringEntryOccurrence::whereKey($row->id)->exists())->toBeFalse()
        ->and(RecurringEntryOccurrence::withHistoricalPlan()->findOrFail($row->id)->recurringEntry->trashed())->toBeTrue();
});

test('refund undo and payment confirmation keep historical synchronization while the plan remains trashed', function (): void {
    $row = $this->plan->occurrences()->firstOrFail();
    $transaction = app(RecurringEntryPostingService::class)->post($row, $this->actor);
    app(DeleteRecurringEntryService::class)->delete($this->plan);
    $refund = app(TransactionRefundService::class)->refund($transaction->fresh(), ['amount' => 10]);
    expect($row->fresh()->remainingPaymentCents())->toBe(1000)->and($this->account->fresh()->current_balance)->toBe('960.00');
    app(TransactionRefundService::class)->undo($refund);
    expect($row->fresh()->status->value)->toBe('completed')
        ->and($row->fresh()->remainingPaymentCents())->toBe(0)
        ->and($this->account->fresh()->current_balance)->toBe('950.00');
    $transaction->forceFill(['payment_verification_started_at' => now(), 'payment_verification_due_at' => now(), 'payment_verified_at' => null])->save();
    $row->forceFill(['payment_management_state' => 'to_verify', 'payment_resolved_at' => null])->save();
    $moneyBefore = $this->account->fresh()->getAttributes();
    app(AutomaticPaymentVerificationService::class)->confirm($transaction->fresh());
    expect($transaction->fresh()->payment_verified_at)->not->toBeNull()
        ->and($row->fresh()->payment_management_state)->toBe('paid')
        ->and($this->account->fresh()->getAttributes())->toBe($moneyBefore)
        ->and($this->plan->fresh()->trashed())->toBeTrue();
});
