<?php

use App\Enums\CounterpartyObligationSourceEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\CounterpartyAllocationEntry;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CounterpartySourceLink;
use App\Models\CounterpartySyncIssue;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Counterparties\CounterpartyBackfillService;
use App\Services\Counterparties\CounterpartyLedgerService;
use App\Services\Counterparties\CounterpartyProjectionService;
use App\Services\Counterparties\CounterpartyReconciliationService;
use App\Services\Counterparties\SharedCounterpartyShadowService;
use App\Services\CreditDebts\CreditDebtItemService;
use App\Services\CreditDebts\CreditDebtPaymentService;
use App\Services\Recurring\DeleteRecurringEntryService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\SharedExpenses\SharedExpenseOccurrencePostingService;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Services\Transactions\TransactionMutationService;
use App\Support\MoneyAmount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['features.credits_debts.enabled' => true, 'counterparty_ledger.shadow_manual_enabled' => false, 'counterparty_ledger.shadow_shared_enabled' => true]);
});

/** @return array{User, TrackedItem, Account} */
function sharedLedgerDomain(): array
{
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $party = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Emanuela', 'slug' => fake()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);

    return [$user, $party, createTestAccount($user, ['opening_balance' => 1000, 'current_balance' => 1000])];
}

function sharedLedgerTransaction(User $user, TrackedItem $party, Account $account, array $attributes = []): Transaction
{
    return Transaction::query()->create(['user_id' => $account->user_id, 'account_id' => $account->id, 'transaction_date' => '2026-10-01', 'amount' => '100.00', 'currency' => 'EUR', 'direction' => 'expense', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed', 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000, ...$attributes]);
}

function sharedLedgerExternal(User $user, TrackedItem $party, array $attributes = []): SharedExpenseLedgerEntry
{
    return SharedExpenseLedgerEntry::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'entry_date' => '2026-10-01', 'title' => 'Cena', 'currency' => 'EUR', 'total_cents' => 10000, 'paid_by' => 'counterparty', 'my_share_cents' => 5000, 'counterparty_share_cents' => 5000, 'status' => 'recorded', ...$attributes]);
}

/** @return array<string, mixed> */
function sharedConversionPayload(Account $account, TrackedItem $party, string $payer): array
{
    $category = Category::query()->firstOrCreate(['user_id' => $account->user_id, 'slug' => 'shared-test'], ['name' => 'Shared test', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true]);

    return ['account_id' => $account->id, 'category_id' => $category->id, 'type_key' => 'expense', 'amount' => '100.00', 'transaction_date' => '2026-10-01', 'description' => 'Cena convertita', 'notes' => null, 'is_shared_expense' => true, 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => $payer, 'shared_expense_my_share' => '50.00', 'shared_expense_counterparty_share' => '50.00'];
}

function sharedRelationReport(User $user, TrackedItem $party): array
{
    return app(CounterpartyReconciliationService::class)->compare($user, $party, 'EUR');
}

function sharedLegacySettlement(User $user, TrackedItem $party, Account $account, string $direction = 'counterparty_to_me', int $cents = 2000): SharedExpenseSettlement
{
    $transaction = Transaction::query()->create(['user_id' => $account->user_id, 'account_id' => $account->id, 'transaction_date' => '2026-10-03', 'amount' => MoneyAmount::decimal($cents), 'currency' => 'EUR', 'direction' => $direction === 'counterparty_to_me' ? 'income' : 'expense', 'kind' => 'manual', 'source_type' => 'manual', 'status' => 'confirmed']);

    return SharedExpenseSettlement::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'amount_cents' => $cents, 'direction' => $direction, 'settled_at' => '2026-10-03', 'transaction_id' => $transaction->id]);
}

test('shared shadow recognizes only the economic share in both directions', function (string $payer, int $expected) {
    [$user, $party, $account] = sharedLedgerDomain();
    $payer === 'self' ? sharedLedgerTransaction($user, $party, $account) : sharedLedgerExternal($user, $party);
    $report = sharedRelationReport($user, $party);
    expect(CounterpartyObligation::count())->toBe(1)->and($report['ledger_balance_minor'])->toBe($expected)->and($report['delta_minor'])->toBe(0)->and($report['readiness'])->toBe('reconciled')->and(CounterpartyPayment::count())->toBe(0);
})->with([['self', 5000], ['counterparty', -5000]]);

test('predicted expenses create no obligation until confirmation and can be unconfirmed again', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $transaction = sharedLedgerTransaction($user, $party, $account, ['status' => 'draft']);
    expect(CounterpartyObligation::count())->toBe(0)->and(CounterpartyLedgerEntry::count())->toBe(0);
    $transaction->update(['status' => 'confirmed']);
    expect(CounterpartyObligation::count())->toBe(1)->and(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(5000);
    $transaction->update(['status' => 'draft']);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(0);
    $transaction->update(['status' => 'confirmed']);
    expect(CounterpartyObligation::count())->toBe(1)->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0);
});

