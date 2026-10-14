<?php

use App\Enums\CounterpartyObligationSourceEnum as SourceType;
use App\Enums\CounterpartyPaymentDirectionEnum as PaymentDirection;
use App\Models\CounterpartyAllocationEntry;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Counterparties\CounterpartyLedgerService;
use App\Services\Counterparties\CounterpartyProjectionService;
use App\Services\Counterparties\CounterpartyReconciliationService;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\CreditDebts\CreditDebtItemService;
use App\Services\CreditDebts\CreditDebtPaymentService;
use App\Support\CurrencyMinorUnits;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['features.credits_debts.enabled' => true, 'counterparty_ledger.shadow_manual_enabled' => false]);
});

/** @return array{0: User, 1: TrackedItem, 2: CounterpartyRelation} */
function counterpartyDomain(string $currency = 'EUR'): array
{
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $counterparty = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Emanuela', 'slug' => fake()->uuid(), 'is_active' => true, 'is_shared_expense_counterpart' => true]);

    return [$user, $counterparty, app(CounterpartyLedgerService::class)->relation($user, $counterparty, $currency)];
}

function domainObligation(CounterpartyRelation $relation, int $minor = 20000, string $identity = 'manual-A', string $date = '2026-10-01'): CounterpartyObligation
{
    return app(CounterpartyLedgerService::class)->recognize($relation, SourceType::OPENING_BALANCE, $identity, $identity, $minor, $date, 'recognize:'.$identity);
}

function domainPayment(CounterpartyRelation $relation, int $minor = 10000, PaymentDirection $direction = PaymentDirection::RECEIVED, string $key = 'payment-A'): CounterpartyPayment
{
    $account = createTestAccount($relation->user, ['currency' => $relation->currency_code, 'currency_code' => $relation->currency_code, 'opening_balance' => 1000, 'current_balance' => 1000]);

    return app(CounterpartyLedgerService::class)->recordPayment($relation, $direction, $minor, '2026-10-03', $account, $key);
}

/** @return array<string, mixed> */
function shadowManualData(TrackedItem $counterparty, string $currency = 'EUR', string $amount = '200.00'): array
{
    return ['counterparty_id' => $counterparty->id, 'type' => 'credit', 'description' => 'Credito pregresso', 'total_amount' => $amount, 'currency_code' => $currency, 'due_date' => null];
}

test('minor units follow the currency precision and round trip exactly', function (string $currency, string $input, int $minor, string $display) {
    expect(CurrencyMinorUnits::fromDecimal($input, $currency))->toBe($minor)->and(CurrencyMinorUnits::toDecimal($minor, $currency))->toBe($display);
})->with([
    ['EUR', '200', 20000, '200.00'], ['EUR', '0.01', 1, '0.01'], ['EUR', '-20.30', -2030, '-20.30'],
    ['EUR', '1.2300', 123, '1.23'], ['JPY', '200', 200, '200'], ['JPY', '200.00', 200, '200'], ['JPY', '-1', -1, '-1'], ['JPY', '0', 0, '0'],
]);

test('unrepresentable fractions invalid decimal inputs and unsupported currencies are rejected', function (string $currency, string $input) {
    CurrencyMinorUnits::fromDecimal($input, $currency);
})->with([['JPY', '200.50'], ['EUR', '1.001'], ['EUR', '1e2'], ['EUR', '1,20'], ['XXX', '200']])->throws(InvalidArgumentException::class);

test('minor unit parsing and sums cannot silently overflow or fall back to floats', function () {
    expect(CurrencyMinorUnits::fromDecimal('92233720368547758.07', 'EUR'))->toBe(PHP_INT_MAX);
    expect(fn () => CurrencyMinorUnits::fromDecimal('92233720368547758.08', 'EUR'))->toThrow(OverflowException::class)
        ->and(fn () => CurrencyMinorUnits::add(PHP_INT_MAX, 1))->toThrow(OverflowException::class);
});

test('one relation exists per owner counterparty and currency', function () {
    [$user, $party, $relation] = counterpartyDomain();
    $again = app(CounterpartyLedgerService::class)->relation($user, $party, 'EUR');
    $usd = app(CounterpartyLedgerService::class)->relation($user, $party, 'USD');
    expect($again->id)->toBe($relation->id)->and($usd->id)->not->toBe($relation->id)->and(CounterpartyRelation::count())->toBe(2);
    $copy = $relation->getAttributes();
    unset($copy['id']);
    $copy['uuid'] = fake()->uuid();
    expect(fn () => DB::transaction(fn () => DB::table('counterparty_relations')->insert($copy)))->toThrow(QueryException::class);
});

