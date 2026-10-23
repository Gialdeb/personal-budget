<?php

namespace App\Services\Counterparties;

use App\Enums\CounterpartyLedgerEventEnum as EventType;
use App\Enums\CounterpartyObligationSourceEnum as SourceType;
use App\Enums\CounterpartyPaymentDirectionEnum as PaymentDirection;
use App\Enums\CreditDebtTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\CounterpartyAllocationEntry;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\CreditDebts\ManualEconomicSubjectResolver;
use App\Support\CurrencyMinorUnits;
use App\Support\MoneyAmount;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Internal shadow-domain operations; deliberately not exposed by routes or controllers. */
class CounterpartyLedgerService
{
    public function __construct(
        private readonly CounterpartyProjectionService $projections,
        private readonly AccessibleAccountsQuery $accounts,
    ) {}

    public function relation(User $user, TrackedItem $counterparty, string $currency): CounterpartyRelation
    {
        $currency = strtoupper($currency);
        $precision = CurrencyMinorUnits::precision($currency);

        return DB::transaction(function () use ($user, $counterparty, $currency, $precision): CounterpartyRelation {
            $party = TrackedItem::query()->ownedBy($user->id)->lockForUpdate()->find($counterparty->id);
            $this->require($party !== null, 'counterparty', 'The counterparty must be personal and owned by the user.');
            $existing = CounterpartyRelation::query()->where(['user_id' => $user->id, 'counterparty_id' => $party->id, 'currency_code' => $currency])->first();
            if ($existing !== null) {
                $this->require($existing->minor_unit === $precision, 'currency', 'Relation precision differs from the currency configuration.');

                return $existing;
            }
            $this->require(($party->is_active && $party->is_shared_expense_counterpart) || CounterpartyRelation::hasLegacyEvidence($party, $currency), 'counterparty', 'The counterparty must be enabled.');

            return CounterpartyRelation::query()->create(['user_id' => $user->id, 'counterparty_id' => $party->id, 'currency_code' => $currency, 'minor_unit' => $precision, 'migration_state' => 'shadow']);
        });
    }

    /** @param array<string, mixed> $metadata */
    public function recognize(CounterpartyRelation $relation, SourceType $sourceType, string $sourceIdentity, string $economicIdentity, int $signedMinor, string $economicDate, string $key, ?string $dueDate = null, array $metadata = [], ?User $actor = null): CounterpartyObligation
    {
        $payload = ['action' => 'recognize', 'source_type' => $sourceType->value, 'source_identity' => $sourceIdentity, 'economic_identity' => $economicIdentity, 'signed_minor' => $signedMinor, 'economic_date' => $economicDate, 'due_date' => $dueDate, 'metadata' => $metadata, 'actor_id' => $actor?->id];

        return $this->locked($relation, function (CounterpartyRelation $relation) use ($sourceType, $sourceIdentity, $economicIdentity, $signedMinor, $economicDate, $key, $dueDate, $metadata, $actor, $payload): CounterpartyObligation {
            if ($existing = $this->existingLedger($relation, $key, $payload)) {
                return $existing->obligation()->firstOrFail();
            }
            $this->require($signedMinor !== 0 && $signedMinor !== PHP_INT_MIN, 'amount', 'An obligation must have a nonzero representable principal.');
            $this->validateIdentity($sourceIdentity);
            $this->validateIdentity($economicIdentity);
            $this->validateDates($economicDate, $dueDate);
            $this->validateActor($relation, $actor);
            $this->require(! $relation->obligations()->where('economic_identity', $economicIdentity)->exists(), 'source', 'This economic obligation is already recognized. Use a revision.');
            $this->validateSource($relation, $sourceType, $sourceIdentity);
            $obligation = CounterpartyObligation::query()->create(['relation_id' => $relation->id, 'source_type' => $sourceType->value, 'source_identity' => $sourceIdentity, 'economic_identity' => $economicIdentity, 'recognized_minor' => $signedMinor, 'economic_date' => $economicDate, 'due_date' => $dueDate, 'metadata' => $metadata]);
            $snapshot = [...$metadata, 'source_type' => $sourceType->value, 'source_identity' => $sourceIdentity, 'recognized_minor' => $signedMinor, 'economic_date' => $economicDate, 'due_date' => $dueDate];
            $this->append($relation, $key, $payload, ['obligation_id' => $obligation->id, 'signed_minor' => $signedMinor, 'event_type' => EventType::OBLIGATION_RECOGNIZED, 'source_type' => $sourceType->value, 'source_identity' => $sourceIdentity, 'source_version' => 1, 'economic_date' => $economicDate, 'actor_id' => $actor?->id, 'metadata' => ['snapshot' => $snapshot, 'before_snapshot' => [...$snapshot, 'recognized_minor' => 0]]]);

            return $obligation;
        });
    }