test('shared share and amount changes append a full reversal and a new recognition', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $transaction = sharedLedgerTransaction($user, $party, $account);
    $uuid = CounterpartyObligation::query()->first()->uuid;
    $transaction->update(['shared_expense_my_share_cents' => 7000, 'shared_expense_counterparty_share_cents' => 3000]);
    expect(CounterpartyLedgerEntry::query()->orderBy('id')->pluck('signed_minor')->all())->toBe([5000, -5000, 3000])
        ->and(CounterpartyObligation::query()->first()->uuid)->toBe($uuid)->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0);
    $transaction->update(['amount' => '200.00', 'shared_expense_my_share_cents' => 14000, 'shared_expense_counterparty_share_cents' => 6000]);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(6000)->and(CounterpartyLedgerEntry::query()->orderBy('id')->pluck('signed_minor')->all())->toBe([5000, -5000, 3000, -3000, 6000]);
});

test('commercial refund revises the expense and never creates a counterparty payment', function (string $amount, int $expected) {
    [$user, $party, $account] = sharedLedgerDomain();
    $transaction = sharedLedgerTransaction($user, $party, $account);
    $refund = app(TransactionRefundService::class)->refund($transaction, ['amount' => $amount]);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe($expected)->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0)
        ->and(CounterpartyPayment::count())->toBe(0)->and(CounterpartyObligation::count())->toBe(1);
    app(TransactionRefundService::class)->undo($refund);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(5000)->and(CounterpartyPayment::count())->toBe(0);
})->with([['40.00', 3000], ['100.00', 0]]);

test('shared delete and restore keep identity and historical revisions', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $transaction = sharedLedgerTransaction($user, $party, $account);
    $transaction->delete();
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(0)->and(CounterpartyObligation::count())->toBe(1);
    $transaction->restore();
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(5000)->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0);
    $transaction->forceDelete();
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(0);
});

test('external shared edits cancellation and sign changes preserve one obligation', function () {
    [$user, $party] = sharedLedgerDomain();
    $source = sharedLedgerExternal($user, $party);
    $source->update(['my_share_cents' => 3000, 'counterparty_share_cents' => 7000]);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(-3000);
    $source->update(['paid_by' => 'self']);
    $obligation = CounterpartyObligation::query()->first();
    expect(app(CounterpartyProjectionService::class)->obligation($obligation)['direction_sign'])->toBe(1)
        ->and(app(CounterpartyProjectionService::class)->reconcile($obligation->relation)['consistent'])->toBeTrue();
    $source->delete();
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(0)->and(CounterpartyObligation::count())->toBe(1);
});

test('both technical conversions preserve the same economic identity and obligation', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $source = sharedLedgerTransaction($user, $party, $account);
    $obligation = CounterpartyObligation::query()->first();
    $originalUuid = $obligation->uuid;
    $identity = $obligation->economic_identity;
    $mutations = app(TransactionMutationService::class);
    $external = $mutations->update($user, $source, sharedConversionPayload($account, $party, 'counterparty'));
    expect($external)->toBeInstanceOf(SharedExpenseLedgerEntry::class)->and(CounterpartyObligation::count())->toBe(1)
        ->and(CounterpartyObligation::query()->first()->uuid)->toBe($originalUuid)->and(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(-5000)
        ->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0);
    $transaction = $mutations->updateSharedLedger($user, $external, sharedConversionPayload($account, $party, 'self'));
    expect($transaction)->toBeInstanceOf(Transaction::class)->and(CounterpartyObligation::count())->toBe(1)
        ->and(CounterpartyObligation::query()->first()->economic_identity)->toBe($identity)->and(CounterpartySourceLink::count())->toBe(3)
        ->and(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(5000)->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0);
    $count = CounterpartyLedgerEntry::count();
    app(SharedCounterpartyShadowService::class)->changed($transaction);
    expect(CounterpartyLedgerEntry::count())->toBe($count);
});

test('two distinct shared facts do not collide and retries do not duplicate', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $first = sharedLedgerTransaction($user, $party, $account);
    $second = sharedLedgerTransaction($user, $party, $account);
    app(SharedCounterpartyShadowService::class)->changed($first);
    app(SharedCounterpartyShadowService::class)->changed($second);
    expect(CounterpartyObligation::count())->toBe(2)->and(CounterpartyObligation::query()->pluck('economic_identity')->unique()->count())->toBe(2)
        ->and(CounterpartyLedgerEntry::count())->toBe(2)->and(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(10000);
});

