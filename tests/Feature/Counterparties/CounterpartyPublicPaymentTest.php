<?php

use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\CounterpartyAllocationEntry;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyOperation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CounterpartySyncIssue;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyLedgerService;
use App\Services\Counterparties\CounterpartyPaymentService;
use App\Services\Counterparties\CounterpartyProjectionService;
use App\Services\Counterparties\CounterpartyReconciliationService;
use App\Services\Counterparties\CounterpartyViewService;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\CreditDebts\CreditDebtItemService;
use App\Services\CreditDebts\CreditDebtPaymentService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['features.credits_debts.enabled' => true, 'counterparty_ledger.public_enabled' => true, 'counterparty_ledger.shadow_manual_enabled' => false, 'counterparty_ledger.shadow_shared_enabled' => false]);
    $this->withoutMiddleware(PreventRequestForgery::class);
});

/** @return array{User, TrackedItem, Account, CounterpartyRelation, CreditDebtItem} */
function publicCounterpartyFixture(int $shared = 5000, string $manual = '200.00', string $type = 'credit'): array
{
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $party = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Emanuela', 'slug' => fake()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $account = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $item = CreditDebtItem::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'type' => $type, 'description' => 'Credito iniziale', 'total_amount' => $manual, 'currency_code' => 'EUR', 'due_date' => null, 'created_at' => '2026-09-01']);
    $item->forceFill(['created_at' => '2026-09-01 00:00:00'])->save();
    if ($shared !== 0) {
        SharedExpenseLedgerEntry::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'entry_date' => '2026-10-01', 'title' => 'Cena', 'currency' => 'EUR', 'total_cents' => abs($shared) * 2, 'my_share_cents' => abs($shared), 'counterparty_share_cents' => abs($shared), 'paid_by' => $shared > 0 ? 'self' : 'counterparty', 'status' => 'recorded']);
    }
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    $relation = CounterpartyRelation::query()->where('user_id', $user->id)->firstOrFail();
    app(CounterpartyPaymentService::class)->activate($user, $relation, 'activate');

    return [$user, $party, $account, $relation->fresh(), $item];
}

function commonPaymentInput(Account $account, string $amount = '100.00', string $key = 'payment', string $direction = 'received'): array
{
    return ['operation_key' => $key, 'amount' => $amount, 'date' => '2026-10-14', 'account_uuid' => $account->uuid, 'direction' => $direction, 'note' => null, 'scope' => 'all', 'allow_unallocated' => false];
}

function commonCreatedPayment(User $user, CounterpartyRelation $relation, array $data): CounterpartyPayment
{
    $result = app(CounterpartyPaymentService::class)->create($user, $relation, $data);

    return CounterpartyPayment::query()->where('uuid', $result['payment_uuid'])->firstOrFail();
}

test('one unified receipt settles FIFO and updates both the ledger and manual document without legacy copies', function () {
    [$user, $party, $account, $relation, $item] = publicCounterpartyFixture();
    $before = CounterpartyLedgerEntry::count();
    $payment = commonCreatedPayment($user, $relation, commonPaymentInput($account));
    expect(Transaction::count())->toBe(1)->and(CounterpartyLedgerEntry::count())->toBe($before + 1)->and(CreditDebtPayment::count())->toBe(0)->and(SharedExpenseSettlement::count())->toBe(0)
        ->and($payment->transaction->direction->value)->toBe('income')->and($item->fresh()->paidAmount())->toBe('100.00')->and($item->fresh()->remainingAmount())->toBe('100.00')
        ->and(app(CounterpartyBalanceService::class)->balance($user, $party, 'EUR')['total_cents'])->toBe(15000)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
});

test('FIFO splits ninety between forty and one hundred using stable economic dates', function () {
    [$user, $party, $account, $relation, $first] = publicCounterpartyFixture(0, '40.00');
    $second = app(CreditDebtItemService::class)->create($user, ['counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Credito B', 'total_amount' => '100.00', 'currency_code' => 'EUR']);
    $payment = commonCreatedPayment($user, $relation, commonPaymentInput($account, '90.00'));
    expect($first->fresh()->remainingAmount())->toBe('0.00')->and($second->fresh()->remainingAmount())->toBe('50.00')
        ->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(5000)->and($payment->allocationEntries()->count())->toBe(2);
    app(CounterpartyPaymentService::class)->cancel($user, $relation, $payment, ['operation_key' => 'cancel']);
    expect($first->fresh()->remainingAmount())->toBe('40.00')->and($second->fresh()->remainingAmount())->toBe('100.00')->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(14000);
});

test('outgoing payments produce one expense and settle negative obligations', function () {
    [$user, $party, $account, $relation, $item] = publicCounterpartyFixture(-5000, '200.00', 'debit');
    $payment = commonCreatedPayment($user, $relation, commonPaymentInput($account, '100.00', direction: 'sent'));
    expect($payment->transaction->direction->value)->toBe('expense')->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(-15000)
        ->and($item->fresh()->remainingAmount())->toBe('100.00')->and($account->fresh()->current_balance)->toBe('900.00');
});

test('explicit direction can pay a compatible debt even when the net balance is positive', function () {
    [$user, , $account, $relation] = publicCounterpartyFixture(-5000);
    commonCreatedPayment($user, $relation, commonPaymentInput($account, '50.00', direction: 'sent'));
    expect(app(CounterpartyProjectionService::class)->balance($relation))->toBe(20000);
});

