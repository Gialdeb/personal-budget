<?php

use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(now()->setDate(2026, 10, 15));
});

function focusOccurrence(User $user, string $date, string $type): RecurringEntryOccurrence
{
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000, 'opening_balance_date' => '2026-01-01']);
    $entry = RecurringEntry::query()->create([
        'user_id' => $user->id, 'account_id' => $account->id, 'title' => 'Focus payment',
        'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => $type,
        'status' => 'active', 'is_active' => true, 'start_date' => $date,
        'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'end_mode' => 'after_occurrences',
        'occurrences_limit' => 1, 'installments_count' => 1, 'expected_amount' => 50, 'total_amount' => 50,
        'is_amount_variable' => false, 'auto_create_transaction' => false,
    ]);

    return $entry->occurrences()->create(['sequence_number' => 1, 'expected_date' => $date, 'due_date' => $date, 'expected_amount' => 50, 'status' => 'pending']);
}

test('conversion redirects directly to its persisted transaction and effective period without duplication', function (string $date, string $type) {
    $user = User::factory()->create();
    $occurrence = focusOccurrence($user, '2026-10-10', $type);
    $endpoint = route('recurring-entries.occurrences.convert', [$occurrence->recurringEntry->uuid, $occurrence->uuid]);
    $response = $this->actingAs($user)->post($endpoint, ['transaction_date' => $date])->assertSessionHasNoErrors();
    $transaction = $occurrence->fresh()->convertedTransaction;
    expect($transaction)->not->toBeNull()->and($transaction->uuid)->not->toBe($occurrence->uuid);
    $destination = route('transactions.show', ['year' => $transaction->transaction_date->year, 'month' => $transaction->transaction_date->month, 'highlight' => $transaction->uuid]);
    $response->assertRedirect($destination);
    $this->get($destination)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('year', 2026)->where('month', $transaction->transaction_date->month)
        ->where('monthlySheet.transactions', fn ($rows) => collect($rows)->contains('uuid', $transaction->uuid)));
    $this->post($endpoint)->assertRedirect($destination);
    expect(Transaction::count())->toBe(1);
})->with([['2026-10-10', 'recurring'], ['2026-09-10', 'recurring'], ['2026-10-10', 'installment'], ['2026-09-10', 'installment']]);

test('an accessible focus selects the actual month while preserving its query across normalization', function () {
    $user = User::factory()->create();
    $occurrence = focusOccurrence($user, '2026-09-10', 'recurring');
    $this->actingAs($user)->post(route('recurring-entries.occurrences.convert', [$occurrence->recurringEntry->uuid, $occurrence->uuid]));
    $transaction = $occurrence->fresh()->convertedTransaction;
    $this->get(route('transactions.show', ['year' => 2026, 'month' => 10, 'highlight' => $transaction->uuid]))
        ->assertRedirect(route('transactions.show', ['highlight' => $transaction->uuid, 'year' => 2026, 'month' => 9]));
    $this->actingAs(User::factory()->create())->get(route('transactions.show', ['year' => 2026, 'month' => 10, 'highlight' => $transaction->uuid]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('month', 10)->where('monthlySheet.transactions', []));
});