test('settlement shadow records one unallocated payment of the appropriate sign', function (string $direction, int $expected) {
    [$user, $party, $account] = sharedLedgerDomain();
    $direction === 'counterparty_to_me' ? sharedLedgerTransaction($user, $party, $account) : sharedLedgerExternal($user, $party);
    $settlement = sharedLegacySettlement($user, $party, $account, $direction);
    app(SharedCounterpartyShadowService::class)->changed($settlement);
    $report = sharedRelationReport($user, $party);
    expect(CounterpartyPayment::count())->toBe(1)->and(CounterpartyAllocationEntry::count())->toBe(0)->and($report['ledger_balance_minor'])->toBe($expected)
        ->and($report['delta_minor'])->toBe(0)->and($report['readiness'])->toBe('reconciled')->and($report['unallocated'][0]['allocation_status'])->toBe('unknown_legacy')
        ->and(CounterpartyObligation::count())->toBe(1)->and(Transaction::count())->toBe($direction === 'counterparty_to_me' ? 2 : 1);
})->with([['counterparty_to_me', 3000], ['me_to_counterparty', -3000]]);

test('settlement lifecycle revision and cancellation preserve one payment and bank history', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    $service = app(SharedExpenseSettlementService::class);
    $settlement = $service->record($user, $party, ['amount' => '20.00', 'settled_at' => '2026-10-03', 'account_uuid' => $account->uuid, 'reference' => null]);
    $paymentUuid = CounterpartyPayment::query()->first()->uuid;
    $service->update($user, $settlement, ['amount' => '10.00', 'settled_at' => '2026-10-04', 'account_uuid' => $account->uuid, 'reference' => null]);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(4000)->and(CounterpartyPayment::count())->toBe(1);
    $service->delete($user, $settlement);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(5000)->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0)
        ->and(CounterpartyPayment::query()->first()->uuid)->toBe($paymentUuid)->and(Transaction::withTrashed()->count())->toBe(2)
        ->and(CounterpartyLedgerEntry::query()->where('event_type', 'reversal')->count())->toBeGreaterThan(0);
});

test('historical settlement with partial commercial refund imports its net effective payment unallocated', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    $settlement = sharedLegacySettlement($user, $party, $account, cents: 4000);
    Transaction::query()->create(['user_id' => $user->id, 'account_id' => $account->id, 'transaction_date' => '2026-10-04', 'amount' => '10.00', 'currency' => 'EUR', 'direction' => 'expense', 'kind' => 'refund', 'source_type' => 'manual', 'status' => 'confirmed', 'refunded_transaction_id' => $settlement->transaction_id]);
    $before = Transaction::count();
    $report = app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true)['reconciliation'];
    expect($report['ledger_balance_minor'])->toBe(2000)->and($report['delta_minor'])->toBe(0)->and($report['payments'][0]['effective_minor'])->toBe(3000)
        ->and($report['payments'][0]['amount_minor'])->toBe(4000)->and(CounterpartyAllocationEntry::count())->toBe(0)->and(Transaction::count())->toBe($before);
});

test('shared flag is independent and defaults to leaving shared domain untouched', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false, 'counterparty_ledger.shadow_manual_enabled' => true]);
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    expect(CounterpartyRelation::count())->toBe(0)->and(CounterpartySourceLink::count())->toBe(0);
    app(CreditDebtItemService::class)->create($user, ['counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Manuale', 'total_amount' => '200.00', 'currency_code' => 'EUR', 'due_date' => null]);
    expect(CounterpartyObligation::count())->toBe(1);
});

test('explicit dry run apply and retry reconcile manual shared and known manual payments without bank mutations', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    $item = app(CreditDebtItemService::class)->create($user, ['counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Pregresso', 'total_amount' => '200.00', 'currency_code' => 'EUR', 'due_date' => null]);
    sharedLedgerTransaction($user, $party, $account);
    $legacyUnassociated = legacyCreditDebtItem($user, ['type' => 'credit', 'description' => 'Unassociated', 'total_amount' => '900.00', 'currency_code' => 'EUR']);
    $service = app(CounterpartyBackfillService::class);
    $dry = $service->run($user, $party, 'EUR');
    expect($dry['dry_run'])->toBeTrue()->and($dry['ledger_result_minor'])->toBe(25000)->and($dry['delta_minor'])->toBe(0)->and(CounterpartyObligation::count())->toBe(0)->and(CounterpartyRelation::count())->toBe(0);
    app(CreditDebtPaymentService::class)->create($user, $item, ['account_id' => $account->id, 'amount' => '100.00', 'paid_at' => '2026-10-03']);
    $transactionCount = Transaction::count();
    $first = $service->run($user, $party, 'EUR', true);
    $ids = [CounterpartyObligation::query()->pluck('uuid')->all(), CounterpartyPayment::query()->pluck('uuid')->all(), CounterpartyLedgerEntry::count(), CounterpartyAllocationEntry::count()];
    $again = $service->run($user, $party, 'EUR', true);
    expect($first['ledger_result_minor'])->toBe(15000)->and($first['readiness'])->toBe('reconciled')->and($again['delta_minor'])->toBe(0)
        ->and([CounterpartyObligation::query()->pluck('uuid')->all(), CounterpartyPayment::query()->pluck('uuid')->all(), CounterpartyLedgerEntry::count(), CounterpartyAllocationEntry::count()])->toBe($ids)
        ->and(Transaction::count())->toBe($transactionCount)->and($legacyUnassociated->fresh()->counterparty_id)->toBeNull();
});