test('custom allocations are validated and can target shared claims first', function () {
    [$user, , $account, $relation, $item] = publicCounterpartyFixture();
    $shared = $relation->obligations()->where('source_type', 'shared_ledger_share')->first();
    $manual = $relation->obligations()->where('source_type', 'credit_debt_item')->first();
    $payment = commonCreatedPayment($user, $relation, [...commonPaymentInput($account), 'allocations' => [['obligation_uuid' => $shared->uuid, 'amount' => '50.00'], ['obligation_uuid' => $manual->uuid, 'amount' => '50.00']]]);
    expect($item->fresh()->remainingAmount())->toBe('150.00')->and(app(CounterpartyProjectionService::class)->remaining($shared))->toBe(0)->and($payment->allocationEntries()->count())->toBe(2);
});

test('overpayments overallocations and foreign obligations are rejected atomically', function (string $scenario) {
    [$user, , $account, $relation] = publicCounterpartyFixture();
    $shared = $relation->obligations()->where('source_type', 'shared_ledger_share')->first();
    $data = commonPaymentInput($account);
    if ($scenario === 'excess') {
        $data['amount'] = '300.00';
    }
    if ($scenario === 'overallocate') {
        $data['allocations'] = [['obligation_uuid' => $shared->uuid, 'amount' => '100.00']];
    }
    if ($scenario === 'foreign') {
        $data['allocations'] = [['obligation_uuid' => (string) Str::uuid(), 'amount' => '100.00']];
    }
    expect(fn () => app(CounterpartyPaymentService::class)->create($user, $relation, $data))->toThrow(ValidationException::class);
    expect(Transaction::count())->toBe(0)->and(CounterpartyPayment::count())->toBe(0)->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(25000);
})->with(['excess', 'overallocate', 'foreign']);

test('explicit unallocated portions remain visible and reconciled without advancing beyond capacity', function () {
    [$user, , $account, $relation, $item] = publicCounterpartyFixture();
    $manual = $relation->obligations()->where('source_type', 'credit_debt_item')->first();
    $payment = commonCreatedPayment($user, $relation, [...commonPaymentInput($account), 'allow_unallocated' => true, 'allocations' => [['obligation_uuid' => $manual->uuid, 'amount' => '60.00']]]);
    expect(app(CounterpartyProjectionService::class)->unallocated($payment))->toBe(4000)->and($item->fresh()->remainingAmount())->toBe('140.00')
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
});

test('compensation is explicit append only and has no bank effect', function () {
    [$user, , $account, $relation, $item] = publicCounterpartyFixture(-5000);
    $positive = $relation->obligations()->where('source_type', 'credit_debt_item')->first();
    $negative = $relation->obligations()->where('source_type', 'shared_ledger_share')->first();
    $result = app(CounterpartyPaymentService::class)->compensate($user, $relation, ['operation_key' => 'comp', 'positive_uuid' => $positive->uuid, 'negative_uuid' => $negative->uuid, 'amount' => '50.00', 'date' => '2026-10-14']);
    expect(Transaction::count())->toBe(0)->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(15000)->and($item->fresh()->paidAmount())->toBe('0.00')->and($item->fresh()->compensatedAmount())->toBe('50.00')
        ->and($item->fresh()->remainingAmount())->toBe('150.00')->and(app(CounterpartyProjectionService::class)->remaining($negative))->toBe(0)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
    app(CounterpartyPaymentService::class)->reverseCompensation($user, $relation, CounterpartyLedgerEntry::query()->where('uuid', $result['compensation_uuid'])->first(), ['operation_key' => 'reverse-comp']);
    expect($item->fresh()->remainingAmount())->toBe('200.00')->and(app(CounterpartyProjectionService::class)->remaining($negative))->toBe(5000)->and(Transaction::count())->toBe(0);
});

test('administrative cancellation reopens residuals preserves history and soft deletes its owned Transaction', function () {
    [$user, , $account, $relation, $item] = publicCounterpartyFixture();
    $payment = commonCreatedPayment($user, $relation, commonPaymentInput($account));
    $service = app(CounterpartyPaymentService::class);
    $first = $service->cancel($user, $relation, $payment, ['operation_key' => 'cancel']);
    expect($service->cancel($user, $relation, $payment, ['operation_key' => 'cancel']))->toBe($first)->and($item->fresh()->remainingAmount())->toBe('200.00')
        ->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(25000)->and(CounterpartyPayment::count())->toBe(1)->and(Transaction::withTrashed()->count())->toBe(1)
        ->and(Transaction::withTrashed()->first()->trashed())->toBeTrue()->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
});

test('payment revisions create a replacement and keep the old immutable document', function () {
    [$user, , $account, $relation, $item] = publicCounterpartyFixture();
    $payment = commonCreatedPayment($user, $relation, commonPaymentInput($account));
    $otherAccount = createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $data = [...commonPaymentInput($otherAccount, '120.00', 'revision'), 'date' => '2026-10-15'];
    $result = app(CounterpartyPaymentService::class)->revise($user, $relation, $payment, $data);
    $replacement = CounterpartyPayment::query()->where('uuid', $result['payment_uuid'])->first();
    expect($replacement->revision_of_id)->toBe($payment->id)->and($payment->fresh()->amount_minor)->toBe(10000)->and($item->fresh()->remainingAmount())->toBe('80.00')
        ->and(Transaction::count())->toBe(1)->and(Transaction::withTrashed()->count())->toBe(2)->and($replacement->account_id)->toBe($otherAccount->id)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
});

