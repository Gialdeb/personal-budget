<?php

namespace App\Services\Counterparties;

use App\Enums\CounterpartyObligationSourceEnum;
use App\Enums\CreditDebtTypeEnum;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CounterpartySyncIssue;
use App\Models\CreditDebtItem;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\CreditDebts\CounterpartyBalanceService;
use App\Services\CreditDebts\ManualEconomicSubjectResolver;
use App\Support\CurrencyMinorUnits;
use App\Support\MoneyAmount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CounterpartyBackfillService
{
    public function __construct(private readonly SharedSourceProjector $sources, private readonly SharedCounterpartyShadowService $shared, private readonly CounterpartyLedgerService $ledger, private readonly CounterpartyProjectionService $projections, private readonly CounterpartyReconciliationService $reconciliation, private readonly CounterpartyBalanceService $legacy, private readonly AccessibleAccountsQuery $accounts) {}

    /** @return array<string, mixed> */
    public function run(User $user, TrackedItem $party, string $currency, bool $apply = false): array
    {
        if ($party->user_id !== $user->id || $party->account_id !== null) {
            throw ValidationException::withMessages(['counterparty' => __('form_errors.invalid_personal_economic_ownership')]);
        }
        if ($apply && app(CounterpartyAuthority::class)->activeRelation($user->id, $party->id, $currency) !== null) {
            throw ValidationException::withMessages(['relation' => __('form_errors.the_relationship_is_active_do_not_import_legacy_documents_again_use_the')]);
        }
        if (! $apply) {
            return $this->plan($user, $party, $currency);
        }

        try {
            return DB::transaction(function () use ($user, $party, $currency): array {
                TrackedItem::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();
                $plan = $this->plan($user, $party, $currency, true);
                if ($plan['ambiguous'] !== []) {
                    throw ValidationException::withMessages(['migration' => __('form_errors.relation_backfill_blocked').json_encode($plan['ambiguous'], JSON_THROW_ON_ERROR)]);
                }
                $relation = $this->ledger->relation($user, $party, $currency);
                $relation = CounterpartyRelation::query()->whereKey($relation->id)->lockForUpdate()->firstOrFail();
                foreach (CreditDebtItem::query()->forUser($user)->where('counterparty_id', $party->id)->where('currency_code', $currency)->with(['payments.transaction.account', 'reference'])->lockForUpdate()->get() as $item) {
                    $principal = CurrencyMinorUnits::fromDecimal((string) $item->total_amount, $currency) * ($item->type === CreditDebtTypeEnum::CREDIT ? 1 : -1);
                    $obligation = $relation->obligations()->where('source_type', 'credit_debt_item')->where('source_identity', $item->uuid)->first();
                    if ($obligation === null) {
                        $obligation = $this->ledger->recognize($relation, CounterpartyObligationSourceEnum::CREDIT_DEBT_ITEM, $item->uuid, 'credit-debt:'.$item->uuid, $principal, $item->created_at->toDateString(), 'backfill:manual:'.$item->uuid, $item->due_date?->toDateString(), ['legacy_origin' => 'credit_debt_item', 'migration_date' => now()->toISOString(), 'description' => $item->description]);
                    } elseif ($this->projections->recognized($obligation) !== $principal || $this->projections->obligation($obligation)['due_date'] !== $item->due_date?->toDateString()) {
                        $this->ledger->revise($obligation, $principal, $item->created_at->toDateString(), 'backfill:manual:'.$item->uuid.':'.($obligation->ledgerEntries()->max('id') + 1), dueDate: $item->due_date?->toDateString(), metadata: ['legacy_origin' => 'credit_debt_item', 'description' => $item->description]);
                    }
                    CounterpartySyncIssue::query()->where('source_type', 'credit_debt_item')->where('source_identity', $item->uuid)->whereNull('resolved_at')->update(['resolved_at' => now()]);
                    foreach ($item->payments as $source) {
                        $minor = CurrencyMinorUnits::fromDecimal((string) $source->amount, $currency);
                        $payment = CounterpartyPayment::query()->where('legacy_source_type', 'credit_debt_payment')->where('legacy_source_identity', $source->uuid)->first();
                        if ($payment === null) {
                            $payment = $this->ledger->synchronizeLegacyPayment($relation, $source, $minor, 'backfill:manual-payment:'.$source->uuid, ['allocation_status' => 'known_legacy', 'legacy_origin' => 'credit_debt_payment', 'migration_date' => now()->toISOString()]);
                        } elseif ($payment->relation_id !== $relation->id || $this->projections->effectivePayment($payment) !== $minor) {
                            throw ValidationException::withMessages(['payment' => __('form_errors.existing_manual_payment_differs_from_legacy_explicit_correction_is_required')]);
                        }
                        if ($this->projections->allocatedToPayment($payment) === 0) {
                            $this->ledger->allocate($payment, $obligation, $minor, $source->paid_at->toDateString(), 'backfill:known-allocation:'.$source->uuid);
                        } elseif ($payment->allocationEntries()->where('obligation_id', '!=', $obligation->id)->exists() || $this->projections->allocatedToPayment($payment) !== $minor) {
                            throw ValidationException::withMessages(['allocation' => __('form_errors.existing_manual_allocation_differs_from_its_known_legacy_obligation')]);
                        }
                        CounterpartySyncIssue::query()->where('source_type', 'credit_debt_payment')->where('source_identity', $source->uuid)->whereNull('resolved_at')->update(['resolved_at' => now()]);
                    }
                }
                foreach ($this->sharedDocuments($user, $party, $currency, true) as $source) {
                    $this->shared->synchronizeSource($source);
                }
                foreach ($this->settlements($user, $party, $currency, true) as $source) {
                    $this->shared->synchronizeSettlement($source);
                }
                CounterpartySyncIssue::query()->where('source_type', 'backfill_relation_'.$currency)->where('source_identity', $party->uuid)->update(['resolved_at' => now()]);
                $report = $this->reconciliation->inspect($relation);
                $relation->update(['migration_state' => $report['readiness']]);
                $report['migration_state'] = $report['readiness'];

                return [...$plan, 'dry_run' => false, 'ledger_result_minor' => $report['ledger_balance_minor'], 'delta_minor' => $report['delta_minor'], 'readiness' => $report['readiness'], 'reconciliation' => $report];
            });
        } catch (Throwable $exception) {
            try {
                CounterpartySyncIssue::query()->updateOrCreate(['source_type' => 'backfill_relation_'.$currency, 'source_identity' => $party->uuid], ['user_id' => $user->id, 'counterparty_id' => $party->id, 'currency_code' => $currency, 'message' => $exception->getMessage(), 'resolved_at' => null]);
            } catch (Throwable $diagnosticFailure) {
                Log::error('Counterparty backfill diagnostic could not be persisted.', ['source_identity' => $party->uuid, 'message' => $diagnosticFailure->getMessage()]);
            }
            throw $exception;
        }
    }

    /** @return list<string> */
    public function currencies(User $user, TrackedItem $party): array
    {
        return collect(['EUR'])->concat(CreditDebtItem::query()->forUser($user)->where(fn ($query) => $query->where('counterparty_id', $party->id)->orWhere('reference_id', $party->id))->pluck('currency_code'))
            ->concat(Transaction::query()->whereIn('account_id', $this->accounts->ids($user))->where('shared_expense_counterparty_id', $party->id)->pluck('currency'))
            ->concat(SharedExpenseLedgerEntry::query()->where('user_id', $user->id)->where('counterparty_id', $party->id)->pluck('currency'))
            ->concat(CounterpartyRelation::query()->where('user_id', $user->id)->where('counterparty_id', $party->id)->pluck('currency_code'))->unique()->sort()->values()->all();
    }

    /** @return array<string, mixed> */
    private function plan(User $user, TrackedItem $party, string $currency, bool $lock = false): array
    {
        CurrencyMinorUnits::precision($currency);
        $relation = CounterpartyRelation::query()->where(['user_id' => $user->id, 'counterparty_id' => $party->id, 'currency_code' => $currency])->first();
        $balance = $relation === null ? 0 : $this->projections->balance($relation);
        $before = $this->legacy->legacyBalance($user, $party, $currency);
        $ambiguous = app(ManualEconomicSubjectResolver::class)->relationIssues($user, $party, $currency);
        $created = [];
        $reused = [];
        $effects = [];
        $identities = [];
        $bankIdentities = [];
        $items = CreditDebtItem::query()->forUser($user)->where('counterparty_id', $party->id)->where('currency_code', $currency)->with(['payments.transaction.account', 'reference'])->when($lock, fn ($query) => $query->lockForUpdate())->get();
        foreach ($items as $item) {
            try {
                app(ManualEconomicSubjectResolver::class)->assertConsistent($item);
                $minor = CurrencyMinorUnits::fromDecimal((string) $item->total_amount, $currency) * ($item->type === CreditDebtTypeEnum::CREDIT ? 1 : -1);
                if ($minor === 0) {
                    throw ValidationException::withMessages(['amount' => __('form_errors.zero_manual_principal_cannot_be_recognized')]);
                }
                $existing = $relation?->obligations()->where('source_type', 'credit_debt_item')->where('source_identity', $item->uuid)->first();
                if ($existing !== null && ((($this->projections->recognized($existing) ?: $existing->recognized_minor) > 0) !== ($minor > 0) || $this->projections->allocatedToObligation($existing) > abs($minor))) {
                    throw ValidationException::withMessages(['obligation' => __('form_errors.existing_manual_obligation_cannot_be_revised_safely')]);
                }
                if (CurrencyMinorUnits::sum($item->payments->map(fn ($payment): int => CurrencyMinorUnits::fromDecimal((string) $payment->amount, $currency))) > abs($minor)) {
                    throw ValidationException::withMessages(['payment' => __('form_errors.known_manual_payments_exceed_the_documented_principal')]);
                }
                $effects[] = ['kind' => 'obligation', 'source_type' => 'credit_debt_item', 'source_identity' => $item->uuid, 'target_minor' => $minor, 'current_minor' => $existing === null ? 0 : $this->projections->recognized($existing)];
                if ($existing === null) {
                    $created[] = 'obligation:'.$item->uuid;
                } else {
                    $reused[] = 'obligation:'.$item->uuid;
                }
                foreach ($item->payments as $payment) {
                    $amount = CurrencyMinorUnits::fromDecimal((string) $payment->amount, $currency);
                    $transaction = $payment->transaction;
                    if ($transaction === null || $transaction->account === null || $transaction->status->value !== 'confirmed' || $transaction->currency !== $currency
                        || $transaction->account->currency_code !== $currency || $transaction->direction->value !== ($minor > 0 ? 'income' : 'expense')
                        || CurrencyMinorUnits::fromDecimal((string) $transaction->amount, $currency) !== $amount || $transaction->refundTransaction()->exists()) {
                        throw ValidationException::withMessages(['payment' => __('form_errors.manual_payment_bank_document_is_missing_or_incompatible')]);
                    }
                    if (isset($bankIdentities[$transaction->uuid])) {
                        throw ValidationException::withMessages(['payment' => __('form_errors.duplicate_bank_payment_source')]);
                    }
                    $bankIdentities[$transaction->uuid] = true;
                    $old = CounterpartyPayment::query()->where('legacy_source_type', 'credit_debt_payment')->where('legacy_source_identity', $payment->uuid)->first();
                    if ($old !== null && ($relation === null || $old->relation_id !== $relation->id || $this->projections->effectivePayment($old) !== $amount)) {
                        throw ValidationException::withMessages(['payment' => __('form_errors.existing_manual_payment_conflicts_with_its_legacy_source')]);
                    }
                    $effects[] = ['kind' => 'payment', 'source_type' => 'credit_debt_payment', 'source_identity' => $payment->uuid, 'target_minor' => $amount * ($minor > 0 ? -1 : 1), 'current_minor' => $old === null ? 0 : $this->projections->effectivePayment($old) * $old->direction->balanceSign()];
                    if ($old === null) {
                        $created[] = 'payment:'.$payment->uuid;
                    } else {
                        $reused[] = 'payment:'.$payment->uuid;
                    }
                }
            } catch (Throwable $exception) {
                $ambiguous[] = ['source' => $item->uuid, 'message' => $exception->getMessage()];
            }
        }
        foreach ($this->sharedDocuments($user, $party, $currency, $lock) as $source) {
            try {
                $data = $this->sources->describe($source);
                $componentFingerprint = isset($data['metadata']['component_snapshots']) ? hash('sha256', json_encode($data['metadata']['component_snapshots'], JSON_THROW_ON_ERROR)) : null;
                if ($componentFingerprint !== null && ($identities[$data['economic_identity']] ?? null) === $componentFingerprint) {
                    $reused[] = 'component:'.$source->uuid;

                    continue;
                }
                if ($data['signed_minor'] !== 0 && isset($identities[$data['economic_identity']])) {
                    throw ValidationException::withMessages(['identity' => __('form_errors.multiple_effective_sources_have_the_same_economic_identity')]);
                }
                if ($data['signed_minor'] !== 0) {
                    $identities[$data['economic_identity']] = $componentFingerprint ?? true;
                }
                $old = $relation?->obligations()->where('economic_identity', $data['economic_identity'])->first();
                if ($old !== null && $this->projections->allocatedToObligation($old) !== 0 && $this->projections->recognized($old) !== $data['signed_minor']) {
                    throw ValidationException::withMessages(['allocation' => __('form_errors.release_shared_allocations_before_revision')]);
                }
                $effects[] = ['kind' => 'obligation', 'source_type' => $data['source_type'], 'source_identity' => $data['source_identity'], 'economic_identity' => $data['economic_identity'], 'target_minor' => $data['signed_minor'], 'current_minor' => $old === null ? 0 : $this->projections->recognized($old)];
                if ($old !== null) {
                    $reused[] = 'obligation:'.$source->uuid;
                } elseif ($data['signed_minor'] !== 0) {
                    $created[] = 'obligation:'.$source->uuid;
                }
            } catch (Throwable $exception) {
                $ambiguous[] = ['source' => $source->uuid, 'message' => $exception->getMessage()];
            }
        }
        foreach ($this->settlements($user, $party, $currency, $lock) as $source) {
            try {
                $data = $this->sources->settlement($source);
                if (isset($bankIdentities[$data['transaction_uuid']])) {
                    throw ValidationException::withMessages(['payment' => __('form_errors.duplicate_bank_payment_source')]);
                }
                $bankIdentities[$data['transaction_uuid']] = true;
                $old = CounterpartyPayment::query()->where('legacy_source_type', 'shared_expense_settlement')->where('legacy_source_identity', $source->uuid)->first();
                if ($old !== null && ($relation === null || $old->relation_id !== $relation->id || $this->projections->allocatedToPayment($old) !== 0)) {
                    throw ValidationException::withMessages(['payment' => __('form_errors.existing_settlement_cannot_be_replaced_without_reviewing_allocations_or_ownership')]);
                }
                $effects[] = ['kind' => 'payment', 'source_type' => 'shared_expense_settlement', 'source_identity' => $source->uuid, 'target_minor' => $data['signed_minor'], 'current_minor' => $old === null ? 0 : $this->projections->effectivePayment($old) * $old->direction->balanceSign(), 'allocation_status' => 'unknown_legacy'];
                if ($old === null) {
                    $created[] = 'payment:'.$source->uuid;
                } else {
                    $reused[] = 'payment:'.$source->uuid;
                }
            } catch (Throwable $exception) {
                $ambiguous[] = ['source' => $source->uuid, 'message' => $exception->getMessage()];
            }
        }
        foreach ($effects as $effect) {
            $balance = CurrencyMinorUnits::add($balance, CurrencyMinorUnits::add($effect['target_minor'], -$effect['current_minor']));
        }
        try {
            $legacyMinor = CurrencyMinorUnits::fromDecimal(MoneyAmount::decimal($before['total_cents']), $currency);
        } catch (Throwable $exception) {
            $legacyMinor = null;
            $ambiguous[] = ['source' => 'legacy_balance', 'message' => $exception->getMessage()];
        }

        return ['dry_run' => true, 'relation_uuid' => $relation?->uuid, 'user_id' => $user->id, 'counterparty_uuid' => $party->uuid, 'currency_code' => $currency, 'created' => $created, 'reused' => $reused, 'ambiguous' => $ambiguous, 'effects' => $effects, 'legacy_before_minor' => $legacyMinor, 'ledger_result_minor' => $ambiguous === [] ? $balance : null, 'delta_minor' => $ambiguous === [] && $legacyMinor !== null ? CurrencyMinorUnits::add($balance, -$legacyMinor) : null];
    }

    /** @return Collection<int, Transaction|SharedExpenseLedgerEntry> */
    private function sharedDocuments(User $user, TrackedItem $party, string $currency, bool $lock): Collection
    {
        $transactions = Transaction::withTrashed()->whereIn('account_id', $this->accounts->ids($user))->where('shared_expense_counterparty_id', $party->id)->where('currency', $currency)->when($lock, fn ($query) => $query->lockForUpdate())->get();
        $external = SharedExpenseLedgerEntry::query()->where('user_id', $user->id)->where('counterparty_id', $party->id)->where('currency', $currency)->when($lock, fn ($query) => $query->lockForUpdate())->get();

        return collect($transactions->all())->concat($external);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, SharedExpenseSettlement> */
    private function settlements(User $user, TrackedItem $party, string $currency, bool $lock): \Illuminate\Database\Eloquent\Collection
    {
        return SharedExpenseSettlement::query()->where('user_id', $user->id)->where('counterparty_id', $party->id)->when($currency !== 'EUR', fn ($query) => $query->whereRaw('1 = 0'))->when($lock, fn ($query) => $query->lockForUpdate())->get();
    }
}