test('relation factory creates a valid personal economic counterparty', function () {
    $relation = CounterpartyRelation::factory()->create();
    expect($relation->counterparty->user_id)->toBe($relation->user_id)->and($relation->counterparty->account_id)->toBeNull()->and($relation->minor_unit)->toBe(2);
});

test('relation ownership personal scope and capability are enforced', function (string $case) {
    [$user, $party] = counterpartyDomain();
    if ($case === 'foreign') {
        $user = User::factory()->create();
    } elseif ($case === 'account') {
        $party->update(['account_id' => createTestAccount($user)->id]);
    } elseif ($case === 'disabled') {
        $party->update(['is_shared_expense_counterpart' => false]);
    } else {
        $party->update(['is_active' => false]);
    }
    app(CounterpartyLedgerService::class)->relation($user, $party, 'USD');
})->with(['foreign', 'account', 'disabled', 'inactive'])->throws(ValidationException::class);

test('the ledger posts two obligations and one payment with exactly one balance effect each', function () {
    [, , $relation] = counterpartyDomain();
    $manual = domainObligation($relation);
    $shared = domainObligation($relation, 5000, 'shared-B', '2026-10-02');
    $payment = domainPayment($relation);
    $projection = app(CounterpartyProjectionService::class);
    expect($projection->balance($relation))->toBe(15000)->and($projection->unallocated($payment))->toBe(10000)
        ->and($projection->remaining($manual))->toBe(20000)->and($projection->remaining($shared))->toBe(5000)
        ->and($projection->reconcile($relation)['consistent'])->toBeTrue()->and(Transaction::count())->toBe(0);
    $entry = app(CounterpartyLedgerService::class)->reversePayment($payment, '2026-10-04', 'reverse-payment');
    expect($entry->signed_minor)->toBe(10000)->and($projection->balance($relation))->toBe(25000)
        ->and($projection->effectivePayment($payment))->toBe(0)->and($projection->reconcile($relation)['consistent'])->toBeTrue();
});

test('ledger idempotency is payload based and ignores metadata object key order', function () {
    [, , $relation] = counterpartyDomain();
    $service = app(CounterpartyLedgerService::class);
    $first = $service->recognize($relation, SourceType::OPENING_BALANCE, 'A', 'economic-A', 20000, '2026-10-01', 'same-key', metadata: ['a' => 1, 'b' => 2]);
    $again = $service->recognize($relation, SourceType::OPENING_BALANCE, 'A', 'economic-A', 20000, '2026-10-01', 'same-key', metadata: ['b' => 2, 'a' => 1]);
    expect($again->uuid)->toBe($first->uuid)->and(CounterpartyLedgerEntry::count())->toBe(1)->and(CounterpartyObligation::count())->toBe(1);
    expect(fn () => $service->recognize($relation, SourceType::OPENING_BALANCE, 'A', 'economic-A', 25000, '2026-10-01', 'same-key'))->toThrow(ValidationException::class);
    expect(fn () => $service->recognize($relation, SourceType::OPENING_BALANCE, 'A', 'economic-A', 20000, '2026-10-01', 'different-key'))->toThrow(ValidationException::class);
});

test('a source version cannot contribute twice even with a different operation key', function () {
    [, , $relation] = counterpartyDomain();
    domainObligation($relation);
    $copy = CounterpartyLedgerEntry::query()->sole()->getAttributes();
    unset($copy['id']);
    $copy['uuid'] = fake()->uuid();
    $copy['idempotency_key'] = 'another-key';
    expect(fn () => DB::transaction(fn () => DB::table('counterparty_ledger_entries')->insert($copy)))->toThrow(QueryException::class)
        ->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(20000);
});

test('allocations can be full partial or absent without changing the ledger balance', function (int $allocation) {
    [, , $relation] = counterpartyDomain();
    $obligation = domainObligation($relation);
    $payment = domainPayment($relation);
    if ($allocation > 0) {
        app(CounterpartyLedgerService::class)->allocate($payment, $obligation, $allocation, '2026-10-03', 'allocation');
    }
    $projection = app(CounterpartyProjectionService::class);
    expect($projection->allocatedToPayment($payment))->toBe($allocation)->and($projection->unallocated($payment))->toBe(10000 - $allocation)
        ->and($projection->remaining($obligation))->toBe(20000 - $allocation)->and($projection->balance($relation))->toBe(10000)
        ->and($projection->reconcile($relation)['consistent'])->toBeTrue()->and(CounterpartyLedgerEntry::count())->toBe(2);
})->with([10000, 4000, 0]);

