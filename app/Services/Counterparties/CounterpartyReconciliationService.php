<?php

namespace App\Services\Counterparties;

use App\Enums\CreditDebtTypeEnum;
use App\Models\CounterpartyAllocationEntry;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
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
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\SharedExpenses\SharedExpenseAllocation;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Support\CurrencyMinorUnits;
use App\Support\MoneyAmount;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OverflowException;

/** Read-only comparison; discrepancies are reported, never repaired or imported. */
class CounterpartyReconciliationService
{
    public function __construct(
        private readonly CounterpartyProjectionService $projections,
        private readonly CounterpartyBalanceService $legacyBalances,
        private readonly AccessibleAccountsQuery $accounts,
        private readonly SharedExpenseSettlementService $sharedBalances,
        private readonly SharedExpenseAllocation $shares,
        private readonly SharedSourceProjector $sourceProjector,
    ) {}

    /** @return array<string, mixed> */
    public function inspect(CounterpartyRelation $relation): array
    {
        return $this->compare($relation->user, $relation->counterparty, $relation->currency_code);
    }

    /** @return array<string, mixed> */
    public function compare(User $user, TrackedItem $counterparty, string $currency): array
    {
        if ($counterparty->user_id !== $user->id || $counterparty->account_id !== null) {
            throw ValidationException::withMessages(['counterparty' => 'Invalid economic ownership.']);
        }
        $relation = CounterpartyRelation::query()->where(['user_id' => $user->id, 'counterparty_id' => $counterparty->id, 'currency_code' => $currency])->first();
        $anomalies = [];
        $convert = function (string $amount, string $source) use ($currency, &$anomalies): ?int {
            try {
                return CurrencyMinorUnits::fromDecimal($amount, $currency);
            } catch (InvalidArgumentException|OverflowException $exception) {
                $anomalies[] = ['code' => 'unrepresentable_legacy_amount', 'source' => $source, 'amount' => $amount, 'message' => $exception->getMessage()];

                return null;
            }
        };
        $legacy = $this->legacyBalances->legacyBalance($user, $counterparty, $currency);
        $legacyMinor = $convert(MoneyAmount::decimal($legacy['total_cents']), 'legacy_balance');
        $manual = CreditDebtItem::query()->forUser($user)->where('counterparty_id', $counterparty->id)->where('currency_code', $currency)->with('payments.transaction')->get();
        $legacyObligations = $manual->map(fn (CreditDebtItem $item): array => ['source_type' => 'credit_debt_item', 'source_identity' => $item->uuid,
            'recognized_minor' => $convert((string) $item->total_amount, $item->uuid), 'remaining_minor' => $convert(MoneyAmount::decimal($item->legacyRemainingCents()), $item->uuid),
            'direction_sign' => $item->type === CreditDebtTypeEnum::CREDIT ? 1 : -1, 'due_date' => $item->due_date?->toDateString()])->all();
        $transactions = Transaction::query()->whereIn('account_id', $this->accounts->ids($user))->where('shared_expense_counterparty_id', $counterparty->id)->where('currency', $currency)->with('refundTransaction')->get();
        $sharedSources = collect($transactions->all())->concat(SharedExpenseLedgerEntry::query()->where('user_id', $user->id)->where('counterparty_id', $counterparty->id)->where('currency', $currency)->get());
        $knownComponents = [];
        foreach ($sharedSources as $source) {
            try {
                $data = $this->sourceProjector->describe($source);
                if (isset($data['metadata']['component_snapshots'])) {
                    if (isset($knownComponents[$data['economic_identity']])) {
                        continue;
                    }
                    $knownComponents[$data['economic_identity']] = true;
                }
                $legacyObligations[] = ['source_type' => $data['source_type'], 'source_identity' => $source->uuid, 'economic_identity' => $data['economic_identity'], 'signed_minor' => $data['signed_minor'], 'component_snapshots' => $data['metadata']['component_snapshots'] ?? null];
            } catch (\Throwable $exception) {
                $anomalies[] = ['code' => 'ambiguous_economic_identity', 'source' => $source->uuid, 'message' => $exception->getMessage()];
            }
        }
        $legacyPayments = CreditDebtPayment::query()->whereIn('credit_debt_item_id', $manual->pluck('id'))->with('transaction')->get()->map(fn (CreditDebtPayment $payment): array => ['source_type' => 'credit_debt_payment', 'source_identity' => $payment->uuid, 'amount_minor' => $convert((string) $payment->amount, $payment->uuid), 'transaction_uuid' => $payment->transaction?->uuid])->all();
        if ($currency === 'EUR') {
            foreach (SharedExpenseSettlement::query()->where('user_id', $user->id)->where('counterparty_id', $counterparty->id)->with(['transaction.refundTransaction', 'transaction.account'])->get() as $settlement) {
                $effect = $this->sharedBalances->settlementEffectCents($settlement);
                $legacyPayments[] = ['source_type' => 'shared_expense_settlement', 'source_identity' => $settlement->uuid, 'effective_signed_minor' => $effect, 'transaction_uuid' => $settlement->transaction?->uuid];
                try {
                    $this->sourceProjector->settlement($settlement);
                } catch (\Throwable $exception) {
                    $anomalies[] = ['code' => 'ambiguous_legacy_payment', 'source' => $settlement->uuid, 'message' => $exception->getMessage()];
                }
                if ($effect === 0) {
                    $anomalies[] = ['code' => 'ineffective_legacy_settlement', 'source' => $settlement->uuid];
                }
            }
        }
        $newObligations = $relation?->obligations()->get()->map(fn (CounterpartyObligation $obligation): array => $this->projections->obligation($obligation))->all() ?? [];
        $payments = $relation?->payments()->get()->map(fn (CounterpartyPayment $payment): array => [...$this->projections->paymentDocument($payment), 'uuid' => $payment->uuid, 'direction' => $payment->direction->value,
            'effective_minor' => $this->projections->effectivePayment($payment), 'allocated_minor' => $this->projections->allocatedToPayment($payment), 'unallocated_minor' => $this->projections->unallocated($payment),
            'legacy_source_type' => $payment->legacy_source_type, 'legacy_source_identity' => $payment->legacy_source_identity, 'transaction_uuid' => $payment->transaction_uuid, 'returned_minor' => app(CounterpartyAuthority::class)->returned($payment), 'allocation_status' => $payment->metadata['allocation_status'] ?? null])->all() ?? [];
        $publicIds = $relation?->activated_at === null ? ['ledger' => [], 'allocations' => []] : app(CounterpartyAuthority::class)->publicEntryIds($relation);
        $publicAllocationDeltas = CounterpartyAllocationEntry::query()->whereIn('id', $publicIds['allocations'])->get()->groupBy('obligation_id')->map(fn ($entries): int => CurrencyMinorUnits::sum($entries->pluck('amount_minor')));
        $publicEffect = CurrencyMinorUnits::sum(CounterpartyLedgerEntry::query()->whereIn('id', $publicIds['ledger'])->with('payment')->get()->filter(fn ($entry): bool => $entry->payment_id === null || $entry->payment?->legacy_source_type !== 'shared_expense_settlement')->pluck('signed_minor'));
        $sourceDeltas = [];
        $seenEconomic = [];
        foreach ($legacyObligations as $source) {
            $captured = collect($newObligations)->first(fn (array $entry): bool => isset($source['economic_identity']) ? $entry['economic_identity'] === $source['economic_identity'] : $entry['source_type'] === $source['source_type'] && $entry['source_identity'] === $source['source_identity']);
            $expected = $source['signed_minor'] ?? (isset($source['recognized_minor']) ? $source['recognized_minor'] * $source['direction_sign'] : null);
            if (isset($source['economic_identity']) && $expected !== 0) {
                if (isset($seenEconomic[$source['economic_identity']])) {
                    $anomalies[] = ['code' => 'duplicate_legacy_economic_source', 'source' => $source['source_identity'], 'economic_identity' => $source['economic_identity']];
                }
                $seenEconomic[$source['economic_identity']] = true;
            }
            if ($captured === null && $expected !== 0) {
                $anomalies[] = ['code' => 'legacy_obligation_not_captured', 'source' => $source['source_identity']];
            }
            $actual = $captured['recognized_minor'] ?? 0;
            $componentDeltas = [];
            foreach ($source['component_snapshots'] ?? [] as $identity => $minor) {
                $actualComponent = $captured['component_snapshots'][$identity] ?? null;
                $componentDeltas[] = ['source_identity' => $identity, 'legacy_minor' => $minor, 'ledger_minor' => $actualComponent, 'delta_minor' => $actualComponent === null ? null : CurrencyMinorUnits::add($actualComponent, -$minor)];
                if ($captured !== null && $actualComponent !== $minor) {
                    $anomalies[] = ['code' => 'shared_component_mismatch', 'source' => $identity];
                }
            }
            $legacyResidual = isset($source['remaining_minor']) ? $source['remaining_minor'] * $source['direction_sign'] : $expected;
            $capturedModel = $captured === null ? null : $relation?->obligations()->where('uuid', $captured['uuid'])->first();
            $publicAllocated = $capturedModel === null ? 0 : ($publicAllocationDeltas[$capturedModel->id] ?? 0);
            $expectedResidual = $legacyResidual === null ? null : CurrencyMinorUnits::add($legacyResidual, -$publicAllocated * ($captured['direction_sign'] ?? ($expected < 0 ? -1 : 1)));
            $ledgerResidual = ($captured['remaining_minor'] ?? 0) * ($captured['direction_sign'] ?? 1);
            $sourceDeltas[] = ['source_type' => $source['source_type'], 'source_identity' => $source['source_identity'], 'economic_identity' => $source['economic_identity'] ?? $captured['economic_identity'] ?? null, 'legacy_minor' => $expected, 'ledger_minor' => $actual, 'delta_minor' => $expected === null ? null : CurrencyMinorUnits::add($actual, -$expected),
                'components' => $componentDeltas, 'legacy_residual_minor' => $legacyResidual, 'ledger_residual_minor' => $ledgerResidual, 'public_allocation_delta_minor' => $publicAllocated, 'expected_residual_minor' => $expectedResidual, 'residual_delta_minor' => $expectedResidual === null ? null : CurrencyMinorUnits::add($ledgerResidual, -$expectedResidual)];
            if ($captured !== null && $expected !== null && $actual !== $expected) {
                $anomalies[] = ['code' => 'obligation_principal_mismatch', 'source' => $source['source_identity']];
            }
            if ($captured !== null && array_key_exists('due_date', $source) && $captured['due_date'] !== $source['due_date']) {
                $anomalies[] = ['code' => 'obligation_due_date_mismatch', 'source' => $source['source_identity']];
            }
            if ($captured !== null && isset($source['remaining_minor']) && $ledgerResidual !== $expectedResidual) {
                $anomalies[] = ['code' => 'obligation_residual_mismatch', 'source' => $source['source_identity']];
            }
        }
        foreach (collect($newObligations)->groupBy(fn (array $entry): string => $entry['source_type'].':'.$entry['source_identity']) as $identity => $group) {
            if ($group->count() > 1) {
                $anomalies[] = ['code' => 'duplicate_new_obligation_source', 'source' => $identity];
            }
        }
        foreach ($newObligations as $entry) {
            if ($entry['recognized_minor'] !== 0 && ! collect($legacyObligations)->contains(fn (array $source): bool => isset($source['economic_identity']) ? $source['economic_identity'] === $entry['economic_identity'] : $source['source_type'] === $entry['source_type'] && $source['source_identity'] === $entry['source_identity'])) {
                $anomalies[] = ['code' => 'new_obligation_without_legacy_source', 'source' => $entry['uuid']];
            }
        }
        foreach (collect($legacyPayments)->filter(fn (array $entry): bool => $entry['transaction_uuid'] !== null)->groupBy('transaction_uuid') as $identity => $group) {
            if ($group->count() > 1) {
                $anomalies[] = ['code' => 'duplicate_legacy_payment_source', 'source' => $identity];
            }
        }
        foreach ($legacyPayments as $source) {
            $captured = collect($payments)->first(fn (array $entry): bool => $entry['legacy_source_type'] === $source['source_type'] && $entry['legacy_source_identity'] === $source['source_identity']);
            if ($captured === null) {
                $anomalies[] = ['code' => 'legacy_payment_not_captured', 'source' => $source['source_identity']];

                continue;
            }
            $expected = $source['effective_signed_minor'] ?? (isset($source['amount_minor']) ? $source['amount_minor'] * ($captured['direction'] === 'received' ? -1 : 1) : null);
            $actual = $captured['effective_minor'] * ($captured['direction'] === 'received' ? -1 : 1);
            $nativeOverride = $relation?->activated_at !== null && CounterpartyOperation::query()->where('relation_id', $relation->id)->whereIn('kind', ['cancel', 'revise'])->get()->contains(fn ($operation): bool => ($operation->result['payment_uuid'] ?? null) === $captured['uuid'] || ($operation->result['previous_payment_uuid'] ?? null) === $captured['uuid']);
            if ($expected !== null && $actual !== $expected && ! $nativeOverride) {
                $anomalies[] = ['code' => 'legacy_payment_effect_mismatch', 'source' => $source['source_identity']];
            }
        }
        foreach ($payments as $payment) {
            if ($payment['effective_minor'] !== 0 && $payment['legacy_source_identity'] !== null && ! collect($legacyPayments)->contains(fn (array $source): bool => $source['source_type'] === $payment['legacy_source_type'] && $source['source_identity'] === $payment['legacy_source_identity'])) {
                $anomalies[] = ['code' => 'new_payment_without_legacy_source', 'source' => $payment['uuid']];
            }
        }
        $internal = $relation === null ? ['ledger_minor' => 0, 'explained_minor' => 0, 'delta_minor' => 0, 'consistent' => true, 'anomalies' => []] : $this->projections->reconcile($relation);
        foreach ($internal['anomalies'] as $code) {
            $anomalies[] = ['code' => $code];
        }
        foreach ($payments as $payment) {
            if ($payment['unallocated_minor'] > 0) {
                $anomalies[] = ['code' => 'unallocated_payment', 'source' => $payment['uuid'], 'amount_minor' => $payment['unallocated_minor'], 'acknowledged' => in_array($payment['allocation_status'], ['unknown_legacy', 'explicit_native', 'return_offset'], true) || $payment['unallocated_minor'] <= $payment['returned_minor'] || ($relation?->activated_at !== null && CounterpartyOperation::query()->where('relation_id', $relation->id)->where('kind', 'allocation')->get()->contains(fn ($operation): bool => ($operation->result['payment_uuid'] ?? null) === $payment['uuid'] && ($operation->result['allow_unallocated'] ?? false)))];
            }
        }
        if ($relation !== null && $relation->minor_unit !== CurrencyMinorUnits::precision($currency)) {
            $anomalies[] = ['code' => 'relation_precision_changed'];
        }
        $delta = $legacyMinor === null ? null : CurrencyMinorUnits::add($internal['ledger_minor'], -CurrencyMinorUnits::add($legacyMinor, $publicEffect));
        if ($delta !== null && $delta !== 0) {
            $anomalies[] = ['code' => 'legacy_ledger_mismatch'];
        }

        $issues = CounterpartySyncIssue::query()->where('user_id', $user->id)->where('counterparty_id', $counterparty->id)->where('currency_code', $currency)->whereNull('resolved_at')->get()->toArray();
        foreach ($issues as $issue) {
            $anomalies[] = ['code' => 'pending_shadow_or_migration_error', 'source' => $issue['source_identity'], 'message' => $issue['message']];
        }
        $blocking = collect($anomalies)->filter(fn (array $anomaly): bool => ! in_array($anomaly['code'], ['ineffective_legacy_settlement', 'unallocated_payment'], true) || ($anomaly['code'] === 'unallocated_payment' && ! $anomaly['acknowledged']));
        $missing = collect($anomalies)->whereIn('code', ['legacy_obligation_not_captured', 'legacy_payment_not_captured']);
        $hardErrors = $blocking->reject(fn (array $anomaly): bool => in_array($anomaly['code'], ['legacy_obligation_not_captured', 'legacy_payment_not_captured'], true) || ($missing->isNotEmpty() && $anomaly['code'] === 'legacy_ledger_mismatch'));
        $readiness = $relation === null ? ($issues === [] ? 'not_migrated' : 'blocked') : ($hardErrors->isNotEmpty() ? 'blocked' : ($missing->isNotEmpty() ? 'shadowing' : ($delta === 0 && $internal['consistent'] ? 'reconciled' : 'blocked')));
        $ledgerEntries = $relation?->ledgerEntries()->orderBy('id')->get()->toArray() ?? [];

        return ['relation_uuid' => $relation?->uuid, 'currency_code' => $currency, 'migration_state' => $relation?->migration_state, 'readiness' => $readiness,
            'legacy_manual_balance_minor' => $convert(MoneyAmount::decimal($legacy['manual_cents']), 'legacy_manual_balance'), 'legacy_shared_balance_minor' => $convert(MoneyAmount::decimal($legacy['shared_cents']), 'legacy_shared_balance'),
            'legacy_balance_minor' => $legacyMinor, 'public_effect_minor' => $publicEffect, 'expected_balance_minor' => $legacyMinor === null ? null : CurrencyMinorUnits::add($legacyMinor, $publicEffect), 'ledger_balance_minor' => $internal['ledger_minor'], 'delta_minor' => $delta, 'source_deltas' => $sourceDeltas,
            'missing_sources' => collect($anomalies)->whereIn('code', ['legacy_obligation_not_captured', 'legacy_payment_not_captured'])->values()->all(),
            'duplicate_sources' => collect($anomalies)->whereIn('code', ['duplicate_new_obligation_source', 'duplicate_legacy_economic_source', 'duplicate_legacy_payment_source'])->values()->all(),
            'ambiguous_identities' => collect($anomalies)->whereIn('code', ['ambiguous_economic_identity', 'duplicate_legacy_economic_source'])->values()->all(),
            'legacy_obligations' => $legacyObligations, 'new_obligations' => $newObligations, 'legacy_payments' => $legacyPayments, 'payments' => $payments,
            'ledger_entries' => $ledgerEntries, 'reversals' => collect($ledgerEntries)->where('event_type', 'reversal')->values()->all(),
            'allocations' => $relation?->allocationEntries()->orderBy('id')->get()->toArray() ?? [], 'unallocated' => collect($payments)->filter(fn (array $payment): bool => $payment['unallocated_minor'] > 0)->values()->all(),
            'pending_errors' => $issues, 'internal_reconciliation' => $internal, 'anomalies' => $anomalies];
    }
}