    /** @param array<string, mixed> $metadata */
    public function revise(CounterpartyObligation $obligation, int $signedMinor, string $economicDate, string $key, ?SourceType $sourceType = null, ?string $sourceIdentity = null, ?string $dueDate = null, array $metadata = [], ?User $actor = null): CounterpartyLedgerEntry
    {
        $payload = ['action' => 'revise', 'obligation_id' => $obligation->id, 'signed_minor' => $signedMinor, 'source_type' => $sourceType?->value, 'source_identity' => $sourceIdentity, 'economic_date' => $economicDate, 'due_date' => $dueDate, 'metadata' => $metadata, 'actor_id' => $actor?->id];

        return $this->locked($obligation->relation, function (CounterpartyRelation $relation) use ($obligation, $signedMinor, $economicDate, $key, $sourceType, $sourceIdentity, $dueDate, $metadata, $actor, $payload): CounterpartyLedgerEntry {
            if ($existing = $this->existingLedger($relation, $key, $payload)) {
                return $existing;
            }
            $current = $this->projections->obligation($obligation);
            $sourceType ??= SourceType::from($current['source_type']);
            $sourceIdentity ??= $current['source_identity'];
            $this->validateSource($relation, $sourceType, $sourceIdentity, $obligation->id);
            $this->validateActor($relation, $actor);
            $this->validateDates($economicDate, $dueDate);
            $this->validateIdentity($sourceIdentity);
            $this->require($signedMinor !== PHP_INT_MIN && ($signedMinor === 0 || ($signedMinor > 0) === (($this->projections->recognized($obligation) ?: $obligation->recognized_minor) > 0)), 'amount', 'A revision cannot invert the obligation direction.');
            $this->require(abs($signedMinor) >= $this->projections->allocatedToObligation($obligation), 'amount', 'Release allocations before reducing the principal below them.');
            $previous = $this->projections->recognized($obligation);
            $version = (int) $obligation->ledgerEntries()->whereIn('event_type', [EventType::OBLIGATION_RECOGNIZED->value, EventType::OBLIGATION_REVISED->value])->max('source_version') + 1;
            $snapshot = [...$metadata, 'source_type' => $sourceType->value, 'source_identity' => $sourceIdentity, 'recognized_minor' => $signedMinor, 'economic_date' => $economicDate, 'due_date' => $dueDate];

            return $this->append($relation, $key, $payload, ['obligation_id' => $obligation->id, 'signed_minor' => CurrencyMinorUnits::add($signedMinor, -$previous), 'event_type' => EventType::OBLIGATION_REVISED, 'source_type' => $sourceType->value, 'source_identity' => $sourceIdentity, 'source_version' => $version, 'economic_date' => $economicDate, 'actor_id' => $actor?->id, 'metadata' => ['snapshot' => $snapshot, 'before_snapshot' => $this->projections->obligation($obligation)]]);
        });
    }