test('allocation only revisions never produce bank or balance effects', function () {
    [$user, , $account, $relation, $item] = publicCounterpartyFixture();
    $payment = commonCreatedPayment($user, $relation, commonPaymentInput($account));
    $shared = $relation->obligations()->where('source_type', 'shared_ledger_share')->first();
    $manual = $relation->obligations()->where('source_type', 'credit_debt_item')->first();
    $entries = CounterpartyLedgerEntry::count();
    app(CounterpartyPaymentService::class)->reallocate($user, $relation, $payment, ['operation_key' => 'reallocate', 'allocations' => [['obligation_uuid' => $shared->uuid, 'amount' => '50.00'], ['obligation_uuid' => $manual->uuid, 'amount' => '50.00']]]);
    expect(CounterpartyLedgerEntry::count())->toBe($entries)->and(Transaction::count())->toBe(1)->and($item->fresh()->remainingAmount())->toBe('150.00')
        ->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(15000)->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
});

test('real partial returns create an opposite Transaction and restore the correct obligations', function () {
    [$user, , $account, $relation, $item] = publicCounterpartyFixture();
    $payment = commonCreatedPayment($user, $relation, commonPaymentInput($account));
    $result = app(CounterpartyPaymentService::class)->returnMoney($user, $relation, $payment, commonPaymentInput($account, '50.00', 'return'));
    $return = CounterpartyPayment::query()->where('uuid', $result['payment_uuid'])->first();
    expect($return->return_of_id)->toBe($payment->id)->and($return->transaction->direction->value)->toBe('expense')->and(Transaction::count())->toBe(2)
        ->and($item->fresh()->remainingAmount())->toBe('150.00')->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(20000)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
    expect(fn () => app(CounterpartyPaymentService::class)->cancel($user, $relation, $payment, ['operation_key' => 'invalid-cancel']))->toThrow(ValidationException::class);
    app(CounterpartyPaymentService::class)->cancel($user, $relation, $return, ['operation_key' => 'cancel-return']);
    expect($item->fresh()->remainingAmount())->toBe('100.00')->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(15000)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
});

test('idempotency retries never create a second bank effect and collisions are rejected', function () {
    [$user, , $account, $relation] = publicCounterpartyFixture();
    $service = app(CounterpartyPaymentService::class);
    $data = commonPaymentInput($account);
    $first = $service->create($user, $relation, $data);
    expect($service->create($user, $relation, $data))->toBe($first);
    expect(fn () => $service->create($user, $relation, [...$data, 'amount' => '120.00']))->toThrow(ValidationException::class);
    expect(Transaction::count())->toBe(1)->and(CounterpartyPayment::count())->toBe(1);
});

test('a second payment cannot consume a residual already used by the first', function () {
    [$user, , $account, $relation] = publicCounterpartyFixture(0, '140.00');
    commonCreatedPayment($user, $relation, commonPaymentInput($account, '90.00', 'first'));
    expect(fn () => app(CounterpartyPaymentService::class)->create($user, $relation, commonPaymentInput($account, '90.00', 'second')))->toThrow(ValidationException::class);
    expect(Transaction::count())->toBe(1)->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(5000);
});

test('owned payment Transactions reject generic edits deletion restore and commercial refund', function () {
    [$user, , $account, $relation] = publicCounterpartyFixture();
    $payment = commonCreatedPayment($user, $relation, commonPaymentInput($account));
    $transaction = $payment->transaction;
    expect(fn () => $transaction->update(['amount' => '110.00']))->toThrow(ValidationException::class)
        ->and(fn () => app(TransactionMutationService::class)->destroy($user, $transaction))->toThrow(ValidationException::class)
        ->and(fn () => app(TransactionRefundService::class)->refund($transaction))->toThrow(ValidationException::class);
    app(CounterpartyPaymentService::class)->cancel($user, $relation, $payment, ['operation_key' => 'cancel']);
    expect(fn () => $transaction->fresh()->restore())->toThrow(ValidationException::class);
});

test('insufficient bank funds roll back ledger allocations payment and operation together', function () {
    [$user, , $account, $relation, $item] = publicCounterpartyFixture(0, '200.00', 'debit');
    $account->update(['opening_balance' => 0, 'current_balance' => 0]);
    expect(fn () => app(CounterpartyPaymentService::class)->create($user, $relation, commonPaymentInput($account, direction: 'sent')))->toThrow(ValidationException::class);
    expect(Transaction::count())->toBe(0)->and(CounterpartyPayment::count())->toBe(0)->and(CounterpartyAllocationEntry::count())->toBe(0)->and(CounterpartyOperation::count())->toBe(1)->and($item->fresh()->remainingAmount())->toBe('200.00');
});

test('activated relations prohibit legacy payment writers and use ledger even if rollout flag is switched off', function () {
    [$user, $party, $account, $relation, $item] = publicCounterpartyFixture();
    commonCreatedPayment($user, $relation, commonPaymentInput($account));
    expect(fn () => app(CreditDebtPaymentService::class)->create($user, $item, ['account_id' => $account->id, 'amount' => '10.00', 'paid_at' => '2026-10-14']))->toThrow(ValidationException::class)
        ->and(fn () => app(SharedExpenseSettlementService::class)->record($user, $party, ['amount' => '10.00', 'settled_at' => '2026-10-14', 'account_uuid' => $account->uuid, 'reference' => null]))->toThrow(ValidationException::class);
    config(['counterparty_ledger.public_enabled' => false]);
    expect(app(CounterpartyBalanceService::class)->balance($user, $party, 'EUR')['total_cents'])->toBe(15000)->and($item->fresh()->remainingAmount())->toBe('100.00');
});