test('settlement backfill is unallocated idempotent and has no new Transaction', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    sharedLegacySettlement($user, $party, $account);
    $service = app(CounterpartyBackfillService::class);
    $service->run($user, $party, 'EUR', true);
    $count = CounterpartyLedgerEntry::count();
    $report = $service->run($user, $party, 'EUR', true);
    expect($report['ledger_result_minor'])->toBe(3000)->and($report['readiness'])->toBe('reconciled')->and(CounterpartyPayment::count())->toBe(1)
        ->and(CounterpartyLedgerEntry::count())->toBe($count)->and(CounterpartyAllocationEntry::count())->toBe(0)->and(Transaction::count())->toBe(2);
});

test('backfill rolls back an entire relation when a write fails midway', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    app(CreditDebtItemService::class)->create($user, ['counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Manuale', 'total_amount' => '200.00', 'currency_code' => 'EUR']);
    sharedLedgerTransaction($user, $party, $account);
    $real = app(SharedCounterpartyShadowService::class);
    $mock = Mockery::mock(SharedCounterpartyShadowService::class)->makePartial();
    $mock->shouldReceive('synchronizeSource')->once()->andThrow(new RuntimeException('Injected import failure'));
    app()->instance(SharedCounterpartyShadowService::class, $mock);
    expect(fn () => app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true))->toThrow(RuntimeException::class);
    expect(CounterpartyRelation::count())->toBe(0)->and(CounterpartyObligation::count())->toBe(0)->and(CounterpartyLedgerEntry::count())->toBe(0)->and(Transaction::count())->toBe(1)->and(CreditDebtItem::count())->toBe(1);
    app()->instance(SharedCounterpartyShadowService::class, $real);
});

test('reconciliation reports per obligation mismatches even when the total delta is zero', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $first = sharedLedgerTransaction($user, $party, $account);
    $second = sharedLedgerTransaction($user, $party, $account);
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    $first->update(['shared_expense_counterparty_share_cents' => 3000, 'shared_expense_my_share_cents' => 7000]);
    $second->update(['shared_expense_counterparty_share_cents' => 7000, 'shared_expense_my_share_cents' => 3000]);
    $report = sharedRelationReport($user, $party);
    expect($report['delta_minor'])->toBe(0)->and(collect($report['source_deltas'])->pluck('delta_minor')->sort()->values()->all())->toBe([-2000, 2000])
        ->and(collect($report['anomalies'])->where('code', 'obligation_principal_mismatch')->count())->toBe(2)->and($report['readiness'])->toBe('blocked');
});

test('readiness blocks unacknowledged payments and pending shadow failures', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $source = sharedLedgerTransaction($user, $party, $account);
    $source->update(['currency' => 'USD']);
    expect(CounterpartySyncIssue::query()->whereNull('resolved_at')->count())->toBe(1)->and(CounterpartyObligation::count())->toBe(1);
    $report = app(CounterpartyReconciliationService::class)->compare($user, $party, 'USD');
    expect($report['readiness'])->toBe('blocked')->and($report['pending_errors'])->toHaveCount(1);
});

test('the explicit command defaults to dry run and validates scopes', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    $this->artisan('counterparties:backfill', ['--counterparty' => $party->uuid])->assertSuccessful();
    expect(CounterpartyRelation::count())->toBe(0);
    $this->artisan('counterparties:backfill', ['--counterparty' => $party->uuid, '--currency' => 'EUR', '--apply' => true])->assertSuccessful();
    $relation = CounterpartyRelation::query()->first();
    $this->artisan('counterparties:backfill', ['--relation' => $relation->uuid])->assertSuccessful();
    $this->artisan('counterparties:backfill', ['--counterparty' => $party->uuid, '--user' => User::factory()->create()->uuid])->assertFailed();
});

test('source aliases cannot be rewritten or deleted even through bulk SQL', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    $link = CounterpartySourceLink::query()->first();
    expect(fn () => $link->update(['economic_identity' => 'fake']))->toThrow(LogicException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('counterparty_source_links')->where('id', $link->id)->delete()))->toThrow(QueryException::class);
});