test('allocation cannot exceed payment or obligation capacity', function (string $case) {
    [, , $relation] = counterpartyDomain();
    $obligation = domainObligation($relation, $case === 'obligation' ? 5000 : 20000);
    $payment = domainPayment($relation);
    $service = app(CounterpartyLedgerService::class);
    if ($case === 'payment') {
        $service->allocate($payment, $obligation, 8000, '2026-10-03', 'first');
    }
    expect(fn () => $service->allocate($payment, $obligation, $case === 'payment' ? 3000 : 6000, '2026-10-03', 'too-much'))->toThrow(ValidationException::class);
    expect(app(CounterpartyProjectionService::class)->reconcile($relation)['consistent'])->toBeTrue();
})->with(['payment', 'obligation']);

test('different currencies cannot be allocated together', function () {
    [$user, $party, $eur] = counterpartyDomain();
    $usd = app(CounterpartyLedgerService::class)->relation($user, $party, 'USD');
    $obligation = domainObligation($usd);
    $payment = domainPayment($eur);
    expect(fn () => app(CounterpartyLedgerService::class)->allocate($payment, $obligation, 1000, '2026-10-03', 'invalid'))->toThrow(ValidationException::class);
    expect(app(CounterpartyProjectionService::class)->balance($usd))->toBe(20000)->and(app(CounterpartyProjectionService::class)->balance($eur))->toBe(-10000);
});

test('payment directions only settle compatible obligation signs', function (string $direction, int $principal) {
    [, , $relation] = counterpartyDomain();
    $payment = domainPayment($relation, direction: PaymentDirection::from($direction));
    $obligation = domainObligation($relation, $principal);
    app(CounterpartyLedgerService::class)->allocate($payment, $obligation, 1000, '2026-10-03', 'invalid-direction');
})->with([['sent', 20000], ['received', -20000]])->throws(ValidationException::class);

test('an outgoing payment allocates a negative obligation using the same sign convention', function () {
    [, , $relation] = counterpartyDomain();
    $debt = domainObligation($relation, -20000);
    $payment = domainPayment($relation, direction: PaymentDirection::SENT);
    app(CounterpartyLedgerService::class)->allocate($payment, $debt, 10000, '2026-10-03', 'debt-payment');
    $projection = app(CounterpartyProjectionService::class);
    expect($projection->balance($relation))->toBe(-10000)->and($projection->remaining($debt))->toBe(10000)->and($projection->reconcile($relation)['consistent'])->toBeTrue();
});

test('allocation reversal preserves the original and is idempotent', function () {
    [, , $relation] = counterpartyDomain();
    $obligation = domainObligation($relation);
    $payment = domainPayment($relation);
    $service = app(CounterpartyLedgerService::class);
    $allocation = $service->allocate($payment, $obligation, 10000, '2026-10-03', 'allocate');
    $again = $service->allocate($payment, $obligation, 10000, '2026-10-03', 'allocate');
    expect($again->id)->toBe($allocation->id);
    expect(fn () => $service->allocate($payment, $obligation, 5000, '2026-10-03', 'allocate'))->toThrow(ValidationException::class);
    $reverse = $service->reverseAllocation($allocation, '2026-10-04', 'reverse');
    $same = $service->reverseAllocation($allocation, '2026-10-04', 'reverse');
    expect($reverse->amount_minor)->toBe(-10000)->and($reverse->reversal_of_id)->toBe($allocation->id)->and($same->id)->toBe($reverse->id)
        ->and($allocation->fresh()->amount_minor)->toBe(10000)->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(10000)
        ->and(app(CounterpartyProjectionService::class)->unallocated($payment))->toBe(10000);
});

test('payment reversal releases all allocations and preserves all historical rows', function () {
    [, , $relation] = counterpartyDomain();
    $obligation = domainObligation($relation);
    $payment = domainPayment($relation);
    $service = app(CounterpartyLedgerService::class);
    $service->allocate($payment, $obligation, 10000, '2026-10-03', 'allocate');
    $reverse = $service->reversePayment($payment, '2026-10-04', 'reverse');
    $again = $service->reversePayment($payment, '2026-10-04', 'reverse');
    expect($again->id)->toBe($reverse->id)->and(CounterpartyPayment::count())->toBe(1)->and(CounterpartyAllocationEntry::count())->toBe(2)
        ->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(20000)
        ->and(app(CounterpartyProjectionService::class)->remaining($obligation))->toBe(20000)
        ->and(app(CounterpartyProjectionService::class)->reconcile($relation)['consistent'])->toBeTrue();
});