    /** @param array<string, mixed> $metadata */
    public function recordPayment(CounterpartyRelation $relation, PaymentDirection $direction, int $amountMinor, string $economicDate, Account $account, string $key, ?Transaction $transaction = null, ?string $legacySourceType = null, ?string $legacySourceIdentity = null, ?CounterpartyPayment $revisionOf = null, array $metadata = [], ?User $actor = null, ?CounterpartyPayment $returnOf = null): CounterpartyPayment
    {
        $payload = ['action' => 'payment', 'direction' => $direction->value, 'amount_minor' => $amountMinor, 'economic_date' => $economicDate, 'account_id' => $account->id, 'account_uuid' => $account->uuid, 'transaction_id' => $transaction?->id, 'transaction_uuid' => $transaction?->uuid, 'legacy_source_type' => $legacySourceType, 'legacy_source_identity' => $legacySourceIdentity, 'revision_of_id' => $revisionOf?->id, 'metadata' => $metadata, 'actor_id' => $actor?->id];
        if ($returnOf !== null) {
            $payload['return_of_id'] = $returnOf->id;
        }

        return $this->locked($relation, function (CounterpartyRelation $relation) use ($returnOf, $direction, $amountMinor, $economicDate, $account, $key, $transaction, $legacySourceType, $legacySourceIdentity, $revisionOf, $metadata, $actor, $payload): CounterpartyPayment {
            if ($existing = $this->existingLedger($relation, $key, $payload)) {
                return $existing->payment()->firstOrFail();
            }
            $this->require($amountMinor > 0, 'amount', 'A payment must be positive.');
            $this->validateActor($relation, $actor);
            $this->validateDates($economicDate);
            $account = $this->accounts->editable($relation->user_id)->where('accounts.id', $account->id)->first();
            $this->require($account !== null && $account->currency_code === $relation->currency_code, 'account', 'Select an editable account in the relation currency.');
            $account = Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $this->require($account->currency_code === $relation->currency_code, 'account', 'Account currency changed before the payment lock.');
            if ($transaction !== null) {
                $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
                $expectedDirection = $direction === PaymentDirection::RECEIVED ? TransactionDirectionEnum::INCOME : TransactionDirectionEnum::EXPENSE;
                $this->require($transaction->account_id === $account->id && $transaction->currency === $relation->currency_code && $transaction->direction === $expectedDirection
                    && $transaction->status === TransactionStatusEnum::CONFIRMED && ! $transaction->is_transfer
                    && CurrencyMinorUnits::fromDecimal((string) $transaction->amount, $relation->currency_code) === $amountMinor
                    && ! $transaction->refundTransaction()->exists(), 'transaction', 'Transaction does not match the effective payment.');
            }
            if ($revisionOf !== null) {
                $this->require($revisionOf->relation_id === $relation->id && $this->projections->effectivePayment($revisionOf) === 0, 'revision', 'Reverse the previous payment in this relation before revising it.');
            }
            $this->require($returnOf === null || ($returnOf->relation_id === $relation->id && $returnOf->return_of_id === null && $returnOf->direction !== $direction), 'return', 'Restituzione incompatibile.');
            $this->require(($legacySourceType === null) === ($legacySourceIdentity === null), 'source', 'Legacy source type and identity must be supplied together.');
            $payment = CounterpartyPayment::query()->create(['relation_id' => $relation->id, 'direction' => $direction, 'amount_minor' => $amountMinor, 'economic_date' => $economicDate, 'account_id' => $account->id, 'account_uuid' => $account->uuid, 'transaction_id' => $transaction?->id, 'transaction_uuid' => $transaction?->uuid, 'status' => 'recorded', 'legacy_source_type' => $legacySourceType, 'legacy_source_identity' => $legacySourceIdentity, 'revision_of_id' => $revisionOf?->id, 'return_of_id' => $returnOf?->id, 'idempotency_key' => $key, 'payload_hash' => $this->hash($payload), 'metadata' => $metadata]);
            $this->append($relation, $key, $payload, ['payment_id' => $payment->id, 'signed_minor' => $amountMinor * $direction->balanceSign(), 'event_type' => EventType::PAYMENT_RECORDED, 'source_type' => $legacySourceType ?? 'counterparty_payment', 'source_identity' => $legacySourceIdentity ?? $payment->uuid, 'source_version' => 1, 'economic_date' => $economicDate, 'transaction_id' => $transaction?->id, 'transaction_uuid' => $transaction?->uuid, 'actor_id' => $actor?->id, 'metadata' => $metadata]);

            return $payment;
        });
    }

    /** Replace a shared principal with an explicit reversal and a new full recognition. @param array<string, mixed> $metadata */
    public function replaceSharedRecognition(CounterpartyObligation $obligation, SourceType $sourceType, string $sourceIdentity, int $signedMinor, string $economicDate, string $key, array $metadata = []): void
    {
        $this->locked($obligation->relation, function (CounterpartyRelation $relation) use ($obligation, $sourceType, $sourceIdentity, $signedMinor, $economicDate, $key, $metadata): void {
            $payload = ['action' => 'replace_shared', 'obligation_id' => $obligation->id, 'source_type' => $sourceType->value, 'source_identity' => $sourceIdentity, 'signed_minor' => $signedMinor, 'economic_date' => $economicDate, 'metadata' => $metadata];
            if ($this->existingLedger($relation, $key, $payload) !== null) {
                return;
            }
            $this->require(in_array($obligation->source_type, [SourceType::SHARED_TRANSACTION, SourceType::SHARED_LEDGER], true), 'source', 'Only shared obligations use replacement recognition.');
            $this->require(in_array($sourceType, [SourceType::SHARED_TRANSACTION, SourceType::SHARED_LEDGER], true), 'source', 'Select a shared source.');
            $allocated = $this->projections->allocatedToObligation($obligation);
            $this->require($signedMinor !== PHP_INT_MIN && ($allocated === 0 || ($allocated <= abs($signedMinor) && ($signedMinor > 0) === ($this->projections->recognized($obligation) > 0))), 'amount', 'Shared replacement must preserve the direction and cover existing allocations.');
            $this->validateDates($economicDate);
            $this->validateSource($relation, $sourceType, $sourceIdentity, $obligation->id);
            $current = $this->projections->recognized($obligation);
            $active = $obligation->ledgerEntries()->whereIn('event_type', [EventType::OBLIGATION_RECOGNIZED, EventType::OBLIGATION_REVISED])
                ->whereNotIn('id', CounterpartyLedgerEntry::query()->whereNotNull('reversal_of_id')->select('reversal_of_id'))->orderByDesc('id')->get();
            $this->require(CurrencyMinorUnits::sum($active->pluck('signed_minor')) === $current, 'history', 'Shared history does not reconcile with its active recognition events.');
            foreach ($active as $entry) {
                $this->append($relation, $this->childKey($key, 'reverse:'.$entry->uuid), $this->reversalPayload($entry, $economicDate, null, 'Shared source revision.'),
                    ['obligation_id' => $obligation->id, 'signed_minor' => -$entry->signed_minor, 'event_type' => EventType::REVERSAL, 'source_type' => 'ledger_entry', 'source_identity' => $entry->uuid, 'source_version' => 1, 'economic_date' => $economicDate, 'reversal_of_id' => $entry->id, 'reason' => 'Shared source revision.', 'metadata' => ['reversed_entry' => $entry->uuid, 'snapshot' => $entry->metadata['before_snapshot'] ?? []]]);
            }
            $version = (int) $obligation->ledgerEntries()->whereIn('event_type', [EventType::OBLIGATION_RECOGNIZED, EventType::OBLIGATION_REVISED])->max('source_version') + 1;
            $snapshot = [...$metadata, 'source_type' => $sourceType->value, 'source_identity' => $sourceIdentity, 'recognized_minor' => $signedMinor, 'economic_date' => $economicDate, 'due_date' => null];
            $this->append($relation, $key, $payload, ['obligation_id' => $obligation->id, 'signed_minor' => $signedMinor, 'event_type' => EventType::OBLIGATION_REVISED, 'source_type' => $sourceType->value, 'source_identity' => $sourceIdentity, 'source_version' => $version, 'economic_date' => $economicDate, 'metadata' => ['snapshot' => $snapshot, 'before_snapshot' => [...$snapshot, 'recognized_minor' => 0]]]);
        });
    }

