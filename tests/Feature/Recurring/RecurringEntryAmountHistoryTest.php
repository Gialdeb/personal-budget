<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\Recurring\UpdateRecurringOccurrenceAmountService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(CarbonImmutable::parse('2026-12-20 12:00:00'));
    $this->historyUser = User::factory()->create();
    UserYear::query()->create(['user_id' => $this->historyUser->id, 'year' => 2026, 'is_closed' => false]);
    UserYear::query()->create(['user_id' => $this->historyUser->id, 'year' => 2027, 'is_closed' => false]);
    $this->historyAccount = createTestAccount($this->historyUser, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $category = Category::query()->create(['user_id' => $this->historyUser->id, 'name' => 'Subscription', 'slug' => 'subscription', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->historyPayload = ['title' => 'Price history', 'account_id' => $this->historyAccount->id, 'category_id' => $category->id, 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 1], 'start_date' => '2026-12-01', 'end_mode' => 'never', 'expected_amount' => 29.99, 'is_amount_variable' => false, 'auto_generate_occurrences' => true, 'auto_create_transaction' => false, 'is_active' => true];
    $this->actingAs($this->historyUser);
});

test('base amount increases and decreases retain immutable history without duplicate saves', function () {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->historyUser, $this->historyPayload);
    $past = $entry->occurrences()->where('sequence_number', 1)->firstOrFail();
    $pastAttributes = $past->getAttributes();
    $this->patch(route('recurring-entries.update', $entry->uuid), [...$this->historyPayload, 'expected_amount' => 34.99])->assertSessionHasNoErrors();
    $first = $entry->amountChanges()->firstOrFail();
    $firstAttributes = $first->getAttributes();
    expect($first->previous_amount)->toBe('29.99')->and($first->new_amount)->toBe('34.99')
        ->and($first->effective_from->toDateString())->toBe('2027-01-01')
        ->and($first->difference())->toBe(5.0)->and($first->percentage())->toBe(16.67)
        ->and($past->fresh()->getAttributes())->toBe($pastAttributes);
    $january = $entry->occurrences()->where('sequence_number', 2)->firstOrFail();
    expect($january->expected_amount)->toBe('34.99');
    $this->patch(route('recurring-entries.update', $entry->uuid), [...$this->historyPayload, 'expected_amount' => 34.99])->assertSessionHasNoErrors();
    expect($entry->amountChanges()->count())->toBe(1);
    $januaryAttributes = $january->fresh()->getAttributes();
    $this->travelTo(CarbonImmutable::parse('2027-01-02 12:00:00'));
    $this->patch(route('recurring-entries.update', $entry->uuid), $this->historyPayload)->assertSessionHasNoErrors();
    $second = $entry->amountChanges()->get()->last();
    expect($second->previous_amount)->toBe('34.99')->and($second->new_amount)->toBe('29.99')
        ->and($second->effective_from->toDateString())->toBe('2027-02-01')
        ->and($second->percentage())->toBe(-14.29)
        ->and($second->difference())->toBe(-5.0)
        ->and($first->fresh()->getAttributes())->toBe($firstAttributes)
        ->and($january->fresh()->getAttributes())->toBe($januaryAttributes)
        ->and($entry->occurrences()->where('sequence_number', 3)->firstOrFail()->expected_amount)->toBe('29.99')
        ->and($entry->amountChanges()->count())->toBe(2);
    $this->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (Assert $page) => $page
        ->has('recurringEntry.amount_history', 2)
        ->where('recurringEntry.amount_history.0.percentage', 16.67)
        ->where('recurringEntry.amount_history.0.previous_until', '2026-12-31')
        ->where('recurringEntry.amount_history.1.percentage', -14.29));
});