test('FIFO across distinct obligations is simulatable without a public payment lifecycle', function () {
    [, , $relation] = counterpartyDomain();
    $a = domainObligation($relation, 4000, 'A', '2026-10-01');
    $b = domainObligation($relation, 10000, 'B', '2026-10-02');
    $payment = domainPayment($relation, 9000);
    $service = app(CounterpartyLedgerService::class);
    foreach (app(CounterpartyProjectionService::class)->openObligations($relation) as $row) {
        $remainingPayment = app(CounterpartyProjectionService::class)->unallocated($payment);
        if ($remainingPayment === 0) {
            break;
        }
        $service->allocate($payment, CounterpartyObligation::query()->where('uuid', $row['uuid'])->firstOrFail(), min($remainingPayment, $row['remaining_minor']), '2026-10-03', 'fifo:'.$row['uuid']);
    }
    $projection = app(CounterpartyProjectionService::class);
    expect($projection->remaining($a))->toBe(0)->and($projection->remaining($b))->toBe(5000)->and($projection->balance($relation))->toBe(5000)
        ->and($projection->unallocated($payment))->toBe(0)->and($projection->reconcile($relation)['consistent'])->toBeTrue()->and(Transaction::count())->toBe(0);
});

test('explicit compensation changes document residuals but creates no cash transaction or net effect', function () {
    [, , $relation] = counterpartyDomain();
    $positive = domainObligation($relation, 20000, 'credit');
    $negative = domainObligation($relation, -5000, 'debt');
    $service = app(CounterpartyLedgerService::class);
    $event = $service->compensate($positive, $negative, 5000, '2026-10-03', 'compensate');
    $same = $service->compensate($positive, $negative, 5000, '2026-10-03', 'compensate');
    $projection = app(CounterpartyProjectionService::class);
    expect($event->signed_minor)->toBe(0)->and($same->id)->toBe($event->id)->and($projection->balance($relation))->toBe(15000)
        ->and($projection->remaining($positive))->toBe(15000)->and($projection->remaining($negative))->toBe(0)
        ->and($projection->reconcile($relation)['consistent'])->toBeTrue()->and(Transaction::count())->toBe(0)->and(CounterpartyPayment::count())->toBe(0);
    expect(fn () => $service->reverseAllocation($event->allocationEntries()->firstOrFail(), '2026-10-04', 'invalid-one-leg'))->toThrow(ValidationException::class);
    $service->reverseCompensation($event, '2026-10-04', 'reverse-compensation');
    expect($projection->remaining($positive))->toBe(20000)->and($projection->remaining($negative))->toBe(5000)->and($projection->balance($relation))->toBe(15000)->and($projection->reconcile($relation)['consistent'])->toBeTrue();
});

test('obligation source revisions preserve economic identity and update projections through history', function () {
    [, , $relation] = counterpartyDomain();
    $service = app(CounterpartyLedgerService::class);
    $obligation = $service->recognize($relation, SourceType::SHARED_TRANSACTION, 'transaction-A', 'stable-economic-A', 5000, '2026-10-01', 'recognize', dueDate: '2026-10-31');
    $uuid = $obligation->uuid;
    $revision = $service->revise($obligation, 6000, '2026-10-02', 'technical-conversion', SourceType::SHARED_LEDGER, 'external-ledger-A');
    $again = $service->revise($obligation, 6000, '2026-10-02', 'technical-conversion', SourceType::SHARED_LEDGER, 'external-ledger-A');
    $projection = app(CounterpartyProjectionService::class)->obligation($obligation);
    expect($again->id)->toBe($revision->id)->and($obligation->fresh()->uuid)->toBe($uuid)->and(CounterpartyObligation::count())->toBe(1)
        ->and($obligation->fresh()->recognized_minor)->toBe(5000)->and($projection['recognized_minor'])->toBe(6000)->and($projection['due_date'])->toBeNull()
        ->and($projection['source_type'])->toBe(SourceType::SHARED_LEDGER->value)->and($projection['source_identity'])->toBe('external-ledger-A')
        ->and($projection['economic_identity'])->toBe('stable-economic-A');
});

test('principal cannot be reduced or reversed below active allocations', function () {
    [, , $relation] = counterpartyDomain();
    $obligation = domainObligation($relation);
    $payment = domainPayment($relation);
    $service = app(CounterpartyLedgerService::class);
    $service->allocate($payment, $obligation, 10000, '2026-10-03', 'allocate');
    expect(fn () => $service->revise($obligation, 5000, '2026-10-04', 'too-low'))->toThrow(ValidationException::class)
        ->and(fn () => $service->reverseEntry($obligation->ledgerEntries()->firstOrFail(), '2026-10-04', 'invalid-reversal'))->toThrow(ValidationException::class)
        ->and(fn () => $service->revise($obligation, -20000, '2026-10-04', 'invert'))->toThrow(ValidationException::class);
});