    /** Import/synchronize a documented legacy payment without creating bank movements. @param array<string, mixed> $metadata */
    public function synchronizeLegacyPayment(CounterpartyRelation $relation, CreditDebtPayment|SharedExpenseSettlement $source, int $effectiveMinor, string $key, array $metadata = []): CounterpartyPayment
    {
        return $this->locked($relation, function (CounterpartyRelation $relation) use ($source, $effectiveMinor, $key, $metadata): CounterpartyPayment {
            $manual = $source instanceof CreditDebtPayment;
            $sourceType = $manual ? 'credit_debt_payment' : 'shared_expense_settlement';
            $transaction = Transaction::withTrashed()->with(['account', 'refundTransaction'])->find($source->transaction_id);
            $this->require($transaction !== null && $transaction->account !== null, 'transaction', 'Legacy bank document is missing.');
            $account = $transaction->account;
            $direction = $manual
                ? ($source->item->type === CreditDebtTypeEnum::CREDIT ? PaymentDirection::RECEIVED : PaymentDirection::SENT)
                : ($source->direction === 'counterparty_to_me' ? PaymentDirection::RECEIVED : PaymentDirection::SENT);
            $partyId = $manual ? $source->item->counterparty_id : $source->counterparty_id;
            $amount = $manual ? CurrencyMinorUnits::fromDecimal((string) $source->amount, $relation->currency_code) : CurrencyMinorUnits::fromDecimal(MoneyAmount::decimal($source->amount_cents), $relation->currency_code);
            $this->require($source->user_id === $relation->user_id && $partyId === $relation->counterparty_id, 'source', 'Legacy payment does not belong to this relation.');
            $this->require($account->currency_code === $relation->currency_code && $transaction->currency === $relation->currency_code
                && $transaction->user_id === $account->user_id && ! $transaction->is_transfer
                && $transaction->direction === ($direction === PaymentDirection::RECEIVED ? TransactionDirectionEnum::INCOME : TransactionDirectionEnum::EXPENSE)
                && CurrencyMinorUnits::fromDecimal((string) $transaction->amount, $relation->currency_code) === $amount, 'transaction', 'Legacy payment and Transaction disagree.');
            $this->require(in_array($account->id, $this->accounts->ids($relation->user_id), true), 'account', 'Legacy account is not accessible to this relation owner.');
            $this->require($amount > 0 && $effectiveMinor >= 0 && $effectiveMinor <= $amount, 'amount', 'Invalid effective legacy payment amount.');
            $this->validateDates($manual ? $source->paid_at->toDateString() : $source->settled_at->toDateString());
            $date = $manual ? $source->paid_at->toDateString() : $source->settled_at->toDateString();
            $payload = ['action' => 'legacy_payment', 'source_type' => $sourceType, 'source_identity' => $source->uuid, 'direction' => $direction->value, 'amount_minor' => $amount, 'effective_minor' => $effectiveMinor, 'economic_date' => $date, 'account_uuid' => $account->uuid, 'transaction_uuid' => $transaction->uuid, 'metadata' => $metadata];
            if ($existing = $this->existingLedger($relation, $key, $payload)) {
                return $existing->payment()->firstOrFail();
            }
            $payment = CounterpartyPayment::query()->where('legacy_source_type', $sourceType)->where('legacy_source_identity', $source->uuid)->first();
            $this->require($payment === null || ($payment->relation_id === $relation->id && $payment->direction === $direction), 'source', 'Legacy payment relation or direction changed.');
            $this->require($payment === null || $payment->transaction_uuid === $transaction->uuid, 'transaction', 'Replacing the bank identity of an imported payment requires explicit review.');
            $this->require(! CounterpartyPayment::query()->where('transaction_id', $transaction->id)->when($payment !== null, fn ($query) => $query->where('id', '!=', $payment->id))->exists(), 'transaction', 'This Transaction already backs another payment.');
            if ($payment === null) {
                $payment = CounterpartyPayment::query()->create(['relation_id' => $relation->id, 'direction' => $direction, 'amount_minor' => $amount, 'economic_date' => $date, 'account_id' => $account->id, 'account_uuid' => $account->uuid, 'transaction_id' => $transaction->id, 'transaction_uuid' => $transaction->uuid, 'status' => 'recorded', 'legacy_source_type' => $sourceType, 'legacy_source_identity' => $source->uuid, 'idempotency_key' => $key, 'payload_hash' => $this->hash($payload), 'metadata' => $metadata]);
            } else {
                $this->require($this->projections->allocatedToPayment($payment) === 0, 'allocation', 'Release known allocations before replacing a legacy payment.');
                $active = $payment->ledgerEntries()->where('event_type', EventType::PAYMENT_RECORDED)->whereNotIn('id', CounterpartyLedgerEntry::query()->whereNotNull('reversal_of_id')->select('reversal_of_id'))->get();
                $this->require($active->count() <= 1, 'history', 'Legacy payment has ambiguous active history.');
                if ($active->isNotEmpty()) {
                    $this->reverseLedgerLocked($relation, $active->first(), $date, $this->childKey($key, 'reverse'), null, 'Legacy payment revision or cancellation.');
                }
            }
            $version = (int) $payment->ledgerEntries()->where('event_type', EventType::PAYMENT_RECORDED)->max('source_version') + 1;
            $this->append($relation, $key, $payload, ['payment_id' => $payment->id, 'signed_minor' => $effectiveMinor * $direction->balanceSign(), 'event_type' => EventType::PAYMENT_RECORDED, 'source_type' => $sourceType, 'source_identity' => $source->uuid, 'source_version' => $version, 'economic_date' => $date, 'transaction_id' => $transaction->id, 'transaction_uuid' => $transaction->uuid, 'metadata' => [...$metadata, 'legacy_snapshot' => $payload]]);

            return $payment;
        });
    }