function sharedLedgerOccurrence(User $user, TrackedItem $party, Account $account): RecurringEntryOccurrence
{
    $entry = RecurringEntry::query()->create(['user_id' => $user->id, 'account_id' => $account->id, 'title' => 'Shared recurring', 'direction' => 'expense', 'currency' => 'EUR', 'entry_type' => 'recurring', 'status' => 'active', 'is_active' => true, 'start_date' => '2026-10-01', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'expected_amount' => 100, 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'counterparty', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);

    return $entry->occurrences()->create(['sequence_number' => 1, 'expected_date' => '2026-10-01', 'due_date' => '2026-10-01', 'expected_amount' => 100, 'status' => 'pending']);
}

test('an occurrence uses its persistent UUID and cumulative postings revise one obligation', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $occurrence = sharedLedgerOccurrence($user, $party, $account);
    expect(CounterpartyObligation::count())->toBe(0);
    $source = app(SharedExpenseOccurrencePostingService::class)->post($occurrence, $user, ['amount' => '40.00']);
    $obligation = CounterpartyObligation::query()->first();
    expect($obligation->economic_identity)->toBe('shared-occurrence:'.$occurrence->uuid)->and(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(-2000);
    app(SharedExpenseOccurrencePostingService::class)->post($occurrence, $user, ['amount' => '60.00']);
    expect(CounterpartyObligation::count())->toBe(1)->and(CounterpartyObligation::query()->first()->uuid)->toBe($obligation->uuid)
        ->and(CounterpartyLedgerEntry::query()->orderBy('id')->pluck('signed_minor')->all())->toBe([-2000, 2000, -5000])
        ->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0)->and(Transaction::count())->toBe(0);
});

test('recurring root cascade revokes captured external obligations before deleting occurrences', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $occurrence = sharedLedgerOccurrence($user, $party, $account);
    app(SharedExpenseOccurrencePostingService::class)->post($occurrence, $user);
    app(DeleteRecurringEntryService::class)->delete($occurrence->recurringEntry);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(0)->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0)->and(CounterpartyObligation::count())->toBe(1);
});

test('multiple effective documents with one occurrence identity are reported as duplicate and ambiguous', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    $occurrence = sharedLedgerOccurrence($user, $party, $account);
    sharedLedgerTransaction($user, $party, $account, ['recurring_entry_occurrence_id' => $occurrence->id]);
    sharedLedgerExternal($user, $party, ['recurring_entry_occurrence_id' => $occurrence->id]);
    $report = sharedRelationReport($user, $party);
    expect($report['duplicate_sources'])->toHaveCount(1)->and($report['ambiguous_identities'])->toHaveCount(1);
    $plan = app(CounterpartyBackfillService::class)->run($user, $party, 'EUR');
    expect($plan['ambiguous'])->toHaveCount(1)->and($plan['ledger_result_minor'])->toBeNull();
    expect(fn () => app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true))->toThrow(ValidationException::class);
    expect(CounterpartyObligation::count())->toBe(0)->and(CounterpartySourceLink::count())->toBe(0);
});

test('ambiguous identities fail open in shadow and leave a pending diagnostic', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $occurrence = sharedLedgerOccurrence($user, $party, $account);
    sharedLedgerTransaction($user, $party, $account, ['recurring_entry_occurrence_id' => $occurrence->id]);
    $second = sharedLedgerExternal($user, $party, ['recurring_entry_occurrence_id' => $occurrence->id]);
    expect($second->exists)->toBeTrue()->and(CounterpartyObligation::count())->toBe(1)->and(CounterpartySyncIssue::query()->whereNull('resolved_at')->count())->toBe(1)
        ->and(sharedRelationReport($user, $party)['readiness'])->toBe('blocked');
});

test('a fully refunded historical settlement is imported with zero effective amount and no allocation', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    $settlement = sharedLegacySettlement($user, $party, $account);
    Transaction::query()->create(['user_id' => $user->id, 'account_id' => $account->id, 'transaction_date' => '2026-10-04', 'amount' => '20.00', 'currency' => 'EUR', 'direction' => 'expense', 'kind' => 'refund', 'source_type' => 'manual', 'status' => 'confirmed', 'refunded_transaction_id' => $settlement->transaction_id]);
    $report = app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true)['reconciliation'];
    expect($report['delta_minor'])->toBe(0)->and($report['payments'][0]['effective_minor'])->toBe(0)->and(CounterpartyPayment::count())->toBe(1)->and(CounterpartyAllocationEntry::count())->toBe(0)->and(Transaction::count())->toBe(3);
});