test('non reconciled relations cannot activate and continue to use legacy', function () {
    [$user, $party, $account, $relation] = publicCounterpartyFixture();
    $relation->update(['activated_at' => null]);
    config(['counterparty_ledger.shadow_manual_enabled' => false]);
    CreditDebtItem::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Uncaptured', 'total_amount' => '40.00', 'currency_code' => 'EUR']);
    expect(fn () => app(CounterpartyPaymentService::class)->activate($user, $relation, 'new-activation'))->toThrow(ValidationException::class);
    expect(app(CounterpartyBalanceService::class)->balance($user, $party, 'EUR')['total_cents'])->toBe(29000);
});

test('HTTP lifecycle enforces ownership validation and publishes the new page', function () {
    [$user, , $account, $relation] = publicCounterpartyFixture();
    $this->actingAs($user)->postJson(route('counterparties.payments.store', $relation), commonPaymentInput($account))->assertSuccessful();
    $this->get(route('counterparties.show', $relation))->assertSuccessful()->assertInertia(fn ($page) => $page->component('counterparties/Show')->where('relation.balance_minor', '15000'));
    $this->postJson(route('counterparties.payments.store', $relation), [...commonPaymentInput($account, key: 'invalid'), 'amount' => 12.5])->assertUnprocessable();
    $this->actingAs(User::factory()->create())->postJson(route('counterparties.payments.store', $relation), commonPaymentInput($account, key: 'foreign'))->assertForbidden();
});

test('active source edits cannot destroy allocated claims and legitimate additions remain authoritative with shadow flags off', function () {
    [$user, $party, $account, $relation, $item] = publicCounterpartyFixture();
    commonCreatedPayment($user, $relation, commonPaymentInput($account));
    expect(fn () => $item->update(['total_amount' => '50.00']))->toThrow(ValidationException::class)->and(fn () => $item->fresh()->delete())->toThrow(ValidationException::class);
    $item = $item->fresh();
    $item->update(['total_amount' => '250.00']);
    expect($item->fresh()->remainingAmount())->toBe('150.00')->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
    $legacy = legacyCreditDebtItem($user, ['type' => 'credit', 'description' => 'Pregresso non associato', 'total_amount' => '40.00', 'currency_code' => 'EUR']);
    $uuid = $legacy->uuid;
    $legacy->update(['reference_id' => $party->id]);
    expect($legacy->fresh()->uuid)->toBe($uuid)->and(CounterpartyPayment::count())->toBe(1)->and(Transaction::count())->toBe(1)->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(24000)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
});

test('historical imported manual payments can be cancelled without altering the immutable legacy payment document', function () {
    [$user, $party, $account, $relation, $item] = publicCounterpartyFixture(0);
    $relation->update(['activated_at' => null]);
    $legacy = app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '30.00', 'paid_at' => '2026-10-01', 'account_id' => $account->id]);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    app(CounterpartyPaymentService::class)->activate($user, $relation->fresh(), 'reactivate');
    $imported = CounterpartyPayment::query()->where('legacy_source_identity', $legacy->uuid)->firstOrFail();
    expect($item->fresh()->remainingAmount())->toBe('170.00');
    app(CounterpartyPaymentService::class)->cancel($user, $relation->fresh(), $imported, ['operation_key' => 'cancel-imported']);
    expect($legacy->fresh()->amount)->toBe('30.00')->and($legacy->fresh()->uuid)->toBe($legacy->uuid)->and($item->fresh()->remainingAmount())->toBe('200.00')
        ->and($legacy->transaction()->withTrashed()->first()->trashed())->toBeTrue()->and(app(CounterpartyReconciliationService::class)->inspect($relation->fresh())['readiness'])->toBe('reconciled');
});