    public function allocate(CounterpartyPayment $payment, CounterpartyObligation $obligation, int $amountMinor, string $economicDate, string $key, ?User $actor = null, ?string $reason = null): CounterpartyAllocationEntry
    {
        $payload = ['action' => 'allocate', 'payment_id' => $payment->id, 'obligation_id' => $obligation->id, 'amount_minor' => $amountMinor, 'economic_date' => $economicDate, 'actor_id' => $actor?->id, 'reason' => $reason];

        return $this->locked($payment->relation, function (CounterpartyRelation $relation) use ($payment, $obligation, $amountMinor, $economicDate, $key, $actor, $reason, $payload): CounterpartyAllocationEntry {
            if ($existing = $this->existingAllocation($relation, $key, $payload)) {
                return $existing;
            }
            $this->validateActor($relation, $actor);
            $this->validateDates($economicDate);
            $this->require($payment->return_of_id === null, 'allocation', 'Le restituzioni restano collegate al pagamento originale e non sono allocabili separatamente.');
            $this->require($obligation->relation_id === $relation->id, 'relation', 'Payment and obligation must share relation and currency.');
            $this->require(($payment->direction === PaymentDirection::RECEIVED) === (($this->projections->recognized($obligation) ?: $obligation->recognized_minor) > 0), 'direction', 'Payment direction is incompatible with the obligation.');
            $this->require($amountMinor > 0 && $amountMinor <= $this->projections->unallocated($payment) - app(CounterpartyAuthority::class)->returned($payment), 'amount', 'Allocation exceeds the effective payment.');
            $this->require($amountMinor <= $this->projections->remaining($obligation), 'amount', 'Allocation exceeds the obligation residual.');

            return $this->appendAllocation($relation, $key, $payload, ['payment_id' => $payment->id, 'obligation_id' => $obligation->id, 'amount_minor' => $amountMinor, 'economic_date' => $economicDate, 'actor_id' => $actor?->id, 'reason' => $reason]);
        });
    }

    public function reverseAllocation(CounterpartyAllocationEntry $entry, string $economicDate, string $key, ?User $actor = null, ?string $reason = null): CounterpartyAllocationEntry
    {
        $this->require($entry->compensation_entry_id === null, 'compensation', 'Reverse both compensation legs together.');

        return $this->locked($entry->relation, fn (CounterpartyRelation $relation): CounterpartyAllocationEntry => $this->reverseAllocationLocked($relation, $entry, $economicDate, $key, $actor, $reason));
    }

