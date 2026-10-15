<?php

use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyPaymentService;
use App\Services\CreditDebts\CreditDebtPaymentService;
use App\Services\Dashboard\MonthlyTransactionSheetService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\SharedExpenses\SharedExpenseSettlementService;

beforeEach(function () {
    config(['counterparty_ledger.shadow_manual_enabled' => false, 'counterparty_ledger.shadow_shared_enabled' => false]);
});

test('register distinguishes an incoming refund from both ordinary income and its refunded original', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $ordinary = userTransaction($user, $account, ['direction' => 'income', 'description' => 'Rimborso', 'transaction_date' => '2026-10-01']);
    $original = userTransaction($user, $account, ['transaction_date' => '2026-10-02']);
    $refund = app(TransactionRefundService::class)->refund($original, ['transaction_date' => '2026-10-03']);
    $rows = collect(app(MonthlyTransactionSheetService::class)->build($user, 2026, 10)['transactions'])->keyBy('uuid');

    expect($rows[$ordinary->uuid]['is_reimbursement'])->toBeFalse()
        ->and($rows[$ordinary->uuid]['reimbursement_kind'])->toBeNull()
        ->and($rows[$original->uuid]['is_refunded'])->toBeTrue()
        ->and($rows[$original->uuid]['is_reimbursement'])->toBeFalse()
        ->and($rows[$refund->uuid]['direction'])->toBe('income')
        ->and($rows[$refund->uuid]['is_refunded'])->toBeFalse()
        ->and($rows[$refund->uuid]['is_reimbursement'])->toBeTrue()
        ->and($rows[$refund->uuid]['reimbursement_kind'])->toBe('transaction_refund');
});

test('register identifies shared reimbursements through the settlement relation', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $party = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Mario', 'slug' => fake()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    userTransaction($user, $account, ['shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 0, 'shared_expense_counterparty_share_cents' => 10000]);
    $settlement = app(SharedExpenseSettlementService::class)->record($user, $party, ['amount' => '40.00', 'settled_at' => today()->toDateString(), 'account_uuid' => $account->uuid, 'category_uuid' => null, 'reference' => null, 'notes' => null]);
    $row = collect(app(MonthlyTransactionSheetService::class)->build($user, today()->year, today()->month)['transactions'])->firstWhere('uuid', $settlement->transaction->uuid);

    expect($row['direction'])->toBe('income')->and($row['is_reimbursement'])->toBeTrue()->and($row['reimbursement_kind'])->toBe('shared_expense_settlement');
});

test('register identifies native counterparty payments and returns without treating their original claim as a refund', function () {
    config(['counterparty_ledger.public_enabled' => true]);
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $party = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Mario', 'slug' => fake()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    CreditDebtItem::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Prestito', 'total_amount' => '100.00', 'currency_code' => 'EUR']);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    $relation = CounterpartyRelation::query()->where('user_id', $user->id)->firstOrFail();
    $service = app(CounterpartyPaymentService::class);
    $service->activate($user, $relation, 'presentation-activate');
    $result = $service->create($user, $relation, ['operation_key' => 'presentation-payment', 'amount' => '40.00', 'date' => today()->toDateString(), 'account_uuid' => $account->uuid, 'direction' => 'received', 'scope' => 'all', 'allow_unallocated' => false]);
    $payment = CounterpartyPayment::query()->where('uuid', $result['payment_uuid'])->firstOrFail();
    $service->returnMoney($user, $relation, $payment, ['operation_key' => 'presentation-return', 'amount' => '10.00', 'date' => today()->toDateString(), 'account_uuid' => $account->uuid]);
    $returned = CounterpartyPayment::query()->where('return_of_id', $payment->id)->firstOrFail();
    $rows = collect(app(MonthlyTransactionSheetService::class)->build($user, today()->year, today()->month)['transactions'])->keyBy('uuid');

    expect($rows[$payment->transaction_uuid]['is_reimbursement'])->toBeTrue()->and($rows[$payment->transaction_uuid]['reimbursement_kind'])->toBe('counterparty_payment')
        ->and($rows[$returned->transaction_uuid]['is_reimbursement'])->toBeTrue()->and($rows[$returned->transaction_uuid]['reimbursement_kind'])->toBe('counterparty_return');
});

test('register identifies credit repayments through their payment relation', function () {
    config(['features.credits_debts.enabled' => true]);
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = userAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $party = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Mario', 'slug' => fake()->uuid(), 'is_active' => true, 'settings' => ['economic_subject' => true]]);
    $item = CreditDebtItem::query()->create(['user_id' => $user->id, 'reference_id' => $party->id, 'counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Prestito', 'total_amount' => '100.00', 'currency_code' => 'EUR']);
    $payment = app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '40.00', 'paid_at' => today()->toDateString(), 'account_id' => $account->id]);
    $row = collect(app(MonthlyTransactionSheetService::class)->build($user, today()->year, today()->month)['transactions'])->firstWhere('uuid', $payment->transaction->uuid);

    expect($row['direction'])->toBe('income')->and($row['is_reimbursement'])->toBeTrue()->and($row['reimbursement_kind'])->toBe('credit_debt_payment');
});