test('revisions retain the current technical source and reserve all source identities', function () {
    [, , $relation] = counterpartyDomain();
    $service = app(CounterpartyLedgerService::class);
    $obligation = $service->recognize($relation, SourceType::SHARED_TRANSACTION, 'transaction-A', 'economic-A', 5000, '2026-10-01', 'recognize');
    $service->revise($obligation, 5000, '2026-10-02', 'convert', SourceType::SHARED_LEDGER, 'ledger-A');
    $revision = $service->revise($obligation, 6000, '2026-10-03', 'change-current');
    expect($service->revise($obligation, 6000, '2026-10-03', 'change-current')->id)->toBe($revision->id)
        ->and(app(CounterpartyProjectionService::class)->obligation($obligation)['source_identity'])->toBe('ledger-A')
        ->and(fn () => $service->recognize($relation, SourceType::SHARED_LEDGER, 'ledger-A', 'economic-B', 5000, '2026-10-03', 'duplicate'))->toThrow(ValidationException::class);
    $other = domainObligation($relation, 10000, 'other');
    expect(fn () => $service->revise($other, 10000, '2026-10-03', 'steal-source', SourceType::SHARED_LEDGER, 'ledger-A'))->toThrow(ValidationException::class);
    expect(CounterpartyObligation::count())->toBe(2)->and(app(CounterpartyProjectionService::class)->balance($relation))->toBe(16000);
});

test('obligation reversals restore the prior snapshot without undoing obsolete revisions', function () {
    [, , $relation] = counterpartyDomain();
    $service = app(CounterpartyLedgerService::class);
    $obligation = domainObligation($relation);
    $original = $obligation->ledgerEntries()->firstOrFail();
    $revision = $service->revise($obligation, 25000, '2026-10-02', 'revision', dueDate: '2026-10-31');
    expect(fn () => $service->reverseEntry($original, '2026-10-03', 'obsolete'))->toThrow(ValidationException::class);
    $service->reverseEntry($revision, '2026-10-03', 'reverse-current');
    $projection = app(CounterpartyProjectionService::class)->obligation($obligation);
    expect($projection['recognized_minor'])->toBe(20000)->and($projection['due_date'])->toBeNull()
        ->and($projection['economic_date'])->toBe('2026-10-01')
        ->and(app(CounterpartyProjectionService::class)->reconcile($relation)['consistent'])->toBeTrue();
});

test('historical models reject ORM mutations and database triggers reject bulk mutation', function (string $kind) {
    [, , $relation] = counterpartyDomain();
    $obligation = domainObligation($relation);
    $payment = domainPayment($relation);
    $allocation = app(CounterpartyLedgerService::class)->allocate($payment, $obligation, 10000, '2026-10-03', 'allocate');
    $record = match ($kind) {
        'obligation' => $obligation, 'payment' => $payment, 'allocation' => $allocation,
        default => CounterpartyLedgerEntry::query()->firstOrFail(),
    };
    expect(fn () => $record->update(['metadata' => ['changed' => true], 'economic_date' => '2026-10-05']))->toThrow(LogicException::class)
        ->and(fn () => $record->delete())->toThrow(LogicException::class)
        ->and(fn () => DB::transaction(fn () => DB::table($record->getTable())->where('id', $record->id)->update(['economic_date' => '2026-10-05'])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table($record->getTable())->where('id', $record->id)->delete()))->toThrow(QueryException::class);
})->with(['obligation', 'payment', 'ledger', 'allocation']);

test('ledger currency consistency is enforced by a database foreign key', function () {
    [, , $relation] = counterpartyDomain();
    domainObligation($relation);
    $copy = CounterpartyLedgerEntry::query()->sole()->getAttributes();
    unset($copy['id']);
    $copy['uuid'] = fake()->uuid();
    $copy['source_identity'] = 'other-source';
    $copy['idempotency_key'] = 'other-key';
    $copy['currency_code'] = 'USD';
    expect(fn () => DB::transaction(fn () => DB::table('counterparty_ledger_entries')->insert($copy)))->toThrow(QueryException::class);
});

test('payment registration reuses an existing Transaction and never generates an additional one', function () {
    [$user, , $relation] = counterpartyDomain();
    $account = createTestAccount($user);
    $bank = userTransaction($user, $account, ['direction' => 'income', 'amount' => '100.00']);
    $service = app(CounterpartyLedgerService::class);
    $first = $service->recordPayment($relation, PaymentDirection::RECEIVED, 10000, today()->toDateString(), $account, 'payment', $bank);
    $again = $service->recordPayment($relation, PaymentDirection::RECEIVED, 10000, today()->toDateString(), $account, 'payment', $bank);
    expect($again->id)->toBe($first->id)->and($first->transaction_uuid)->toBe($bank->uuid)->and(Transaction::count())->toBe(1);
    expect(fn () => $service->recordPayment($relation, PaymentDirection::RECEIVED, 9000, today()->toDateString(), $account, 'payment', $bank))->toThrow(ValidationException::class);
});

