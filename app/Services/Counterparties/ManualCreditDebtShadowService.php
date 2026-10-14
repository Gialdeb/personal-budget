<?php

namespace App\Services\Counterparties;

use App\Enums\CounterpartyObligationSourceEnum;
use App\Enums\CounterpartyPaymentDirectionEnum;
use App\Enums\CreditDebtTypeEnum;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartySyncIssue;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Support\CurrencyMinorUnits;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Opt-in, fail-open shadow capture. Existing records are never implicitly backfilled. */
class ManualCreditDebtShadowService
{
    public function __construct(
        private readonly CounterpartyLedgerService $ledger,
        private readonly CounterpartyProjectionService $projections,
    ) {}

    public function itemCreated(CreditDebtItem $item): void
    {
        if ($item->counterparty_id === null) {
            return;
        }
        $this->capture($item, function () use ($item): void {
            $relation = $this->ledger->relation($item->user, $item->counterparty, $item->currency_code);
            $this->ledger->recognize($relation, CounterpartyObligationSourceEnum::CREDIT_DEBT_ITEM, $item->uuid, 'credit-debt:'.$item->uuid,
                $this->signedPrincipal($item), $item->created_at->toDateString(), 'shadow:item:'.$item->uuid.':created',
                $item->due_date?->toDateString(), $this->snapshot($item), $item->user);
        });
    }

    public function itemChanged(CreditDebtItem $item, bool $deleted = false): void
    {
        $this->capture($item, function () use ($item, $deleted): void {
            $obligation = CounterpartyObligation::query()->where('source_type', CounterpartyObligationSourceEnum::CREDIT_DEBT_ITEM)
                ->where('source_identity', $item->uuid)->first();
            if ($obligation === null) {
                if (! $deleted && app(CounterpartyAuthority::class)->activeRelation($item->user_id, $item->counterparty_id, $item->currency_code) !== null && ! $item->payments()->exists()) {
                    $this->itemCreated($item);
                }

                return;
            }
            $relation = $obligation->relation;
            $compatible = ! $deleted && $relation->user_id === $item->user_id && $relation->counterparty_id === $item->counterparty_id
                && $relation->currency_code === $item->currency_code
                && ($obligation->recognized_minor > 0) === ($item->type === CreditDebtTypeEnum::CREDIT);
            $principal = $compatible ? $this->signedPrincipal($item) : 0;
            $metadata = [...$this->snapshot($item), 'tracking_suspended' => ! $compatible && ! $deleted, 'deleted' => $deleted];
            $last = $obligation->ledgerEntries()->orderByDesc('id')->first();
            $snapshot = $last?->metadata['snapshot'] ?? [];
            if ($principal === $this->projections->recognized($obligation) && ($snapshot['description'] ?? null) === $item->description
                && ($snapshot['due_date'] ?? null) === $item->due_date?->toDateString()
                && ($snapshot['tracking_suspended'] ?? false) === $metadata['tracking_suspended']
                && ($snapshot['deleted'] ?? false) === $deleted) {
                return;
            }
            $version = (int) $obligation->ledgerEntries()->max('source_version') + 1;
            $this->ledger->revise($obligation, $principal, $item->created_at->toDateString(), 'shadow:item:'.$item->uuid.':revision:'.$version,
                dueDate: $item->due_date?->toDateString(), metadata: $metadata, actor: $item->user);
        });
    }

