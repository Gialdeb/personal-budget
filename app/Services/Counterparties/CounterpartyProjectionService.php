<?php

namespace App\Services\Counterparties;

use App\Models\CounterpartyAllocationEntry;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Support\CurrencyMinorUnits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CounterpartyProjectionService
{
    public function balance(CounterpartyRelation $relation): int
    {
        return CurrencyMinorUnits::sum($relation->ledgerEntries()->get(['signed_minor'])->pluck('signed_minor'));
    }

    public function recognized(CounterpartyObligation $obligation): int
    {
        return CurrencyMinorUnits::sum($obligation->ledgerEntries()->get(['signed_minor'])->pluck('signed_minor'));
    }

    public function allocatedToObligation(CounterpartyObligation $obligation): int
    {
        return CurrencyMinorUnits::sum($obligation->allocationEntries()->get(['amount_minor'])->pluck('amount_minor'));
    }

    public function allocatedToPayment(CounterpartyPayment $payment): int
    {
        return CurrencyMinorUnits::sum($payment->allocationEntries()->get(['amount_minor'])->pluck('amount_minor'));
    }

    public function effectivePayment(CounterpartyPayment $payment): int
    {
        return $payment->direction->balanceSign() * CurrencyMinorUnits::sum($payment->ledgerEntries()->get(['signed_minor'])->pluck('signed_minor'));
    }

    /** @return array<string, mixed> */
    public function paymentDocument(CounterpartyPayment $payment): array
    {
        $last = $payment->ledgerEntries()->where('event_type', 'payment_recorded')->orderByDesc('id')->first();

        return $this->formatPaymentDocument($payment, $last);
    }

    /** @return array<string, mixed> */
    private function formatPaymentDocument(CounterpartyPayment $payment, ?CounterpartyLedgerEntry $last): array
    {
        $snapshot = $last?->metadata['legacy_snapshot'] ?? [];

        return ['economic_date' => $snapshot['economic_date'] ?? $payment->economic_date->toDateString(), 'amount_minor' => $snapshot['amount_minor'] ?? $payment->amount_minor, 'original_amount_minor' => $payment->amount_minor,
            'account_uuid' => $snapshot['account_uuid'] ?? $payment->account_uuid, 'original_account_uuid' => $payment->account_uuid,
            'transaction_uuid' => $snapshot['transaction_uuid'] ?? $payment->transaction_uuid];
    }

    /** @param Collection<int, CounterpartyPayment> $payments
     * @return Collection<int, array<string, mixed>>
     */
    public function paymentTotals(Collection $payments): Collection
    {
        if ($payments->isEmpty()) {
            return collect();
        }
        $ids = $payments->pluck('id');
        $ledger = CounterpartyLedgerEntry::query()->whereIn('payment_id', $ids)->get()->groupBy('payment_id');
        $allocations = CounterpartyAllocationEntry::query()->whereIn('payment_id', $ids)->get()->groupBy('payment_id');

        return $payments->mapWithKeys(function (CounterpartyPayment $payment) use ($ledger, $allocations): array {
            $entries = $ledger->get($payment->id, collect());
            $effective = $payment->direction->balanceSign() * CurrencyMinorUnits::sum($entries->pluck('signed_minor'));
            $allocated = CurrencyMinorUnits::sum($allocations->get($payment->id, collect())->pluck('amount_minor'));
            $last = $entries->filter(fn (CounterpartyLedgerEntry $entry): bool => $entry->event_type->value === 'payment_recorded')->sortByDesc('id')->first();

            return [$payment->id => ['effective_minor' => $effective, 'allocated_minor' => $allocated,
                'unallocated_minor' => CurrencyMinorUnits::add($effective, -$allocated), 'document' => $this->formatPaymentDocument($payment, $last)]];
        });
    }

    public function unallocated(CounterpartyPayment $payment): int
    {
        return CurrencyMinorUnits::add($this->effectivePayment($payment), -$this->allocatedToPayment($payment));
    }

    public function remaining(CounterpartyObligation $obligation): int
    {
        return CurrencyMinorUnits::add(abs($this->recognized($obligation)), -$this->allocatedToObligation($obligation));
    }

    /** @return array<string, mixed> */
    public function obligation(CounterpartyObligation $obligation): array
    {
        $last = $obligation->ledgerEntries()->orderByDesc('id')->first();

        return $this->formatObligation($obligation, $last, $this->recognized($obligation), $this->allocatedToObligation($obligation));
    }

    /** @param Collection<int, CounterpartyObligation> $obligations
     * @return Collection<int, array<string, mixed>>
     */
    public function obligations(Collection $obligations): Collection
    {
        if ($obligations->isEmpty()) {
            return collect();
        }
        $ids = $obligations->pluck('id');
        $ledger = CounterpartyLedgerEntry::query()->whereIn('obligation_id', $ids)->get()->groupBy('obligation_id');
        $allocations = CounterpartyAllocationEntry::query()->whereIn('obligation_id', $ids)->get()->groupBy('obligation_id');

        return $obligations->map(function (CounterpartyObligation $obligation) use ($ledger, $allocations): array {
            $entries = $ledger->get($obligation->id, collect());

            return $this->formatObligation($obligation, $entries->sortByDesc('id')->first(), CurrencyMinorUnits::sum($entries->pluck('signed_minor')),
                CurrencyMinorUnits::sum($allocations->get($obligation->id, collect())->pluck('amount_minor')));
        });
    }

    /** @return array<string, mixed> */
    private function formatObligation(CounterpartyObligation $obligation, ?CounterpartyLedgerEntry $last, int $recognized, int $allocated): array
    {
        $snapshot = $last?->metadata['snapshot'] ?? $obligation->metadata ?? [];

        return [
            'uuid' => $obligation->uuid,
            'economic_identity' => $obligation->economic_identity,
            'source_type' => $snapshot['source_type'] ?? $obligation->source_type->value,
            'source_identity' => $snapshot['source_identity'] ?? $obligation->source_identity,
            'economic_date' => $snapshot['economic_date'] ?? $obligation->economic_date->toDateString(),
            'due_date' => array_key_exists('due_date', $snapshot) ? $snapshot['due_date'] : $obligation->due_date?->toDateString(),
            'recognized_minor' => $recognized,
            'allocated_minor' => $allocated,
            'remaining_minor' => CurrencyMinorUnits::add(abs($recognized), -$allocated),
            'direction_sign' => ($recognized ?: $obligation->recognized_minor) > 0 ? 1 : -1,
            'component_snapshots' => $snapshot['component_snapshots'] ?? [],
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function openObligations(CounterpartyRelation $relation): Collection
    {
        return $this->obligations($relation->obligations()->orderBy('economic_date')->orderBy('id')->get())
            ->filter(fn (array $obligation): bool => $obligation['remaining_minor'] > 0)
            ->sortBy(fn (array $obligation): string => $obligation['economic_date'].':'.$obligation['uuid'])->values();
    }

    /** @return array{ledger_minor: int, explained_minor: int, delta_minor: int, consistent: bool, anomalies: list<string>} */
    public function reconcile(CounterpartyRelation $relation): array
    {
        $explained = 0;
        $anomalies = [];
        $obligations = $relation->obligations()->get();
        $recognizedIds = CounterpartyLedgerEntry::query()->whereIn('obligation_id', $obligations->pluck('id'))->pluck('obligation_id')->unique();
        $projections = $this->obligations($obligations)->keyBy('uuid');
        foreach ($obligations as $obligation) {
            $projection = $projections[$obligation->uuid];
            $remaining = $projection['remaining_minor'];
            if ($remaining < 0) {
                $anomalies[] = 'over_allocated_obligation:'.$obligation->uuid;
            }
            if (! $recognizedIds->contains($obligation->id)) {
                $anomalies[] = 'unrecognized_obligation:'.$obligation->uuid;
            }
            $explained = CurrencyMinorUnits::add($explained, $remaining * $projection['direction_sign']);
        }
        $payments = $relation->payments()->get();
        $paymentTotals = $this->paymentTotals($payments);
        foreach ($payments as $payment) {
            $unallocated = $paymentTotals[$payment->id]['unallocated_minor'];
            if ($unallocated < 0) {
                $anomalies[] = 'over_allocated_payment:'.$payment->uuid;
            }
            if ($paymentTotals[$payment->id]['effective_minor'] < 0) {
                $anomalies[] = 'negative_effective_payment:'.$payment->uuid;
            }
            $explained = CurrencyMinorUnits::add($explained, $unallocated * $payment->direction->balanceSign());
        }
        $ledger = $this->balance($relation);
        $delta = CurrencyMinorUnits::add($ledger, -$explained);
        if ($delta !== 0) {
            $anomalies[] = 'ledger_projection_mismatch';
        }

        return ['ledger_minor' => $ledger, 'explained_minor' => $explained, 'delta_minor' => $delta, 'consistent' => $anomalies === [], 'anomalies' => $anomalies];
    }

    /** @return Collection<int, CounterpartyAllocationEntry> */
    public function activeAllocations(CounterpartyPayment|CounterpartyLedgerEntry $parent): Collection
    {
        $query = $this->activeAllocationQuery();

        return $query->where($parent instanceof CounterpartyPayment ? 'payment_id' : 'compensation_entry_id', $parent->id)->get();
    }

    /** @param Collection<int, CounterpartyPayment> $payments
     * @return Collection<int, Collection<int, CounterpartyAllocationEntry>>
     */
    public function activeAllocationsForPayments(Collection $payments): Collection
    {
        if ($payments->isEmpty()) {
            return collect();
        }

        return $this->activeAllocationQuery()->whereIn('payment_id', $payments->pluck('id'))->with('obligation:id,uuid')->get()->groupBy('payment_id');
    }

    private function activeAllocationQuery(): Builder
    {
        return CounterpartyAllocationEntry::query()->where('amount_minor', '>', 0)
            ->whereNotIn('id', CounterpartyAllocationEntry::query()->select('reversal_of_id')->whereNotNull('reversal_of_id'));
    }
}