test('dry run and read diagnostics never modify legacy rows or timestamps', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    $transaction = sharedLedgerTransaction($user, $party, $account);
    $settlement = sharedLegacySettlement($user, $party, $account);
    $snapshots = [$transaction->fresh()->getAttributes(), $settlement->fresh()->getAttributes()];
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR');
    sharedRelationReport($user, $party);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    expect([$transaction->fresh()->getAttributes(), $settlement->fresh()->getAttributes()])->toBe($snapshots);
});

test('known aliases survive a technical conversion even when shared shadow is disabled', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $source = sharedLedgerTransaction($user, $party, $account);
    $identity = CounterpartyObligation::query()->first()->economic_identity;
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    $target = app(TransactionMutationService::class)->update($user, $source, sharedConversionPayload($account, $party, 'counterparty'));
    expect(CounterpartySourceLink::query()->where('source_identity', $target->uuid)->value('economic_identity'))->toBe($identity)
        ->and(CounterpartyObligation::count())->toBe(1)->and(sharedRelationReport($user, $party)['readiness'])->toBe('blocked');
    $report = app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    expect($report['delta_minor'])->toBe(0)->and($report['ledger_result_minor'])->toBe(-5000)->and(CounterpartyObligation::count())->toBe(1);
});

test('readiness distinguishes untouched incomplete shadowing and reconciled relations', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    expect(sharedRelationReport($user, $party)['readiness'])->toBe('not_migrated');
    app(CounterpartyLedgerService::class)->relation($user, $party, 'EUR');
    expect(sharedRelationReport($user, $party)['readiness'])->toBe('shadowing');
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    expect(sharedRelationReport($user, $party)['readiness'])->toBe('reconciled');
});

test('inactive historical counterparties can be explicitly reconciled without being reenabled', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    $party->update(['is_active' => false, 'is_shared_expense_counterpart' => false]);
    $report = app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    expect($report['delta_minor'])->toBe(0)->and($report['readiness'])->toBe('reconciled')->and($party->fresh()->is_active)->toBeFalse()->and($party->fresh()->is_shared_expense_counterpart)->toBeFalse();
});

test('migration failures remain diagnostic until a successful retry resolves them', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    $source = sharedLedgerTransaction($user, $party, $account);
    $real = app(SharedCounterpartyShadowService::class);
    $mock = Mockery::mock(SharedCounterpartyShadowService::class)->makePartial();
    $mock->shouldReceive('synchronizeSource')->once()->andThrow(new RuntimeException('Injected failure'));
    app()->instance(SharedCounterpartyShadowService::class, $mock);
    expect(fn () => app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true))->toThrow(RuntimeException::class);
    expect(sharedRelationReport($user, $party)['readiness'])->toBe('blocked');
    app()->instance(SharedCounterpartyShadowService::class, $real);
    app()->forgetInstance(CounterpartyBackfillService::class);
    $report = app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    expect($report['readiness'])->toBe('reconciled')->and(CounterpartySyncIssue::query()->whereNull('resolved_at')->count())->toBe(0)->and(Transaction::count())->toBe(1);
});

test('shared recognition uses currency specific units and never merges two currencies', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    $jpy = createTestAccount($user, ['currency' => 'JPY', 'currency_code' => 'JPY']);
    sharedLedgerTransaction($user, $party, $jpy, ['currency' => 'JPY']);
    expect(CounterpartyRelation::count())->toBe(2)->and(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(5000)
        ->and(app(CounterpartyReconciliationService::class)->compare($user, $party, 'JPY')['ledger_balance_minor'])->toBe(50)
        ->and(app(CounterpartyReconciliationService::class)->compare($user, $party, 'JPY')['delta_minor'])->toBe(0);
});

test('foundation and identity migrations can be rolled back and reapplied on the current database driver', function () {
    $identity = require database_path('migrations/2026_10_14_171350_create_counterparty_source_links_and_sync_issues.php');
    $foundation = require database_path('migrations/2026_10_14_154852_create_counterparty_ledger_foundations.php');
    $public = require database_path('migrations/2026_10_14_181001_add_public_lifecycle_to_counterparty_ledger.php');
    $public->down();
    $identity->down();
    $foundation->down();
    expect(Schema::hasTable('counterparty_relations'))->toBeFalse();
    $foundation->up();
    $identity->up();
    $public->up();
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    expect(CounterpartyObligation::count())->toBe(1)->and(CounterpartySourceLink::count())->toBe(1);
    $link = CounterpartySourceLink::query()->first();
    expect(fn () => DB::transaction(fn () => DB::table('counterparty_source_links')->where('id', $link->id)->update(['economic_identity' => 'mutated'])))->toThrow(QueryException::class);
});