test('the new domain follows JPY precision across principal payment and allocations', function () {
    [, , $relation] = counterpartyDomain('JPY');
    $obligation = domainObligation($relation, 200);
    $payment = domainPayment($relation, 100);
    app(CounterpartyLedgerService::class)->allocate($payment, $obligation, 100, '2026-10-03', 'allocate');
    $projection = app(CounterpartyProjectionService::class);
    expect($relation->minor_unit)->toBe(0)->and($projection->balance($relation))->toBe(100)->and($projection->remaining($obligation))->toBe(100)
        ->and(CurrencyMinorUnits::toDecimal($projection->balance($relation), 'JPY'))->toBe('100')->and($projection->reconcile($relation)['consistent'])->toBeTrue();
});

test('the actor and payment account must respect economic ownership and currency', function () {
    [$user, , $relation] = counterpartyDomain();
    $other = User::factory()->create();
    $foreign = createTestAccount($other);
    $usd = createTestAccount($user, ['currency' => 'USD', 'currency_code' => 'USD']);
    $service = app(CounterpartyLedgerService::class);
    expect(fn () => $service->recordPayment($relation, PaymentDirection::RECEIVED, 10000, '2026-10-03', $foreign, 'foreign'))->toThrow(ValidationException::class)
        ->and(fn () => $service->recordPayment($relation, PaymentDirection::RECEIVED, 10000, '2026-10-03', $usd, 'currency'))->toThrow(ValidationException::class)
        ->and(fn () => $service->recognize($relation, SourceType::OPENING_BALANCE, 'A', 'A', 20000, '2026-10-03', 'actor', actor: $other))->toThrow(ValidationException::class);
});

test('shadow disabled leaves all new tables untouched and diagnostics never backfill', function () {
    $user = User::factory()->create(['base_currency_code' => 'EUR']);
    $party = TrackedItem::query()->create(['user_id' => $user->id, 'name' => 'Emanuela', 'slug' => 'emanuela', 'is_active' => true, 'is_shared_expense_counterpart' => true]);
    app(CreditDebtItemService::class)->create($user, shadowManualData($party));
    $report = app(CounterpartyReconciliationService::class)->compare($user, $party, 'EUR');
    expect(CounterpartyRelation::count())->toBe(0)->and(CounterpartyLedgerEntry::count())->toBe(0)
        ->and($report['legacy_balance_minor'])->toBe(20000)->and($report['ledger_balance_minor'])->toBe(0)->and($report['delta_minor'])->toBe(-20000)
        ->and(collect($report['anomalies'])->pluck('code'))->toContain('legacy_obligation_not_captured');
});

test('shadow manual lifecycle remains economically identical and preserves snapshots after legacy Transaction deletion', function () {
    config(['counterparty_ledger.shadow_manual_enabled' => true]);
    [$user, $party, $relation] = counterpartyDomain();
    $item = app(CreditDebtItemService::class)->create($user, shadowManualData($party));
    $account = createTestAccount($user);
    $payment = app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '100.00', 'account_id' => $account->id, 'paid_at' => today()->toDateString()]);
    $snapshot = CounterpartyPayment::query()->sole();
    $bankUuid = $payment->transaction->uuid;
    $report = app(CounterpartyReconciliationService::class)->inspect($relation);
    expect($report['legacy_balance_minor'])->toBe(10000)->and($report['ledger_balance_minor'])->toBe(10000)->and($report['delta_minor'])->toBe(0)
        ->and($report['internal_reconciliation']['consistent'])->toBeTrue()->and($report['payments'][0]['allocated_minor'])->toBe(10000)
        ->and(Transaction::count())->toBe(1)->and(CreditDebtPayment::count())->toBe(1)
        ->and(app(CounterpartyBalanceService::class)->balance($user, $party, 'EUR')['total_cents'])->toBe(10000);
    app(CreditDebtPaymentService::class)->delete($user, $payment);
    $report = app(CounterpartyReconciliationService::class)->inspect($relation);
    expect($report['ledger_balance_minor'])->toBe(20000)->and($report['delta_minor'])->toBe(0)->and(Transaction::withTrashed()->count())->toBe(0)
        ->and($snapshot->fresh()->transaction_uuid)->toBe($bankUuid)->and($snapshot->fresh()->transaction)->toBeNull()
        ->and(CounterpartyPayment::count())->toBe(1)->and(CounterpartyAllocationEntry::count())->toBe(2);
});