test('commercial expense refunds cannot reduce paid shared quotas through create edit or restore', function () {
    [$user, $party, $account, $relation] = publicCounterpartyFixture(0);
    $expense = Transaction::query()->create(['user_id' => $user->id, 'account_id' => $account->id, 'transaction_date' => '2026-10-01', 'amount' => '100.00', 'currency' => 'EUR', 'direction' => 'expense', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed', 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
    $shared = $relation->obligations()->where('source_type', 'shared_transaction_share')->firstOrFail();
    commonCreatedPayment($user, $relation, [...commonPaymentInput($account, '30.00'), 'allocations' => [['obligation_uuid' => $shared->uuid, 'amount' => '30.00']]]);
    expect(fn () => app(TransactionRefundService::class)->refund($expense->fresh(), ['amount' => '90.00']))->toThrow(ValidationException::class);
    $refund = app(TransactionRefundService::class)->refund($expense->fresh(), ['amount' => '20.00']);
    expect(app(CounterpartyProjectionService::class)->remaining($shared))->toBe(1000)->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
    expect(fn () => $refund->update(['amount' => '90.00']))->toThrow(ValidationException::class);
    $refund->fresh()->delete();
    commonCreatedPayment($user, $relation, [...commonPaymentInput($account, '20.00', 'remaining-shared'), 'allocations' => [['obligation_uuid' => $shared->uuid, 'amount' => '20.00']]]);
    expect(fn () => $refund->fresh()->restore())->toThrow(ValidationException::class)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
});

test('historical unallocated shared settlement cancellation and reallocation do not double count cash', function () {
    [$user, $party, $account, $relation] = publicCounterpartyFixture();
    $relation->update(['activated_at' => null]);
    $legacy = app(SharedExpenseSettlementService::class)->record($user, $party, ['amount' => '20.00', 'settled_at' => '2026-10-01', 'account_uuid' => $account->uuid, 'reference' => null]);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    app(CounterpartyPaymentService::class)->activate($user, $relation->fresh(), 'reactivate-shared');
    $payment = CounterpartyPayment::query()->where('legacy_source_identity', $legacy->uuid)->firstOrFail();
    expect(app(CounterpartyProjectionService::class)->balance($relation))->toBe(23000);
    $shared = $relation->obligations()->where('source_type', 'shared_ledger_share')->firstOrFail();
    app(CounterpartyPaymentService::class)->reallocate($user, $relation->fresh(), $payment, ['operation_key' => 'shared-allocation', 'scope' => 'shared', 'allocations' => [['obligation_uuid' => $shared->uuid, 'amount' => '20.00']]]);
    expect(app(CounterpartyBalanceService::class)->balance($user, $party, 'EUR')['total_cents'])->toBe(23000)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
    app(CounterpartyPaymentService::class)->cancel($user, $relation->fresh(), $payment, ['operation_key' => 'shared-cancel']);
    expect(app(CounterpartyProjectionService::class)->balance($relation))->toBe(25000)->and(SharedExpenseSettlement::count())->toBe(1)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
});

test('a blocked legacy currency precision does not break either rollout page or allow activation', function () {
    [$user, $party] = publicCounterpartyFixture(0);
    $relation = app(CounterpartyLedgerService::class)->relation($user, $party, 'JPY');
    CreditDebtItem::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Frazione legacy non valida', 'total_amount' => '10.55', 'currency_code' => 'JPY']);
    $dto = app(CounterpartyViewService::class)->show($user, $relation);
    expect($dto['relation']['amounts_available'])->toBeFalse()->and($dto['relation']['can_activate'])->toBeFalse();
    $this->actingAs($user)->get(route('credits-debts.index'))->assertSuccessful();
});

test('public operation history rejects direct database mutation and deletion', function () {
    publicCounterpartyFixture();
    $operation = CounterpartyOperation::query()->firstOrFail();
    expect(fn () => DB::transaction(fn () => DB::table('counterparty_operations')->where('id', $operation->id)->update(['kind' => 'changed'])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('counterparty_operations')->where('id', $operation->id)->delete()))->toThrow(QueryException::class)
        ->and($operation->fresh()->kind)->toBe('activate');
});

test('shared account permissions are rechecked before cancelling or revising a bank payment', function () {
    [$user, , , $relation, $item] = publicCounterpartyFixture(0);
    $owner = User::factory()->create(['base_currency_code' => 'EUR']);
    $account = createTestAccount($owner, ['opening_balance' => 1000, 'current_balance' => 1000]);
    $membership = AccountMembership::query()->create(['account_id' => $account->id, 'user_id' => $user->id, 'role' => 'editor', 'status' => 'active', 'source' => 'direct', 'granted_by_user_id' => $owner->id]);
    $payment = commonCreatedPayment($user, $relation, commonPaymentInput($account));
    expect($payment->transaction->user_id)->toBe($owner->id);
    $membership->update(['role' => 'viewer']);
    expect(fn () => app(CounterpartyPaymentService::class)->cancel($user, $relation, $payment, ['operation_key' => 'revoked-access']))->toThrow(ValidationException::class)
        ->and($payment->transaction->fresh()->trashed())->toBeFalse()->and($item->fresh()->remainingAmount())->toBe('100.00');
});

test('rollout flag off preserves legacy HTTP writers for inactive relations without public economic events', function () {
    [$user, $party, $account, $relation, $item] = publicCounterpartyFixture();
    $relation->update(['activated_at' => null]);
    config(['counterparty_ledger.public_enabled' => false]);
    $ledgerCount = CounterpartyLedgerEntry::count();
    $this->actingAs($user)->postJson(route('credits-debts.payments.store', $item), ['account_uuid' => $account->uuid, 'amount' => '30.00', 'paid_at' => '2026-10-14'])->assertSuccessful();
    $this->postJson(route('shared-expenses.settlements.store', $party->uuid), ['account_uuid' => $account->uuid, 'amount' => '20.00', 'settled_at' => '2026-10-14'])->assertRedirect();
    $this->postJson(route('counterparties.payments.store', $relation), commonPaymentInput($account))->assertNotFound();
    $this->get(route('counterparties.show', $relation))->assertNotFound();
    expect(CounterpartyPayment::count())->toBe(0)->and(CreditDebtPayment::count())->toBe(1)->and(SharedExpenseSettlement::count())->toBe(1)->and(Transaction::count())->toBe(2)
        ->and(CounterpartyLedgerEntry::count())->toBe($ledgerCount)->and($item->fresh()->remainingAmount())->toBe('170.00')
        ->and(app(CounterpartyBalanceService::class)->balance($user, $party, 'EUR')['total_cents'])->toBe(20000);
    $this->get(route('credits-debts.index'))->assertInertia(fn ($page) => $page->where('commonRelations', []));
});

test('all inactive readiness states refuse common payments while preserving legacy behavior', function (string $state) {
    [$user, $party, $account, $relation, $item] = publicCounterpartyFixture();
    $relation->update(['activated_at' => null]);
    if ($state === 'shadowing') {
        CreditDebtItem::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Uncaptured', 'total_amount' => '40.00', 'currency_code' => 'EUR']);
    } elseif ($state === 'blocked') {
        CounterpartySyncIssue::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'currency_code' => 'EUR', 'source_type' => 'credit_debt_item', 'source_identity' => $item->uuid, 'message' => 'Diagnostic fixture']);
    }
    expect(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe($state);
    $before = CounterpartyLedgerEntry::count();
    $this->actingAs($user)->postJson(route('counterparties.payments.store', $relation), commonPaymentInput($account))->assertUnprocessable()->assertJsonValidationErrors('relation');
    $this->get(route('counterparties.show', $relation))->assertInertia(fn ($page) => $page->where('relation.can_manage', false)->where('relation.can_activate', $state === 'reconciled'));
    expect(CounterpartyPayment::count())->toBe(0)->and(Transaction::count())->toBe(0)->and(CounterpartyLedgerEntry::count())->toBe($before);
    $this->postJson(route('credits-debts.payments.store', $item), ['account_uuid' => $account->uuid, 'amount' => '10.00', 'paid_at' => '2026-10-14'])->assertSuccessful();
    expect(CreditDebtPayment::count())->toBe(1)->and(CounterpartyPayment::count())->toBe(0);
})->with(['shadowing', 'blocked', 'reconciled']);

test('not migrated counterparties have no common endpoint or automatic migration on legacy payment', function () {
    config(['counterparty_ledger.public_enabled' => false]);
    $user = User::factory()->create();
    $party = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Emanuela', 'slug' => fake()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    $account = createTestAccount($user);
    $item = CreditDebtItem::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Legacy', 'total_amount' => '200.00', 'currency_code' => 'EUR']);
    expect(app(CounterpartyReconciliationService::class)->compare($user, $party, 'EUR')['readiness'])->toBe('not_migrated');
    $this->actingAs($user)->postJson(route('counterparties.payments.store', Str::uuid()), commonPaymentInput($account))->assertNotFound();
    $this->postJson(route('credits-debts.payments.store', $item), ['account_uuid' => $account->uuid, 'amount' => '10.00', 'paid_at' => '2026-10-14'])->assertSuccessful();
    expect(CounterpartyRelation::count())->toBe(0)->and(CounterpartyPayment::count())->toBe(0)->and(CounterpartyLedgerEntry::count())->toBe(0);
});

test('active HTTP relations never fall back to legacy after rollout disablement or diagnostic anomalies', function (string $state) {
    [$user, $party, $account, $relation, $item] = publicCounterpartyFixture();
    commonCreatedPayment($user, $relation, commonPaymentInput($account));
    if ($state === 'flag_off') {
        config(['counterparty_ledger.public_enabled' => false]);
    } else {
        CounterpartySyncIssue::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'currency_code' => 'EUR', 'source_type' => 'credit_debt_item', 'source_identity' => $item->uuid, 'message' => 'Diagnostic fixture']);
    }
    $this->actingAs($user)->get(route('counterparties.show', $relation))->assertInertia(fn ($page) => $page->where('relation.can_manage', false)->where('relation.balance_minor', '15000')->has('payments', 1));
    $response = $this->postJson(route('counterparties.payments.store', $relation), commonPaymentInput($account, '10.00', 'second'));
    $state === 'flag_off' ? $response->assertNotFound() : $response->assertUnprocessable();
    $this->postJson(route('credits-debts.payments.store', $item), ['account_uuid' => $account->uuid, 'amount' => '10.00', 'paid_at' => '2026-10-14'])->assertUnprocessable();
    $this->postJson(route('shared-expenses.settlements.store', $party->uuid), ['account_uuid' => $account->uuid, 'amount' => '10.00', 'settled_at' => '2026-10-14'])->assertUnprocessable();
    expect(CounterpartyPayment::count())->toBe(1)->and(CreditDebtPayment::count())->toBe(0)->and(SharedExpenseSettlement::count())->toBe(0)->and(Transaction::count())->toBe(1)
        ->and(app(CounterpartyBalanceService::class)->balance($user, $party, 'EUR')['total_cents'])->toBe(15000);
})->with(['flag_off', 'diagnostic']);

