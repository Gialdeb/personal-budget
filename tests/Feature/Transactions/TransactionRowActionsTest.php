<?php

use App\Models\Account;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyPaymentService;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\TransactionRefundService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(now()->setDate(2026, 10, 9)->setTime(12, 0));
});

/** @return array{User, Account, RecurringEntryOccurrence} */
function rowActionFixture(): array
{
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    UserYear::create(['user_id' => $user->id, 'year' => 2026, 'is_closed' => false]);
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $entry = RecurringEntry::create([
        'user_id' => $user->id, 'account_id' => $account->id, 'title' => 'Audit isolated recurring',
        'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active',
        'is_active' => true, 'recurrence_type' => 'monthly', 'recurrence_interval' => 1,
        'start_date' => '2026-10-09', 'end_mode' => 'never', 'expected_amount' => 15,
        'auto_create_transaction' => true,
    ]);
    $occurrence = $entry->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-09', 'due_date' => '2026-10-09', 'expected_amount' => 15, 'status' => 'pending']);

    return [$user, $account, $occurrence];
}

/** @return array<string, mixed> */
function actionRow(User $user, Transaction $transaction): array
{
    return collect(app(MonthlyTransactionSheetService::class)->build($user, 2026, 10)['transactions'])->firstWhere('uuid', $transaction->uuid);
}

test('row capabilities preserve ordinary refund and disable impossible orphan refunds and card settlements', function () {
    [$user, $account] = rowActionFixture();
    $original = userTransaction($user, $account, ['kind' => 'manual']);
    $refund = app(TransactionRefundService::class)->refund($original);
    expect(actionRow($user, $refund)['can_undo_refund'])->toBeTrue();
    $orphan = userTransaction($user, $account, ['kind' => 'refund']);
    expect(actionRow($user, $orphan)['can_undo_refund'])->toBeFalse()
        ->and(actionRow($user, $orphan)['undo_refund_unavailable_reason'])->not->toBeNull();
    $card = userTransaction($user, $account, ['kind' => 'credit_card_settlement']);
    expect(actionRow($user, $card)['can_edit'])->toBeFalse();
});

test('a resettled recurring refund navigates to the occurrence and the backend still rejects undo', function () {
    [$user, $account, $occurrence] = rowActionFixture();
    $original = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    $refund = app(TransactionRefundService::class)->refund($original);
    userTransaction($user, $account, ['kind' => 'manual', 'amount' => 15, 'recurring_entry_occurrence_id' => $occurrence->id]);
    $row = actionRow($user, $refund);
    expect($row['can_undo_refund'])->toBeFalse()
        ->and($row['management_url'])->toBe(route('recurring-entries.show', [$occurrence->recurringEntry->uuid, 'highlight' => $occurrence->uuid]));
    $this->actingAs($user)->delete(route('transactions.undo-refund', [2026, 10, $refund->uuid]))->assertSessionHasErrors('transaction');
    expect($refund->fresh())->not->toBeNull();
});

test('counterparty bank documents expose canonical history instead of generic mutations', function () {
    [$user, $account] = rowActionFixture();
    $party = TrackedItem::create(['user_id' => $user->id, 'name' => 'Audit party', 'slug' => 'audit-party', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $relation = CounterpartyRelation::create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'currency_code' => 'EUR', 'minor_unit' => 2, 'activated_at' => now()]);
    $transaction = userTransaction($user, $account, ['kind' => 'manual']);
    CounterpartyPayment::create(['relation_id' => $relation->id, 'direction' => 'received', 'amount_minor' => 10000, 'economic_date' => '2026-10-09', 'account_id' => $account->id, 'account_uuid' => $account->uuid, 'transaction_uuid' => $transaction->uuid, 'payload_hash' => str_repeat('a', 64), 'transaction_id' => $transaction->id, 'status' => 'recorded', 'idempotency_key' => 'audit-payment']);
    $row = actionRow($user, $transaction);
    expect($row['can_edit'])->toBeFalse()->and($row['can_delete'])->toBeFalse()->and($row['can_refund'])->toBeFalse()
        ->and($row['management_url'])->toBe(route('counterparties.show', $relation->uuid));
    $this->actingAs($user)->get($row['management_url'])->assertSuccessful();
    $this->actingAs($user)->delete(route('transactions.destroy', [2026, 10, $transaction->uuid]))->assertSessionHasErrors('transaction');
    expect($transaction->fresh()->trashed())->toBeFalse();
});

test('automatic confirmation is separate from problem reporting and remains idempotent', function () {
    [$user, $account, $occurrence] = rowActionFixture();
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    expect(actionRow($user, $transaction)['can_confirm_payment'])->toBeTrue();
    $count = Transaction::count();
    $balance = $account->fresh()->current_balance;
    $this->actingAs($user)->post(route('transactions.payment-confirmation', $transaction->uuid))->assertRedirect();
    $this->post(route('transactions.payment-confirmation', $transaction->uuid))->assertRedirect();
    expect(Transaction::count())->toBe($count)->and($account->fresh()->current_balance)->toBe($balance)
        ->and(actionRow($user, $transaction)['can_confirm_payment'])->toBeFalse()
        ->and(actionRow($user, $transaction)['can_report_payment_problem'])->toBeFalse();
});

