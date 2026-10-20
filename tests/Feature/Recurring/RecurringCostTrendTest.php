<?php

use App\Models\RecurringEntry;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 20));
    $this->trendUser = User::factory()->create(['locale' => 'it']);
    $account = createTestAccount($this->trendUser);
    $this->trendEntry = RecurringEntry::query()->create([
        'user_id' => $this->trendUser->id, 'account_id' => $account->id,
        'title' => 'Luce variabile', 'direction' => 'expense', 'currency' => 'EUR',
        'entry_type' => 'recurring', 'status' => 'active', 'is_active' => true,
        'is_amount_variable' => true, 'start_date' => '2024-01-01',
        'recurrence_type' => 'monthly', 'recurrence_interval' => 1,
        'end_mode' => 'never', 'expected_amount' => 999,
        'auto_create_transaction' => false,
    ]);
    $this->trendTransactions = collect();
    foreach (['2024-01-10' => 80, '2026-10-10' => 145] as $date => $amount) {
        $occurrence = $this->trendEntry->occurrences()->create([
            'sequence_number' => $this->trendTransactions->count() + 1,
            'expected_date' => $date, 'due_date' => $date,
            'expected_amount' => $amount, 'status' => 'generated',
        ]);
        $transaction = Transaction::query()->create([
            'user_id' => $this->trendUser->id, 'account_id' => $account->id,
            'direction' => 'expense', 'amount' => $amount, 'currency' => 'EUR',
            'transaction_date' => $date, 'kind' => 'manual',
            'source_type' => 'manual', 'status' => 'confirmed',
            'recurring_entry_occurrence_id' => $occurrence->id,
        ]);
        $occurrence->update(['converted_transaction_id' => $transaction->id]);
        $this->trendTransactions->push($transaction);
    }
    $this->trendEntry->occurrences()->create([
        'sequence_number' => 3, 'expected_date' => '2026-10-25',
        'due_date' => '2026-10-25', 'expected_amount' => 500, 'status' => 'pending',
    ]);
    $this->actingAs($this->trendUser);
});

test('variable recurring trend uses actual settled transactions across the whole history', function () {
    $this->getJson(route('recurring-entries.show', $this->trendEntry->uuid))
        ->assertSuccessful()
        ->assertJsonCount(2, 'recurringEntry.cost_trend')
        ->assertJsonPath('recurringEntry.cost_trend.0.date', '2024-01-10')
        ->assertJsonPath('recurringEntry.cost_trend.0.amount', 80)
        ->assertJsonPath('recurringEntry.cost_trend.1.amount', 145)
        ->assertJsonPath('recurringEntry.cost_trend.1.transaction_uuid', $this->trendTransactions[1]->uuid);
});

test('cost trend exposes fixed recurring payments and excludes installments', function (string $type, bool $variable) {
    $this->trendEntry->update(['entry_type' => $type, 'is_amount_variable' => $variable]);
    $this->getJson(route('recurring-entries.show', $this->trendEntry->uuid))
        ->assertSuccessful()->assertJsonCount($type === 'recurring' ? 2 : 0, 'recurringEntry.cost_trend');
})->with(['fixed recurring' => ['recurring', false], 'installments' => ['installment', true]]);

test('cost trend excludes unavailable unsettled and unconfirmed payments', function (string $state) {
    $transaction = $this->trendTransactions[1];
    if ($state === 'deleted') {
        $transaction->delete();
    } elseif ($state === 'unconfirmed') {
        $transaction->update(['status' => 'draft']);
    } elseif ($state === 'partial') {
        $transaction->update(['amount' => 20]);
    } else {
        $this->trendEntry->occurrences()->where('sequence_number', 2)->update(['status' => 'skipped']);
    }
    $this->getJson(route('recurring-entries.show', $this->trendEntry->uuid))
        ->assertSuccessful()->assertJsonCount(1, 'recurringEntry.cost_trend')
        ->assertJsonPath('recurringEntry.cost_trend.0.transaction_uuid', $this->trendTransactions[0]->uuid);
})->with(['deleted', 'unconfirmed', 'partial', 'skipped']);

test('cost trend supports allocated payments and keeps resource authorization', function () {
    foreach ($this->trendTransactions as $transaction) {
        $occurrence = $this->trendEntry->occurrences()->where('converted_transaction_id', $transaction->id)->firstOrFail();
        $occurrence->update(['converted_transaction_id' => null]);
        $transaction->update(['recurring_entry_occurrence_id' => null]);
        $occurrence->paymentAllocations()->create(['transaction_id' => $transaction->id, 'amount_cents' => (int) round((float) $transaction->amount * 100)]);
    }
    $this->getJson(route('recurring-entries.show', $this->trendEntry->uuid))
        ->assertSuccessful()->assertJsonCount(2, 'recurringEntry.cost_trend');
    $this->actingAs(User::factory()->create())
        ->getJson(route('recurring-entries.show', $this->trendEntry->uuid))->assertNotFound();
});