test('both HTTP entry scopes create only one common payment and one economic effect', function (string $scope) {
    [$user, $party, $account, $relation, $item] = publicCounterpartyFixture();
    $before = CounterpartyLedgerEntry::count();
    $this->actingAs($user)->postJson(route('counterparties.payments.store', $relation), [...commonPaymentInput($account, '20.00'), 'scope' => $scope])->assertSuccessful();
    expect(CounterpartyPayment::count())->toBe(1)->and(Transaction::count())->toBe(1)->and(CreditDebtPayment::count())->toBe(0)->and(SharedExpenseSettlement::count())->toBe(0)
        ->and(CounterpartyLedgerEntry::count())->toBe($before + 1)->and(CounterpartyAllocationEntry::count())->toBe(1)
        ->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(23000);
    $this->postJson(route('credits-debts.payments.store', $item), ['account_uuid' => $account->uuid, 'amount' => '10.00', 'paid_at' => '2026-10-14'])->assertUnprocessable();
    $this->postJson(route('shared-expenses.settlements.store', $party->uuid), ['account_uuid' => $account->uuid, 'amount' => '10.00', 'settled_at' => '2026-10-14'])->assertUnprocessable();
})->with(['all', 'shared']);

test('imported legacy documents cannot be updated or deleted through old HTTP endpoints after activation', function () {
    [$user, $party, $account, $relation, $item] = publicCounterpartyFixture();
    $relation->update(['activated_at' => null]);
    $manual = app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '30.00', 'paid_at' => '2026-10-01', 'account_id' => $account->id]);
    $settlement = app(SharedExpenseSettlementService::class)->record($user, $party, ['amount' => '20.00', 'settled_at' => '2026-10-01', 'account_uuid' => $account->uuid, 'reference' => null]);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    app(CounterpartyPaymentService::class)->activate($user, $relation->fresh(), 'activate-historical-http');
    $before = [CounterpartyLedgerEntry::count(), CounterpartyAllocationEntry::count(), $account->fresh()->current_balance];
    $this->actingAs($user)->deleteJson(route('credits-debts.payments.destroy', ['creditDebtItem' => $item->uuid, 'payment' => $manual->uuid]))->assertUnprocessable();
    $this->patchJson(route('shared-expenses.settlements.update', $settlement->uuid), ['account_uuid' => $account->uuid, 'amount' => '10.00', 'settled_at' => '2026-10-14'])->assertUnprocessable();
    $this->deleteJson(route('shared-expenses.settlements.destroy', $settlement->uuid))->assertUnprocessable();
    expect([CounterpartyLedgerEntry::count(), CounterpartyAllocationEntry::count(), $account->fresh()->current_balance])->toBe($before)
        ->and($manual->fresh()->amount)->toBe('30.00')->and($settlement->fresh()->amount_cents)->toBe(2000)->and(Transaction::count())->toBe(2)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
});