test('known scheduled partial transactions contribute to one occurrence obligation without duplicate recognition', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $occurrence = sharedLedgerOccurrence($user, $party, $account);
    $occurrence->recurringEntry->update(['shared_expense_paid_by' => 'self']);
    $first = sharedLedgerTransaction($user, $party, $account, ['kind' => 'scheduled', 'recurring_entry_occurrence_id' => $occurrence->id, 'amount' => '40.00', 'shared_expense_my_share_cents' => 2000, 'shared_expense_counterparty_share_cents' => 2000]);
    $uuid = CounterpartyObligation::query()->first()->uuid;
    $second = sharedLedgerTransaction($user, $party, $account, ['kind' => 'scheduled', 'recurring_entry_occurrence_id' => $occurrence->id, 'amount' => '60.00', 'shared_expense_my_share_cents' => 3000, 'shared_expense_counterparty_share_cents' => 3000]);
    $report = sharedRelationReport($user, $party);
    expect(CounterpartyObligation::count())->toBe(1)->and(CounterpartyObligation::query()->first()->uuid)->toBe($uuid)->and($report['ledger_balance_minor'])->toBe(5000)
        ->and($report['delta_minor'])->toBe(0)->and($report['duplicate_sources'])->toBe([])->and($report['readiness'])->toBe('reconciled');
    $first->delete();
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(3000)->and(CounterpartyObligation::count())->toBe(1);
    $first->restore();
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(5000);
    $before = CounterpartyLedgerEntry::count();
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    expect(CounterpartyLedgerEntry::count())->toBe($before)->and(Transaction::count())->toBe(2);
});

test('scheduled partial occurrence backfill is deterministic and retry creates no historical entries', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    $occurrence = sharedLedgerOccurrence($user, $party, $account);
    sharedLedgerTransaction($user, $party, $account, ['kind' => 'scheduled', 'recurring_entry_occurrence_id' => $occurrence->id, 'amount' => '40.00', 'shared_expense_my_share_cents' => 2000, 'shared_expense_counterparty_share_cents' => 2000]);
    sharedLedgerTransaction($user, $party, $account, ['kind' => 'scheduled', 'recurring_entry_occurrence_id' => $occurrence->id, 'amount' => '60.00', 'shared_expense_my_share_cents' => 3000, 'shared_expense_counterparty_share_cents' => 3000]);
    $backfill = app(CounterpartyBackfillService::class);
    expect($backfill->run($user, $party, 'EUR')['ledger_result_minor'])->toBe(5000);
    $first = $backfill->run($user, $party, 'EUR', true);
    $count = CounterpartyLedgerEntry::count();
    $again = $backfill->run($user, $party, 'EUR', true);
    expect($first['delta_minor'])->toBe(0)->and($again['readiness'])->toBe('reconciled')->and(CounterpartyLedgerEntry::count())->toBe($count)->and(CounterpartyObligation::count())->toBe(1);
});

test('refund followed by another scheduled posting updates the same occurrence obligation', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $occurrence = sharedLedgerOccurrence($user, $party, $account);
    $occurrence->recurringEntry->update(['shared_expense_paid_by' => 'self']);
    $posting = app(RecurringEntryPostingService::class);
    $first = $posting->post($occurrence, $user);
    $obligationUuid = CounterpartyObligation::query()->first()->uuid;
    app(TransactionRefundService::class)->refund($first, ['amount' => '40.00']);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(3000);
    $second = $posting->post($occurrence, $user);
    $report = sharedRelationReport($user, $party);
    expect($second->id)->not->toBe($first->id)->and(CounterpartyObligation::count())->toBe(1)->and(CounterpartyObligation::query()->first()->uuid)->toBe($obligationUuid)
        ->and($report['ledger_balance_minor'])->toBe(5000)->and($report['delta_minor'])->toBe(0)->and($report['readiness'])->toBe('reconciled')
        ->and(CounterpartyPayment::count())->toBe(0);
});

test('component discrepancies that cancel within one occurrence are still diagnostic', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    $occurrence = sharedLedgerOccurrence($user, $party, $account);
    $first = sharedLedgerTransaction($user, $party, $account, ['kind' => 'scheduled', 'recurring_entry_occurrence_id' => $occurrence->id]);
    $second = sharedLedgerTransaction($user, $party, $account, ['kind' => 'scheduled', 'recurring_entry_occurrence_id' => $occurrence->id]);
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    $first->update(['shared_expense_counterparty_share_cents' => 3000]);
    $second->update(['shared_expense_counterparty_share_cents' => 7000]);
    $report = sharedRelationReport($user, $party);
    expect($report['delta_minor'])->toBe(0)->and($report['readiness'])->toBe('blocked')
        ->and(collect($report['anomalies'])->where('code', 'shared_component_mismatch')->count())->toBe(2);
});