test('reconciliation detects offsetting source discrepancies even when aggregate balances agree', function () {
    [$user, $party, $relation] = counterpartyDomain();
    $first = app(CreditDebtItemService::class)->create($user, shadowManualData($party, amount: '250.00'));
    $second = app(CreditDebtItemService::class)->create($user, shadowManualData($party, amount: '250.00'));
    $service = app(CounterpartyLedgerService::class);
    $service->recognize($relation, SourceType::CREDIT_DEBT_ITEM, $first->uuid, 'first', 20000, '2026-10-01', 'first');
    $service->recognize($relation, SourceType::CREDIT_DEBT_ITEM, $second->uuid, 'second', 30000, '2026-10-01', 'second');
    $report = app(CounterpartyReconciliationService::class)->inspect($relation);
    expect($report['delta_minor'])->toBe(0)
        ->and(collect($report['anomalies'])->where('code', 'obligation_principal_mismatch')->count())->toBe(2)
        ->and(collect($report['anomalies'])->where('code', 'obligation_residual_mismatch')->count())->toBe(2)
        ->and(Transaction::count())->toBe(0);
});

test('shadow revisions and deletions append history without changing the obligation identity', function () {
    config(['counterparty_ledger.shadow_manual_enabled' => true]);
    [$user, $party, $relation] = counterpartyDomain();
    $service = app(CreditDebtItemService::class);
    $item = $service->create($user, [...shadowManualData($party), 'due_date' => '2026-10-31']);
    $obligation = CounterpartyObligation::query()->sole();
    $service->update($user, $item, ['total_amount' => '250.00', 'due_date' => null]);
    $projection = app(CounterpartyProjectionService::class);
    expect($projection->balance($relation))->toBe(25000)->and($projection->obligation($obligation)['due_date'])->toBeNull()
        ->and(CounterpartyObligation::query()->sole()->uuid)->toBe($obligation->uuid)->and($obligation->fresh()->recognized_minor)->toBe(20000);
    $service->delete($user, $item);
    expect($projection->balance($relation))->toBe(0)->and(CounterpartyLedgerEntry::count())->toBe(3)->and($projection->reconcile($relation)['consistent'])->toBeTrue()
        ->and(app(CounterpartyReconciliationService::class)->inspect($relation)['delta_minor'])->toBe(0);
});

test('enabling shadow does not import existing positions payments or new legacy associations', function () {
    [$user, $party, $relation] = counterpartyDomain();
    $item = CreditDebtItem::factory()->create(['user_id' => $user->id, 'total_amount' => '200.00', 'counterparty_id' => null]);
    config(['counterparty_ledger.shadow_manual_enabled' => true]);
    app(CreditDebtItemService::class)->update($user, $item, ['counterparty_id' => $party->id]);
    $account = createTestAccount($user);
    app(CreditDebtPaymentService::class)->create($user, $item, ['amount' => '100.00', 'account_id' => $account->id, 'paid_at' => today()->toDateString()]);
    expect(CounterpartyObligation::count())->toBe(0)->and(CounterpartyLedgerEntry::count())->toBe(0)->and(CounterpartyPayment::count())->toBe(0)->and(Transaction::count())->toBe(1);
    $report = app(CounterpartyReconciliationService::class)->inspect($relation);
    expect($report['legacy_balance_minor'])->toBe(10000)->and($report['delta_minor'])->toBe(-10000)
        ->and(collect($report['anomalies'])->pluck('code'))->toContain('legacy_obligation_not_captured', 'legacy_payment_not_captured');
});

test('shadow skips nonrepresentable legacy JPY fractions without rounding or breaking legacy writes', function () {
    config(['counterparty_ledger.shadow_manual_enabled' => true]);
    [$user, $party, $relation] = counterpartyDomain('JPY');
    $item = app(CreditDebtItemService::class)->create($user, shadowManualData($party, 'JPY', '200.50'));
    $report = app(CounterpartyReconciliationService::class)->inspect($relation);
    expect($item->total_amount)->toBe('200.50')->and(CounterpartyObligation::count())->toBe(0)->and(CounterpartyLedgerEntry::count())->toBe(0)
        ->and($report['legacy_balance_minor'])->toBeNull()->and($report['delta_minor'])->toBeNull()
        ->and(collect($report['anomalies'])->pluck('code'))->toContain('unrepresentable_legacy_amount');
});