test('person history separates document totals from claims and preserves balance across filters', function () {
    [$user, , $account, $relation] = publicCounterpartyFixture(800, '200.00');
    $claim = $relation->obligations()->where('source_type', 'shared_ledger_share')->firstOrFail();
    commonCreatedPayment($user, $relation, [...commonPaymentInput($account, '4.00'), 'allocations' => [['obligation_uuid' => $claim->uuid, 'amount' => '4.00']]]);
    $this->actingAs($user)->get(route('counterparties.show', [$relation, 'kind' => 'shared', 'state' => 'partial', 'period' => '2026-10']))
        ->assertSuccessful()->assertInertia(fn ($page) => $page
        ->where('relation.balance_minor', '20400')->where('activity.total', 1)
        ->where('activity.data.0.expense_total_minor', '1600')
        ->where('activity.data.0.my_share_minor', '800')
        ->where('activity.data.0.counterparty_share_minor', '800')
        ->where('activity.data.0.paid_minor', '400')
        ->where('activity.data.0.remaining_minor', '400'));
    $this->get(route('counterparties.show', [$relation, 'kind' => 'payment']))->assertInertia(fn ($page) => $page
        ->where('relation.balance_minor', '20400')->where('activity.total', 1)->where('activity.data.0.kind', 'payment'));
});

test('person history paginates a stable filtered timeline without truncating allocation choices', function () {
    [$user, $party, , $relation] = publicCounterpartyFixture(0);
    foreach (range(1, 25) as $index) {
        app(CreditDebtItemService::class)->create($user, ['counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Prestito '.$index, 'total_amount' => '10.00', 'currency_code' => 'EUR']);
    }
    $this->actingAs($user)->get(route('counterparties.show', [$relation, 'kind' => 'manual', 'search' => 'Prestito', 'scope' => 'shared']))
        ->assertInertia(fn ($page) => $page->has('activity.data', 20)->has('obligations', 26)->where('activity.total', 25)->where('activity.last_page', 2)
            ->where('activity.next_page_url', fn ($url) => str_contains($url, 'kind=manual') && str_contains($url, 'search=Prestito') && str_contains($url, 'scope=shared')));
    $this->get(route('counterparties.show', [$relation, 'kind' => 'manual', 'search' => 'Prestito', 'page' => 2]))
        ->assertInertia(fn ($page) => $page->has('activity.data', 5)->where('activity.current_page', 2)->where('activity.from', 21)->where('activity.to', 25));
});

test('realistic person presentation covers three months payments partial claims and offsets', function () {
    [$user, $party, $account, $relation] = publicCounterpartyFixture(0);
    $user->update(['locale' => 'it']);
    $party->update(['name' => 'Mario Rossi']);
    app(CreditDebtItemService::class)->create($user, ['counterparty_id' => $party->id, 'type' => 'debit', 'description' => 'Anticipo vacanza', 'total_amount' => '80.00', 'currency_code' => 'EUR']);
    foreach (range(1, 12) as $index) {
        SharedExpenseLedgerEntry::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id,
            'entry_date' => $index <= 8 ? '2026-10-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT) : ($index <= 10 ? '2026-09-12' : '2026-08-10'),
            'title' => ['Pulizie casa', 'Spesa al supermercato', 'Cena insieme', 'Bolletta luce'][($index - 1) % 4].' '.$index,
            'currency' => 'EUR', 'total_cents' => 1600, 'my_share_cents' => 800, 'counterparty_share_cents' => 800,
            'paid_by' => $index % 3 === 0 ? 'counterparty' : 'self', 'status' => 'recorded']);
    }
    $positive = $relation->obligations()->where('source_type', 'shared_ledger_share')->where('recognized_minor', '>', 0)->orderBy('economic_date')->get();
    $negative = $relation->obligations()->where('source_type', 'shared_ledger_share')->where('recognized_minor', '<', 0)->firstOrFail();
    foreach (['4.00', '2.00', '8.00'] as $index => $amount) {
        commonCreatedPayment($user, $relation, [...commonPaymentInput($account, $amount, 'visual-received-'.$index), 'allocations' => [['obligation_uuid' => $positive[$index]->uuid, 'amount' => $amount]]]);
    }
    commonCreatedPayment($user, $relation, [...commonPaymentInput($account, '8.00', 'visual-sent', 'sent'), 'allocations' => [['obligation_uuid' => $negative->uuid, 'amount' => '8.00']]]);
    $manualPositive = $relation->obligations()->where('source_type', 'credit_debt_item')->where('recognized_minor', '>', 0)->firstOrFail();
    $manualNegative = $relation->obligations()->where('source_type', 'credit_debt_item')->where('recognized_minor', '<', 0)->firstOrFail();
    foreach (range(1, 3) as $index) {
        app(CounterpartyPaymentService::class)->compensate($user, $relation, ['operation_key' => 'visual-offset-'.$index, 'positive_uuid' => $manualPositive->uuid, 'negative_uuid' => $manualNegative->uuid, 'amount' => '1.00', 'date' => '2026-10-14']);
    }
    $dto = app(CounterpartyViewService::class)->show($user, $relation);
    expect($dto['activity']->total())->toBe(21)->and($dto['historyPeriods'])->toContain('2026-10', '2026-09', '2026-08')
        ->and(collect($dto['obligations'])->where('domain', 'shared')->count())->toBe(12)
        ->and(collect($dto['obligations'])->where('remaining_minor', '400')->count())->toBe(1)
        ->and(collect($dto['obligations'])->where('remaining_minor', '600')->count())->toBe(1);
    $this->actingAs($user);
    $responses = ['detail' => route('counterparties.show', $relation), 'detail-page2' => route('counterparties.show', [$relation, 'page' => 2])];
    foreach (['pending', 'settled', 'all'] as $effect) {
        $responses['shared-'.$effect] = route('shared-expenses.index', ['year' => 2026, 'month' => 10, 'effect' => $effect]);
    }
    foreach ($responses as $name => $url) {
        $response = $this->get($url)->assertSuccessful();
        if ($directory = getenv('SOAMCO_PRESENTATION_FIXTURE_DIR')) {
            file_put_contents($directory.'/'.$name.'.html', $response->getContent());
            file_put_contents($directory.'/'.$name.'.json', json_encode($response->viewData('page'), JSON_THROW_ON_ERROR));
        }
    }
});