test('variable occurrence overrides do not create price history and survive base changes', function () {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->historyUser, [...$this->historyPayload, 'is_amount_variable' => true]);
    $custom = $entry->occurrences()->where('sequence_number', 2)->firstOrFail();
    app(UpdateRecurringOccurrenceAmountService::class)->update($custom, $this->historyUser, 77);
    $customAttributes = $custom->fresh()->getAttributes();
    expect($entry->amountChanges()->count())->toBe(0);
    $service->update($this->historyUser, $entry->fresh(), ['expected_amount' => 34.99]);
    expect($custom->fresh()->getAttributes())->toBe($customAttributes)
        ->and($entry->amountChanges()->count())->toBe(1)
        ->and($entry->amountChanges()->firstOrFail()->effective_from->toDateString())->toBe('2027-02-01');
});

test('refunded occurrences and their transactions remain unchanged after base price updates', function () {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->historyUser, [...$this->historyPayload, 'auto_create_transaction' => true]);
    $past = $entry->occurrences()->where('sequence_number', 1)->firstOrFail();
    $transaction = $past->convertedTransaction;
    $refund = app(TransactionRefundService::class)->refund($transaction);
    $pastAttributes = $past->fresh()->getAttributes();
    $transactionAttributes = $transaction->fresh()->getAttributes();
    $refundAttributes = $refund->getAttributes();
    $balance = $this->historyAccount->fresh()->current_balance;
    $service->update($this->historyUser, $entry->fresh(), ['expected_amount' => 34.99]);
    expect($past->fresh()->getAttributes())->toBe($pastAttributes)
        ->and($transaction->fresh()->getAttributes())->toBe($transactionAttributes)
        ->and($refund->fresh()->getAttributes())->toBe($refundAttributes)
        ->and($this->historyAccount->fresh()->current_balance)->toBe($balance)
        ->and(Transaction::query()->count())->toBe(2);
});

test('installment base price changes preserve registered and customized distribution amounts', function () {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->historyUser, [...$this->historyPayload, 'entry_type' => 'installment', 'expected_amount' => null, 'total_amount' => 89.97, 'installments_count' => 3, 'end_mode' => 'after_occurrences', 'occurrences_limit' => 3, 'auto_create_transaction' => true]);
    $past = $entry->occurrences()->where('sequence_number', 1)->firstOrFail();
    $pastAttributes = $past->getAttributes();
    $transaction = $past->convertedTransaction;
    $transactionAttributes = $transaction->getAttributes();
    $custom = $entry->occurrences()->where('sequence_number', 2)->firstOrFail();
    $custom->forceFill(['expected_amount' => 77])->save();
    $customAttributes = $custom->getAttributes();
    $service->update($this->historyUser, $entry->fresh(), ['total_amount' => 104.97, 'confirm_installment_recalculation' => true]);
    $service->update($this->historyUser, $entry->fresh(), ['total_amount' => 104.97, 'confirm_installment_recalculation' => true]);
    $change = $entry->amountChanges()->firstOrFail();
    expect($change->previous_amount)->toBe('29.99')->and($change->new_amount)->toBe('34.99')
        ->and($change->effective_from->toDateString())->toBe('2027-02-01')
        ->and($change->percentage())->toBe(16.67)
        ->and($entry->amountChanges()->count())->toBe(1)
        ->and($past->fresh()->getAttributes())->toBe($pastAttributes)
        ->and($transaction->fresh()->getAttributes())->toBe($transactionAttributes)
        ->and($custom->fresh()->getAttributes())->toBe($customAttributes)
        ->and($entry->occurrences()->where('sequence_number', 3)->firstOrFail()->expected_amount)->toBe('34.99');
});

test('metadata edits and initial creation do not add an unnecessary amount history', function () {
    $service = app(RecurringEntryManagementService::class);
    $entry = $service->store($this->historyUser, $this->historyPayload);
    $service->update($this->historyUser, $entry, ['title' => 'New title']);
    expect($entry->amountChanges()->count())->toBe(0);
    $this->get(route('recurring-entries.show', $entry->uuid))->assertInertia(fn (Assert $page) => $page
        ->has('recurringEntry.amount_history', 0));
});