test('diagnostics expose uncaptured shared sources rather than silently inventing identities or allocations', function () {
    config(['counterparty_ledger.shadow_manual_enabled' => true]);
    [$user, $party, $relation] = counterpartyDomain();
    app(CreditDebtItemService::class)->create($user, shadowManualData($party));
    $account = createTestAccount($user);
    userTransaction($user, $account, ['amount' => '100.00', 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
    $report = app(CounterpartyReconciliationService::class)->inspect($relation);
    expect($report['legacy_balance_minor'])->toBe(25000)->and($report['ledger_balance_minor'])->toBe(20000)->and($report['delta_minor'])->toBe(-5000)
        ->and($report['legacy_obligations'])->toHaveCount(2)->and($report['new_obligations'])->toHaveCount(1)
        ->and(collect($report['anomalies'])->pluck('code'))->toContain('legacy_obligation_not_captured');
});

test('real manual and shared documents can be represented without replacing the legacy balance service', function () {
    [$user, $party, $relation] = counterpartyDomain();
    $account = createTestAccount($user);
    $manual = CreditDebtItem::factory()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'total_amount' => '200.00']);
    $shared = userTransaction($user, $account, ['amount' => '100.00', 'shared_expense_counterparty_id' => $party->id, 'shared_expense_paid_by' => 'self', 'shared_expense_my_share_cents' => 5000, 'shared_expense_counterparty_share_cents' => 5000]);
    $bank = userTransaction($user, $account, ['amount' => '100.00', 'direction' => 'income']);
    $service = app(CounterpartyLedgerService::class);
    $a = $service->recognize($relation, SourceType::CREDIT_DEBT_ITEM, $manual->uuid, 'manual:'.$manual->uuid, 20000, '2026-10-01', 'manual');
    $b = $service->recognize($relation, SourceType::SHARED_TRANSACTION, $shared->uuid, 'shared:'.$shared->uuid, 5000, '2026-10-02', 'shared');
    $payment = $service->recordPayment($relation, PaymentDirection::RECEIVED, 10000, today()->toDateString(), $account, 'payment', $bank);
    $service->allocate($payment, $a, 10000, today()->toDateString(), 'allocate');
    $projection = app(CounterpartyProjectionService::class);
    expect($projection->balance($relation))->toBe(15000)->and($projection->remaining($a))->toBe(10000)->and($projection->remaining($b))->toBe(5000)
        ->and($projection->reconcile($relation)['consistent'])->toBeTrue()->and(Transaction::count())->toBe(2)
        ->and(app(CounterpartyBalanceService::class)->balance($user, $party, 'EUR')['total_cents'])->toBe(25000);
});

test('batch obligation projections match fresh reads with a fixed query budget including revisions and reversals', function () {
    [, , $relation] = counterpartyDomain();
    $ledger = app(CounterpartyLedgerService::class);
    $projection = app(CounterpartyProjectionService::class);
    $first = domainObligation($relation);
    $payment = domainPayment($relation);
    $allocation = $ledger->allocate($payment, $first, 7000, '2026-10-03', 'batch-allocation');
    $ledger->reverseAllocation($allocation, '2026-10-04', 'batch-reverse');
    $ledger->revise($first, 25000, '2026-10-05', 'batch-revision');
    foreach (range(1, 20) as $index) {
        domainObligation($relation, 10000, 'batch-'.$index);
    }
    $obligations = $relation->obligations()->get();
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $batched = $projection->obligations($obligations);
        expect(DB::getQueryLog())->toHaveCount(2);
    } finally {
        DB::disableQueryLog();
    }
    expect($batched->all())->toBe($obligations->map(fn ($obligation): array => $projection->obligation($obligation))->all());
    expect($batched->first()['remaining_minor'])->toBe(25000);
});

test('batch payment totals preserve documents allocations and reversals with a fixed query budget', function () {
    [, , $relation] = counterpartyDomain();
    $ledger = app(CounterpartyLedgerService::class);
    $projection = app(CounterpartyProjectionService::class);
    $obligation = domainObligation($relation);
    $payment = domainPayment($relation);
    $allocation = $ledger->allocate($payment, $obligation, 7000, '2026-10-03', 'totals-allocation');
    $ledger->reverseAllocation($allocation, '2026-10-04', 'totals-reverse-allocation');
    $ledger->reversePayment($payment, '2026-10-05', 'totals-reverse-payment');
    foreach (range(1, 20) as $index) {
        domainPayment($relation, 5000, key: 'totals-'.$index);
    }
    $payments = $relation->payments()->get();
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $batched = $projection->paymentTotals($payments);
        expect(DB::getQueryLog())->toHaveCount(2);
    } finally {
        DB::disableQueryLog();
    }
    foreach ($payments as $document) {
        expect($batched[$document->id])->toBe([
            'effective_minor' => $projection->effectivePayment($document),
            'allocated_minor' => $projection->allocatedToPayment($document),
            'unallocated_minor' => $projection->unallocated($document),
            'document' => $projection->paymentDocument($document),
        ]);
    }
});