test('the main accounting case remains coherent across import share revision settlement refund and conversions', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    app(CreditDebtItemService::class)->create($user, ['counterparty_id' => $party->id, 'type' => 'credit', 'description' => 'Pregresso +200', 'total_amount' => '200.00', 'currency_code' => 'EUR', 'due_date' => null]);
    $source = sharedLedgerTransaction($user, $party, $account);
    app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    $sharedUuid = CounterpartyObligation::query()->where('source_type', 'shared_transaction_share')->value('uuid');
    expect(sharedRelationReport($user, $party)['legacy_balance_minor'])->toBe(25000)->and(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(25000);
    $source->update(['shared_expense_my_share_cents' => 7000, 'shared_expense_counterparty_share_cents' => 3000]);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(23000);
    app(SharedExpenseSettlementService::class)->record($user, $party, ['amount' => '10.00', 'settled_at' => '2026-10-03', 'account_uuid' => $account->uuid, 'reference' => null]);
    $refund = app(TransactionRefundService::class)->refund($source, ['amount' => '40.00']);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(20800)->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0)->and(CounterpartyPayment::count())->toBe(1);
    app(TransactionRefundService::class)->undo($refund);
    $payload = [...sharedConversionPayload($account, $party, 'counterparty'), 'shared_expense_my_share' => '70.00', 'shared_expense_counterparty_share' => '30.00'];
    $external = app(TransactionMutationService::class)->update($user, $source->fresh(), $payload);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(12000)->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0);
    app(TransactionMutationService::class)->updateSharedLedger($user, $external, [...$payload, 'shared_expense_paid_by' => 'self']);
    expect(sharedRelationReport($user, $party)['ledger_balance_minor'])->toBe(22000)->and(sharedRelationReport($user, $party)['readiness'])->toBe('reconciled')
        ->and(CounterpartyObligation::query()->where('uuid', $sharedUuid)->exists())->toBeTrue()->and(CounterpartyObligation::count())->toBe(2)->and(CounterpartyPayment::count())->toBe(1);
});

test('valid settlement update does not produce a transient shadow error for the intermediate bank change', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    $settlement = sharedLegacySettlement($user, $party, $account);
    Log::spy();
    app(SharedExpenseSettlementService::class)->update($user, $settlement, ['amount' => '10.00', 'settled_at' => '2026-10-04', 'account_uuid' => $account->uuid, 'reference' => null]);
    Log::shouldNotHaveReceived('warning');
    expect(CounterpartySyncIssue::count())->toBe(0)->and(sharedRelationReport($user, $party)['delta_minor'])->toBe(0);
});

test('backfill reuses a phase 2A shared identity and reverses prior delta revisions without losing history', function () {
    config(['counterparty_ledger.shadow_shared_enabled' => false]);
    [$user, $party, $account] = sharedLedgerDomain();
    $source = sharedLedgerTransaction($user, $party, $account);
    $ledger = app(CounterpartyLedgerService::class);
    $relation = $ledger->relation($user, $party, 'EUR');
    $obligation = $ledger->recognize($relation, CounterpartyObligationSourceEnum::SHARED_TRANSACTION, $source->uuid, 'known-economic-identity', 5000, '2026-10-01', 'old-recognition');
    $ledger->revise($obligation, 6000, '2026-10-02', 'old-delta-revision');
    $report = app(CounterpartyBackfillService::class)->run($user, $party, 'EUR', true);
    expect($report['delta_minor'])->toBe(0)->and($report['readiness'])->toBe('reconciled')->and(CounterpartyObligation::count())->toBe(1)
        ->and(CounterpartyObligation::query()->first()->uuid)->toBe($obligation->uuid)->and(CounterpartyObligation::query()->first()->economic_identity)->toBe('known-economic-identity')
        ->and(CounterpartyLedgerEntry::query()->orderBy('id')->pluck('signed_minor')->all())->toBe([5000, 1000, -1000, -5000, 5000]);
});

test('settlement revisions project the current document and retain original immutable amounts', function () {
    [$user, $party, $account] = sharedLedgerDomain();
    sharedLedgerTransaction($user, $party, $account);
    $settlement = sharedLegacySettlement($user, $party, $account);
    app(SharedExpenseSettlementService::class)->update($user, $settlement, ['amount' => '30.00', 'settled_at' => '2026-10-04', 'account_uuid' => $account->uuid, 'reference' => null]);
    $report = sharedRelationReport($user, $party);
    expect(CounterpartyPayment::query()->first()->amount_minor)->toBe(2000)->and($report['payments'][0]['original_amount_minor'])->toBe(2000)
        ->and($report['payments'][0]['amount_minor'])->toBe(3000)->and($report['payments'][0]['effective_minor'])->toBe(3000)
        ->and($report['delta_minor'])->toBe(0)->and($report['readiness'])->toBe('reconciled');
});
