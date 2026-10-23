<?php

namespace App\Services\Recurring;

use App\Models\RecurringEntryOccurrence;
use App\Models\RecurringOccurrencePaymentAllocation;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use WeakMap;

/** Request-local snapshots for rendering only; mutation services keep reading fresh authoritative amounts. */
class RecurringOccurrenceReadService
{
    /** @var WeakMap<RecurringEntryOccurrence, array{allocated: int, personal: int, external: int, remaining: int, count: int}> */
    private WeakMap $snapshots;

    public function __construct()
    {
        $this->snapshots = new WeakMap;
    }

    /** @param Collection<int, RecurringEntryOccurrence> $occurrences */
    public function prepare(Collection $occurrences): void
    {
        if ($occurrences->isEmpty()) {
            return;
        }
        $ids = $occurrences->pluck('id')->all();
        (new \Illuminate\Database\Eloquent\Collection($occurrences->all()))->loadMissing('sharedExpenseLedgerEntry');
        $allocations = RecurringOccurrencePaymentAllocation::query()->whereIn('occurrence_id', $ids)
            ->valid()->selectRaw('occurrence_id, SUM(amount_cents) as total, COUNT(*) as aggregate')->groupBy('occurrence_id')->get()->keyBy('occurrence_id');
        $transactionIds = $occurrences->flatMap(fn ($occurrence): array => [$occurrence->converted_transaction_id, $occurrence->matched_transaction_id])->filter()->unique();
        $transactions = Transaction::query()->where(fn ($query) => $query->whereIn('recurring_entry_occurrence_id', $ids)->orWhereIn('id', $transactionIds))
            ->whereIn('kind', ['manual', 'scheduled'])->where('status', 'confirmed')
            ->with(['refundTransaction', 'occurrencePaymentAllocations'])->get();
        $byOccurrence = $transactions->groupBy('recurring_entry_occurrence_id');
        $byId = $transactions->keyBy('id');
        foreach ($occurrences as $occurrence) {
            $personalTransactions = ($byOccurrence[$occurrence->id] ?? collect())
                ->concat(collect([$byId->get($occurrence->converted_transaction_id), $byId->get($occurrence->matched_transaction_id)])->filter())
                ->unique('id')->reject(fn (Transaction $transaction): bool => $transaction->occurrencePaymentAllocations->contains('occurrence_id', $occurrence->id));
            $personal = $personalTransactions->sum(fn (Transaction $transaction): int => $transaction->activeAmountCents());
            $occurrence->setRelation('personalReadTransactions', $personalTransactions);
            $allocated = (int) ($allocations->get($occurrence->id)?->total ?? 0);
            $external = $occurrence->externalPaymentCents();
            $this->snapshots[$occurrence] = ['allocated' => $allocated, 'personal' => $personal, 'external' => $external,
                'remaining' => max(0, $occurrence->paymentTargetCents() - $allocated - $personal - $external),
                'count' => (int) ($allocations->get($occurrence->id)?->aggregate ?? 0)];
        }
    }

    public function remaining(RecurringEntryOccurrence $occurrence): int
    {
        return $this->snapshots[$occurrence]['remaining'] ?? $occurrence->remainingPaymentCents();
    }

    public function allocated(RecurringEntryOccurrence $occurrence): int
    {
        return $this->snapshots[$occurrence]['allocated'] ?? $occurrence->allocatedPaymentCents();
    }

    public function paid(RecurringEntryOccurrence $occurrence): int
    {
        $snapshot = $this->snapshots[$occurrence] ?? null;

        return $snapshot === null ? $occurrence->allocatedPaymentCents() + $occurrence->personalPaymentCents() + $occurrence->externalPaymentCents()
            : $snapshot['allocated'] + $snapshot['personal'] + $snapshot['external'];
    }

    public function count(RecurringEntryOccurrence $occurrence): int
    {
        return $this->snapshots[$occurrence]['count'] ?? $occurrence->paymentAllocations()->valid()->count();
    }
}