test('matched automatic postings and manual recurring settlements do not advertise forbidden actions', function () {
    [$user, $account, $occurrence] = rowActionFixture();
    $transaction = app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    $occurrence->update(['matched_transaction_id' => $transaction->id]);
    $row = actionRow($user, $transaction);
    expect($row['can_confirm_payment'])->toBeFalse()->and($row['can_report_payment_problem'])->toBeFalse();
    $manual = userTransaction($user, $account, ['kind' => 'manual', 'recurring_entry_occurrence_id' => $occurrence->id]);
    expect(actionRow($user, $manual)['can_edit'])->toBeFalse();
});

test('settled shared sources navigate to sharing instead of advertising full refund or deletion', function () {
    config(['counterparty_ledger.public_enabled' => true, 'counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $account] = rowActionFixture();
    $party = TrackedItem::create(['user_id' => $user->id, 'name' => 'Settled party', 'slug' => 'settled-party', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $source = userTransaction($user, $account, ['kind' => 'manual', 'amount' => 100, 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    $relation = CounterpartyRelation::where('counterparty_id', $party->id)->firstOrFail();
    $payments = app(CounterpartyPaymentService::class);
    $payments->activate($user, $relation, 'audit-activate');
    $payments->create($user, $relation->fresh(), ['operation_key' => 'audit-settle', 'amount' => '10.00', 'date' => '2026-10-09', 'account_uuid' => $account->uuid, 'direction' => 'received', 'scope' => 'all', 'allow_unallocated' => false]);
    $row = actionRow($user, $source);
    expect($row['can_refund'])->toBeFalse()->and($row['can_delete'])->toBeFalse()
        ->and($row['management_url'])->toBe(route('shared-expenses.index', ['counterparty' => $party->uuid]));
    $this->actingAs($user)->post(route('transactions.refund', [2026, 10, $source->uuid]), [])->assertSessionHasErrors();
    expect($source->fresh()->refundTransaction)->toBeNull();
});

test('transfers with an inaccessible counterpart never advertise mutations even after soft deletion', function () {
    [$user, $account] = rowActionFixture();
    $other = User::factory()->create();
    $otherAccount = userAccount($other);
    $out = userTransaction($user, $account, ['kind' => 'manual', 'is_transfer' => true]);
    $in = userTransaction($other, $otherAccount, ['kind' => 'manual', 'is_transfer' => true, 'direction' => 'income', 'related_transaction_id' => $out->id]);
    $row = actionRow($user, $out);
    expect($row['can_edit'])->toBeFalse()->and($row['can_delete'])->toBeFalse();
    $in->delete();
    $out->delete();
    $row = collect(app(MonthlyTransactionSheetService::class)->build($user, 2026, 10)['deleted_transactions'])->firstWhere('uuid', $out->uuid);
    expect($row['can_restore'])->toBeFalse()->and($row['can_force_delete'])->toBeFalse();
});

// Optional export uses only this test's transient SQLite data for browser QA.
test('isolated row action browser fixture', function () {
    if (! getenv('TRANSACTION_ACTION_FIXTURE')) {
        $this->markTestSkipped('Browser fixture export not requested.');
    }
    [$user, $account, $occurrence] = rowActionFixture();
    app(RecurringEntryPostingService::class)->post($occurrence, metadata: ['automatic_posting' => true]);
    $manual = userTransaction($user, $account, ['kind' => 'manual', 'description' => 'Audit manual allowed']);
    userTransaction($user, $account, ['kind' => 'manual', 'description' => 'Audit editable expense']);
    app(TransactionRefundService::class)->refund($manual);
    userTransaction($user, $account, ['kind' => 'refund', 'description' => 'Audit refund unavailable']);
    $party = TrackedItem::create(['user_id' => $user->id, 'name' => 'Audit party', 'slug' => 'audit-party', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    userTransaction($user, $account, ['kind' => 'manual', 'description' => 'Audit shared source', 'amount' => 200, 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 10000, 'shared_expense_counterparty_share_cents' => 10000]);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    $relation = CounterpartyRelation::where('counterparty_id', $party->id)->firstOrFail();
    app(CounterpartyPaymentService::class)->activate($user, $relation, 'fixture-activate');
    $relation->refresh();
    config(['counterparty_ledger.public_enabled' => true]);
    app(CounterpartyPaymentService::class)->create($user, $relation, [
        'operation_key' => 'fixture-payment', 'amount' => '100.00', 'date' => '2026-10-09',
        'account_uuid' => $account->uuid, 'direction' => 'received', 'scope' => 'all', 'allow_unallocated' => true,
    ]);
    $response = $this->actingAs($user)->get(route('transactions.show', [2026, 10]));
    $response->assertSuccessful();
    file_put_contents(getenv('TRANSACTION_ACTION_FIXTURE'), json_encode($response->viewData('page'), JSON_THROW_ON_ERROR));
    $canonical = $this->get(route('counterparties.show', $relation->uuid));
    $canonical->assertSuccessful();
    file_put_contents(getenv('TRANSACTION_ACTION_FIXTURE').'.canonical', json_encode($canonical->viewData('page'), JSON_THROW_ON_ERROR));
});