    public function paymentCreated(CreditDebtPayment $payment): void
    {
        $this->capture($payment, function () use ($payment): void {
            $item = $payment->item;
            $obligation = CounterpartyObligation::query()->where('source_type', CounterpartyObligationSourceEnum::CREDIT_DEBT_ITEM)
                ->where('source_identity', $item->uuid)->first();
            if ($obligation === null || $obligation->relation->counterparty_id !== $item->counterparty_id
                || $obligation->relation->currency_code !== $item->currency_code
                || $this->projections->recognized($obligation) !== $this->signedPrincipal($item)) {
                return;
            }
            $minor = CurrencyMinorUnits::fromDecimal((string) $payment->amount, $payment->currency_code);
            $newPayment = $this->ledger->recordPayment($obligation->relation,
                $item->type === CreditDebtTypeEnum::CREDIT ? CounterpartyPaymentDirectionEnum::RECEIVED : CounterpartyPaymentDirectionEnum::SENT,
                $minor, $payment->paid_at->toDateString(), $payment->account, 'shadow:payment:'.$payment->uuid,
                transaction: $payment->transaction, legacySourceType: 'credit_debt_payment', legacySourceIdentity: $payment->uuid,
                metadata: ['note' => $payment->note, 'legacy_item_uuid' => $item->uuid], actor: $payment->user);
            $this->ledger->allocate($newPayment, $obligation, $minor, $payment->paid_at->toDateString(), 'shadow:payment:'.$payment->uuid.':allocation', $payment->user);
        });
    }

    public function paymentDeleted(CreditDebtPayment $payment): void
    {
        $this->capture($payment, function () use ($payment): void {
            $newPayment = CounterpartyPayment::query()->where('legacy_source_type', 'credit_debt_payment')->where('legacy_source_identity', $payment->uuid)->first();
            if ($newPayment !== null) {
                $this->ledger->reversePayment($newPayment, $payment->paid_at->toDateString(), 'shadow:payment:'.$payment->uuid.':deleted', $payment->user, 'Legacy payment deletion; no new bank movement.');
            }
        });
    }

    private function signedPrincipal(CreditDebtItem $item): int
    {
        return CurrencyMinorUnits::fromDecimal((string) $item->total_amount, $item->currency_code) * ($item->type === CreditDebtTypeEnum::CREDIT ? 1 : -1);
    }

    /** @return array<string, mixed> */
    private function snapshot(CreditDebtItem $item): array
    {
        return ['description' => $item->description, 'legacy_item_uuid' => $item->uuid, 'legacy_counterparty_id' => $item->counterparty_id,
            'legacy_currency_code' => $item->currency_code, 'legacy_type' => $item->type->value, 'economic_date_basis' => 'record_created_at'];
    }

    private function capture(CreditDebtItem|CreditDebtPayment $source, Closure $operation): void
    {
        if (! (bool) config('counterparty_ledger.shadow_manual_enabled', false) && app(CounterpartyAuthority::class)->activeRelation($source->user_id, ($source instanceof CreditDebtItem ? $source : $source->item)->counterparty_id, ($source instanceof CreditDebtItem ? $source : $source->item)->currency_code) === null) {
            return;
        }
        $sourceIdentity = $source->uuid;
        $type = $source instanceof CreditDebtItem ? 'credit_debt_item' : 'credit_debt_payment';
        $item = $source instanceof CreditDebtItem ? $source : $source->item;
        try {
            /** A nested transaction/savepoint isolates shadow failures from the legacy write. */
            DB::transaction(function () use ($operation, $type, $sourceIdentity): void {
                $operation();
                if (Schema::hasTable('counterparty_sync_issues')) {
                    CounterpartySyncIssue::query()->where('source_type', $type)->where('source_identity', $sourceIdentity)->whereNull('resolved_at')->update(['resolved_at' => now()]);
                }
            });
        } catch (Throwable $exception) {
            if (app(CounterpartyAuthority::class)->activeRelation($item->user_id, $item->counterparty_id, $item->currency_code) !== null) {
                throw $exception;
            }
            Log::warning('Counterparty shadow capture skipped.', ['source_identity' => $sourceIdentity, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
            try {
                if (Schema::hasTable('counterparty_sync_issues')) {
                    CounterpartySyncIssue::query()->updateOrCreate(['source_type' => $type, 'source_identity' => $sourceIdentity], ['user_id' => $item->user_id, 'counterparty_id' => $item->counterparty_id, 'currency_code' => $item->currency_code, 'message' => $exception->getMessage(), 'resolved_at' => null]);
                }
            } catch (Throwable $diagnosticFailure) {
                Log::error('Manual shadow diagnostic could not be persisted.', ['source_identity' => $sourceIdentity, 'message' => $diagnosticFailure->getMessage()]);
            }
        }
    }
}
