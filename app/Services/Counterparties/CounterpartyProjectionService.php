<?php

namespace App\Services\Counterparties;

use App\Models\CounterpartyAllocationEntry;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Support\CurrencyMinorUnits;
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
        $snapshot = $last?->metadata['legacy_snapshot'] ?? [];

        return ['economic_date' => $snapshot['economic_date'] ?? $payment->economic_date->toDateString(), 'amount_minor' => $snapshot['amount_minor'] ?? $payment->amount_minor, 'original_amount_minor' => $payment->amount_minor,
            'account_uuid' => $snapshot['account_uuid'] ?? $payment->account_uuid, 'original_account_uuid' => $payment->account_uuid,
            'transaction_uuid' => $snapshot['transaction_uuid'] ?? $payment->transaction_uuid];
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
        $snapshot = $last?->metadata['snapshot'] ?? $obligation->metadata ?? [];

        return [
            'uuid' => $obligation->uuid,
            'economic_identity' => $obligation->economic_identity,
            'source_type' => $snapshot['source_type'] ?? $obligation->source_type->value,
            'source_identity' => $snapshot['source_identity'] ?? $obligation->source_identity,
            'economic_date' => $snapshot['economic_date'] ?? $obligation->economic_date->toDateString(),
            'due_date' => array_key_exists('due_date', $snapshot) ? $snapshot['due_date'] : $obligation->due_date?->toDateString(),
            'recognized_minor' => $this->recognized($obligation),
            'allocated_minor' => $this->allocatedToObligation($obligation),
            'remaining_minor' => $this->remaining($obligation),
            'direction_sign' => ($this->recognized($obligation) ?: $obligation->recognized_minor) > 0 ? 1 : -1,
            'component_snapshots' => $snapshot['component_snapshots'] ?? [],
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function openObligations(CounterpartyRelation $relation): Collection
    {
        return $relation->obligations()->orderBy('economic_date')->orderBy('id')->get()
            ->map(fn (CounterpartyObligation $obligation): array => $this->obligation($obligation))
            ->filter(fn (array $obligation): bool => $obligation['remaining_minor'] > 0)
            ->sortBy(fn (array $obligation): string => $obligation['economic_date'].':'.$obligation['uuid'])->values();
    }

    /** @return array{ledger_minor: int, explained_minor: int, delta_minor: int, consistent: bool, anomalies: list<string>} */
    public function reconcile(CounterpartyRelation $relation): array
    {
        $explained = 0;
        $anomalies = [];
        foreach ($relation->obligations()->get() as $obligation) {
            $remaining = $this->remaining($obligation);
            if ($remaining < 0) {
                $anomalies[] = 'over_allocated_obligation:'.$obligation->uuid;
            }
            if ($obligation->ledgerEntries()->doesntExist()) {
                $anomalies[] = 'unrecognized_obligation:'.$obligation->uuid;
            }
            $explained = CurrencyMinorUnits::add($explained, $remaining * (($this->recognized($obligation) ?: $obligation->recognized_minor) > 0 ? 1 : -1));
        }
        foreach ($relation->payments()->get() as $payment) {
            $unallocated = $this->unallocated($payment);
            if ($unallocated < 0) {
                $anomalies[] = 'over_allocated_payment:'.$payment->uuid;
            }
            if ($this->effectivePayment($payment) < 0) {
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
        $query = CounterpartyAllocationEntry::query()->where('amount_minor', '>', 0)
            ->whereNotIn('id', CounterpartyAllocationEntry::query()->select('reversal_of_id')->whereNotNull('reversal_of_id'));

        return $query->where($parent instanceof CounterpartyPayment ? 'payment_id' : 'compensation_entry_id', $parent->id)->get();
    }
}