    public function reversePayment(CounterpartyPayment $payment, string $economicDate, string $key, ?User $actor = null, ?string $reason = null): CounterpartyLedgerEntry
    {
        return $this->locked($payment->relation, function (CounterpartyRelation $relation) use ($payment, $economicDate, $key, $actor, $reason): CounterpartyLedgerEntry {
            $retry = $relation->ledgerEntries()->where('idempotency_key', $key)->first();
            if ($retry !== null) {
                $original = CounterpartyLedgerEntry::query()->findOrFail($retry->reversal_of_id);
                $this->require($original->payment_id === $payment->id, 'idempotency_key', 'Payment reversal key collision.');

                return $this->existingLedger($relation, $key, $this->reversalPayload($original, $economicDate, $actor, $reason));
            }
            $original = $payment->ledgerEntries()->where('event_type', EventType::PAYMENT_RECORDED)->whereNotIn('id', CounterpartyLedgerEntry::query()->whereNotNull('reversal_of_id')->select('reversal_of_id'))->orderByDesc('id')->firstOrFail();
            $payload = $this->reversalPayload($original, $economicDate, $actor, $reason);
            if ($existing = $this->existingLedger($relation, $key, $payload)) {
                return $existing;
            }
            foreach ($this->projections->activeAllocations($payment) as $allocation) {
                $this->reverseAllocationLocked($relation, $allocation, $economicDate, $this->childKey($key, $allocation->uuid), $actor, $reason);
            }

            return $this->reverseLedgerLocked($relation, $original, $economicDate, $key, $actor, $reason);
        });
    }

    public function compensate(CounterpartyObligation $positive, CounterpartyObligation $negative, int $amountMinor, string $economicDate, string $key, ?User $actor = null, ?string $reason = null): CounterpartyLedgerEntry
    {
        $payload = ['action' => 'compensate', 'positive_id' => $positive->id, 'negative_id' => $negative->id, 'amount_minor' => $amountMinor, 'economic_date' => $economicDate, 'actor_id' => $actor?->id, 'reason' => $reason];

        return $this->locked($positive->relation, function (CounterpartyRelation $relation) use ($positive, $negative, $amountMinor, $economicDate, $key, $actor, $reason, $payload): CounterpartyLedgerEntry {
            if ($existing = $this->existingLedger($relation, $key, $payload)) {
                return $existing;
            }
            $this->validateActor($relation, $actor);
            $this->validateDates($economicDate);
            $this->require($negative->relation_id === $relation->id && $this->projections->recognized($positive) > 0 && $this->projections->recognized($negative) < 0, 'obligations', 'Compensation requires opposite obligations in the same relation.');
            $this->require($amountMinor > 0 && $amountMinor <= min($this->projections->remaining($positive), $this->projections->remaining($negative)), 'amount', 'Compensation exceeds a residual.');
            $event = $this->append($relation, $key, $payload, ['signed_minor' => 0, 'event_type' => EventType::COMPENSATION, 'source_type' => 'compensation', 'source_identity' => $key, 'source_version' => 1, 'economic_date' => $economicDate, 'actor_id' => $actor?->id, 'reason' => $reason, 'metadata' => ['amount_minor' => $amountMinor, 'obligations' => [$positive->uuid, $negative->uuid]]]);
            foreach ([$positive, $negative] as $obligation) {
                $this->appendAllocation($relation, $this->childKey($key, $obligation->uuid), $payload, ['compensation_entry_id' => $event->id, 'obligation_id' => $obligation->id, 'amount_minor' => $amountMinor, 'economic_date' => $economicDate, 'actor_id' => $actor?->id, 'reason' => $reason]);
            }

            return $event;
        });
    }

    public function reverseCompensation(CounterpartyLedgerEntry $entry, string $economicDate, string $key, ?User $actor = null, ?string $reason = null): CounterpartyLedgerEntry
    {
        $this->require($entry->event_type === EventType::COMPENSATION, 'event', 'Select a compensation event.');

        return $this->locked($entry->relation, function (CounterpartyRelation $relation) use ($entry, $economicDate, $key, $actor, $reason): CounterpartyLedgerEntry {
            if ($existing = $this->existingLedger($relation, $key, $this->reversalPayload($entry, $economicDate, $actor, $reason))) {
                return $existing;
            }
            foreach ($this->projections->activeAllocations($entry) as $allocation) {
                $this->reverseAllocationLocked($relation, $allocation, $economicDate, $this->childKey($key, $allocation->uuid), $actor, $reason);
            }

            return $this->reverseLedgerLocked($relation, $entry, $economicDate, $key, $actor, $reason);
        });
    }