test('an existing shared payment can be revised after a later opposite expense reduces the net balance', function (string $direction, string $scope, string $amount) {
    $received = $direction === 'received';
    [$user, $party, $account, $relation] = publicCounterpartyFixture($received ? 10000 : -10000, '1.00', $received ? 'debit' : 'credit');
    $payment = commonCreatedPayment($user, $relation, [...commonPaymentInput($account, '80.00', direction: $direction), 'scope' => $scope]);
    SharedExpenseLedgerEntry::query()->create([
        'user_id' => $user->id, 'counterparty_id' => $party->id,
        'entry_date' => '2026-10-15', 'title' => 'Later opposite expense', 'currency' => 'EUR',
        'total_cents' => 12000, 'my_share_cents' => 6000, 'counterparty_share_cents' => 6000,
        'paid_by' => $received ? 'counterparty' : 'self', 'status' => 'recorded',
    ]);
    $balanceBefore = app(CounterpartyProjectionService::class)->balance($relation);
    $this->actingAs($user)->patchJson(route('counterparties.payments.update', [$relation, $payment]), [
        ...commonPaymentInput($account, $amount, 'revise-after-opposite-expense', $direction),
        'scope' => $scope, 'note' => 'Corrected payment note',
    ])->assertSuccessful();
    $replacement = CounterpartyPayment::query()->where('revision_of_id', $payment->id)->sole();
    $difference = (8000 - (int) round((float) $amount * 100)) * ($received ? 1 : -1);
    expect(app(CounterpartyProjectionService::class)->effectivePayment($payment))->toBe(0)
        ->and($replacement->amount_minor)->toBe((int) round((float) $amount * 100))
        ->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe($balanceBefore + $difference)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled')
        ->and(Transaction::query()->count())->toBe(1);
})->with(['received', 'sent'])->with(['shared', 'all'])->with(['80.00', '60.00']);

test('revision allowance cannot increase an advance or move it to another scope or direction', function (string $amount, string $scope, string $direction) {
    [$user, $party, $account, $relation] = publicCounterpartyFixture(10000, '1.00', 'debit');
    $payment = commonCreatedPayment($user, $relation, commonPaymentInput($account, '80.00'));
    SharedExpenseLedgerEntry::query()->create([
        'user_id' => $user->id, 'counterparty_id' => $party->id,
        'entry_date' => '2026-10-15', 'title' => 'Later opposite expense', 'currency' => 'EUR',
        'total_cents' => 12000, 'my_share_cents' => 6000, 'counterparty_share_cents' => 6000,
        'paid_by' => 'counterparty', 'status' => 'recorded',
    ]);
    $balanceBefore = app(CounterpartyProjectionService::class)->balance($relation);
    $accountBefore = $account->fresh()->current_balance;
    $entriesBefore = CounterpartyLedgerEntry::query()->count();
    $this->actingAs($user)->patchJson(route('counterparties.payments.update', [$relation, $payment]), [
        ...commonPaymentInput($account, $amount, 'invalid-revision', $direction), 'scope' => $scope,
    ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    expect(app(CounterpartyProjectionService::class)->effectivePayment($payment))->toBe(8000)
        ->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe($balanceBefore)
        ->and($account->fresh()->current_balance)->toBe($accountBefore)
        ->and(CounterpartyLedgerEntry::query()->count())->toBe($entriesBefore)
        ->and(Transaction::withTrashed()->count())->toBe(1)
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['readiness'])->toBe('reconciled');
})->with([
    'larger amount' => ['81.00', 'all', 'received'],
    'different scope' => ['60.00', 'shared', 'received'],
    'different direction' => ['80.00', 'all', 'sent'],
]);