    public function reverseEntry(CounterpartyLedgerEntry $entry, string $economicDate, string $key, ?User $actor = null, ?string $reason = null): CounterpartyLedgerEntry
    {
        $this->require($entry->event_type !== EventType::COMPENSATION, 'event', 'Reverse compensation using its paired lifecycle.');

        return $this->locked($entry->relation, fn (CounterpartyRelation $relation): CounterpartyLedgerEntry => $this->reverseLedgerLocked($relation, $entry, $economicDate, $key, $actor, $reason));
    }

    private function reverseLedgerLocked(CounterpartyRelation $relation, CounterpartyLedgerEntry $entry, string $economicDate, string $key, ?User $actor, ?string $reason): CounterpartyLedgerEntry
    {
        $payload = $this->reversalPayload($entry, $economicDate, $actor, $reason);
        if ($existing = $this->existingLedger($relation, $key, $payload)) {
            return $existing;
        }
        $this->validateActor($relation, $actor);
        $this->validateDates($economicDate);
        $this->require($entry->event_type !== EventType::REVERSAL && ! CounterpartyLedgerEntry::query()->where('reversal_of_id', $entry->id)->exists(), 'reversal', 'This event is already reversed or is itself a reversal.');
        if ($entry->obligation_id !== null) {
            $obligation = $entry->obligation;
            $this->require($obligation->ledgerEntries()->orderByDesc('id')->value('id') === $entry->id, 'reversal', 'Only the latest obligation event can be reversed. Use an explicit revision for earlier corrections.');
            $next = CurrencyMinorUnits::add($this->projections->recognized($obligation), -$entry->signed_minor);
            $this->require(($next === 0 || ($next > 0) === (($this->projections->recognized($obligation) ?: $obligation->recognized_minor) > 0)) && abs($next) >= $this->projections->allocatedToObligation($obligation), 'amount', 'Release allocations before reversing this obligation event.');
        }
        if ($entry->payment_id !== null) {
            $next = CurrencyMinorUnits::add($this->projections->effectivePayment($entry->payment), -$entry->signed_minor * $entry->payment->direction->balanceSign());
            $this->require($next >= $this->projections->allocatedToPayment($entry->payment), 'amount', 'Release payment allocations before reversing its event.');
        }

        return $this->append($relation, $key, $payload, ['obligation_id' => $entry->obligation_id, 'payment_id' => $entry->payment_id, 'signed_minor' => -$entry->signed_minor, 'event_type' => EventType::REVERSAL, 'source_type' => 'ledger_entry', 'source_identity' => $entry->uuid, 'source_version' => 1, 'economic_date' => $economicDate, 'reversal_of_id' => $entry->id, 'transaction_id' => $entry->transaction_id, 'transaction_uuid' => $entry->transaction_uuid, 'actor_id' => $actor?->id, 'reason' => $reason, 'metadata' => ['reversed_entry' => $entry->uuid, 'snapshot' => $entry->metadata['before_snapshot'] ?? $entry->metadata]]);
    }

    private function reverseAllocationLocked(CounterpartyRelation $relation, CounterpartyAllocationEntry $entry, string $economicDate, string $key, ?User $actor, ?string $reason): CounterpartyAllocationEntry
    {
        $payload = ['action' => 'reverse_allocation', 'allocation_id' => $entry->id, 'economic_date' => $economicDate, 'actor_id' => $actor?->id, 'reason' => $reason];
        if ($existing = $this->existingAllocation($relation, $key, $payload)) {
            return $existing;
        }
        $this->validateActor($relation, $actor);
        $this->validateDates($economicDate);
        $this->require($entry->reversal_of_id === null && ! CounterpartyAllocationEntry::query()->where('reversal_of_id', $entry->id)->exists(), 'reversal', 'This allocation is already reversed.');

        return $this->appendAllocation($relation, $key, $payload, ['payment_id' => $entry->payment_id, 'compensation_entry_id' => $entry->compensation_entry_id, 'obligation_id' => $entry->obligation_id, 'amount_minor' => -$entry->amount_minor, 'economic_date' => $economicDate, 'reversal_of_id' => $entry->id, 'actor_id' => $actor?->id, 'reason' => $reason]);
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $data */
    private function append(CounterpartyRelation $relation, string $key, array $payload, array $data): CounterpartyLedgerEntry
    {
        $this->validateIdentity($key);
        $this->require(! $relation->ledgerEntries()->where('source_type', $data['source_type'])->where('source_identity', $data['source_identity'])->where('source_version', $data['source_version'])->where('event_type', $data['event_type'])->exists(), 'source', 'This source version already contributes to the ledger.');

        return CounterpartyLedgerEntry::query()->create([...$data, 'relation_id' => $relation->id, 'currency_code' => $relation->currency_code, 'idempotency_key' => $key, 'payload_hash' => $this->hash($payload), 'recorded_at' => now()]);
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $data */
    private function appendAllocation(CounterpartyRelation $relation, string $key, array $payload, array $data): CounterpartyAllocationEntry
    {
        $this->validateIdentity($key);

        return CounterpartyAllocationEntry::query()->create([...$data, 'relation_id' => $relation->id, 'currency_code' => $relation->currency_code, 'idempotency_key' => $key, 'payload_hash' => $this->hash($payload), 'recorded_at' => now()]);
    }

    /** @param array<string, mixed> $payload */
    private function existingLedger(CounterpartyRelation $relation, string $key, array $payload): ?CounterpartyLedgerEntry
    {
        $existing = $relation->ledgerEntries()->where('idempotency_key', $key)->first();
        $this->require($existing === null || hash_equals($existing->payload_hash, $this->hash($payload)), 'idempotency_key', 'Idempotency key collision with a different payload.');

        return $existing;
    }

    /** @param array<string, mixed> $payload */
    private function existingAllocation(CounterpartyRelation $relation, string $key, array $payload): ?CounterpartyAllocationEntry
    {
        $existing = $relation->allocationEntries()->where('idempotency_key', $key)->first();
        $this->require($existing === null || hash_equals($existing->payload_hash, $this->hash($payload)), 'idempotency_key', 'Idempotency key collision with a different allocation.');

        return $existing;
    }

    /** @return array<string, mixed> */
    private function reversalPayload(CounterpartyLedgerEntry $entry, string $date, ?User $actor, ?string $reason): array
    {
        return ['action' => 'reverse_ledger', 'entry_id' => $entry->id, 'economic_date' => $date, 'actor_id' => $actor?->id, 'reason' => $reason];
    }

    private function locked(CounterpartyRelation $relation, Closure $operation): mixed
    {
        return DB::transaction(function () use ($relation, $operation): mixed {
            TrackedItem::query()->whereKey($relation->counterparty_id)->lockForUpdate()->firstOrFail();
            $relation = CounterpartyRelation::query()->lockForUpdate()->findOrFail($relation->id);
            $party = $relation->counterparty;
            $this->require($party !== null && $party->user_id === $relation->user_id && $party->account_id === null, 'relation', 'Invalid economic ownership.');
            $this->require($relation->minor_unit === CurrencyMinorUnits::precision($relation->currency_code), 'currency', 'Currency precision changed after relation creation.');

            return $operation($relation);
        });
    }

    private function validateActor(CounterpartyRelation $relation, ?User $actor): void
    {
        $this->require($actor === null || $actor->id === $relation->user_id, 'actor', 'The actor does not own this personal relation.');
    }

    private function validateIdentity(string $identity): void
    {
        $this->require($identity !== '' && strlen($identity) <= 191, 'identity', 'Use a nonempty identity of at most 191 bytes.');
    }

    private function validateSource(CounterpartyRelation $relation, SourceType $sourceType, string $sourceIdentity, ?int $obligationId = null): void
    {
        if ($sourceType === SourceType::CREDIT_DEBT_ITEM) {
            $item = CreditDebtItem::withTrashed()->where('uuid', $sourceIdentity)->lockForUpdate()->first();
            $this->require($item !== null && app(ManualEconomicSubjectResolver::class)->consistent($item)
                && $item->user_id === $relation->user_id && $item->counterparty_id === $relation->counterparty_id && $item->currency_code === $relation->currency_code, 'source', 'Manual source economic subject does not match its relation.');
            $this->require(! CounterpartyObligation::query()->where('source_type', $sourceType->value)->where('source_identity', $sourceIdentity)->when($obligationId !== null, fn ($query) => $query->where('id', '!=', $obligationId))->exists(), 'source', 'Manual source already belongs to a ledger obligation.');
        }
        $query = $relation->ledgerEntries()->where('source_type', $sourceType->value)->where('source_identity', $sourceIdentity)->whereNotNull('obligation_id');
        if ($obligationId !== null) {
            $query->where('obligation_id', '!=', $obligationId);
        }
        $this->require(! $query->exists(), 'source', 'This source already belongs to an economic obligation.');
    }

    private function validateDates(string $economicDate, ?string $dueDate = null): void
    {
        validator(['economic_date' => $economicDate, 'due_date' => $dueDate], ['economic_date' => ['required', 'date_format:Y-m-d'], 'due_date' => ['nullable', 'date_format:Y-m-d']])->validate();
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        $canonical = function (mixed $value) use (&$canonical): mixed {
            if (is_float($value)) {
                throw ValidationException::withMessages(['payload' => __('form_errors.floating_point_values_are_not_allowed_in_counterparty_payloads')]);
            }
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($canonical, $value);
        };

        return hash('sha256', json_encode($canonical($payload), JSON_THROW_ON_ERROR));
    }

    private function childKey(string $key, string $identity): string
    {
        return hash('sha256', $key.':'.$identity);
    }

    private function require(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
