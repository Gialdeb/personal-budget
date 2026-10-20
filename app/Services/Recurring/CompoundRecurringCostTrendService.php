<?php

namespace App\Services\Recurring;

use App\Models\CompoundRecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;

/** Informational projection only: never changes settlement or schedule amounts. */
class CompoundRecurringCostTrendService
{
    /** @return array<int, array<string, mixed>> */
    public function years(CompoundRecurringEntry $plan): array
    {
        $plan->loadMissing('components.recurringEntry.periods');
        $components = $plan->components->keyBy('recurring_entry_id');
        $rows = RecurringEntryOccurrence::query()->whereIn('recurring_entry_id', $components->keys())
            ->get()->keyBy('id');
        $linkedIds = $rows->flatMap(fn ($row): array => [$row->converted_transaction_id, $row->matched_transaction_id])->filter()->unique();
        $transactions = Transaction::query()->where('currency', $plan->currency)->where('direction', $plan->direction)
            ->where('status', 'confirmed')->whereIn('kind', ['manual', 'scheduled'])->where('is_transfer', false)
            ->whereDate('transaction_date', '<=', today())
            ->where(fn ($query) => $query->whereIn('recurring_entry_occurrence_id', $rows->keys())->orWhereIn('id', $linkedIds)
                ->orWhereHas('occurrencePaymentAllocations', fn ($query) => $query->whereIn('occurrence_id', $rows->keys())))
            ->with(['refundTransaction', 'occurrencePaymentAllocations'])->get();
        $paid = [];
        foreach ($transactions as $transaction) {
            if ($transaction->occurrencePaymentAllocations->isNotEmpty()) {
                if ($transaction->refundTransaction?->status?->value === 'confirmed') {
                    continue;
                }
                foreach ($transaction->occurrencePaymentAllocations as $allocation) {
                    if ($rows->has($allocation->occurrence_id)) {
                        $paid[$allocation->occurrence_id] = ($paid[$allocation->occurrence_id] ?? 0) + $allocation->amount_cents;
                    }
                }

                continue;
            }
            $owners = $transaction->recurring_entry_occurrence_id !== null
                ? $rows->only([$transaction->recurring_entry_occurrence_id])
                : $rows->filter(fn ($row): bool => $row->converted_transaction_id === $transaction->id || $row->matched_transaction_id === $transaction->id);
            if ($owners->count() === 1) {
                $id = $owners->first()->id;
                $paid[$id] = ($paid[$id] ?? 0) + $transaction->activeAmountCents();
            }
        }
        $result = [];
        foreach ($rows->groupBy(fn ($row): int => $row->expected_date->year)->sortKeys() as $year => $yearRows) {
            $total = 0;
            $breakdown = [];
            $complete = $year < today()->year;
            $signature = [];
            foreach ($components as $entryId => $component) {
                $entry = $component->recurringEntry;
                $componentRows = $yearRows->where('recurring_entry_id', $entryId);
                $cents = $componentRows->sum(fn ($row): int => $paid[$row->id] ?? 0);
                $relevant = $entry->start_date->year <= $year && ($component->archived_at === null || $component->archived_at->year >= $year);
                if (! $relevant && $cents === 0) {
                    continue;
                }
                $total += $cents;
                $signature[] = $component->uuid;
                $breakdown[] = ['uuid' => $component->uuid, 'name' => $component->displayName(), 'archived' => $component->archived_at !== null, 'total' => $cents / 100];
                $operative = $componentRows->filter(fn ($row): bool => $row->status?->value !== 'cancelled');
                $period = $entry->periods->filter(fn ($period): bool => $period->starts_on->toDateString() <= $year.'-01-01'
                    && ($period->ends_on === null || $period->ends_on->toDateString() >= $year.'-12-31'));
                $complete = $complete && $relevant && $period->count() === 1 && $operative->count() === 1
                    && ! in_array($operative->first()?->status?->value, ['skipped', 'refunded'], true)
                    && $cents > 0 && ($paid[$operative->first()?->id] ?? 0) >= (int) round((float) $operative->first()?->expected_amount * 100)
                    && $componentRows->filter(fn ($row): bool => $row->status?->value === 'cancelled')->sum(fn ($row): int => $paid[$row->id] ?? 0) === 0;
            }
            if ($total === 0) {
                continue;
            }
            sort($signature);
            $previous = $result[$year - 1] ?? null;
            $comparison = $complete && $previous !== null && $previous['complete'] && $previous['signature'] === $signature
                ? ['previous_year' => $year - 1, 'difference' => ($total - $previous['cents']) / 100,
                    'ratio' => $previous['cents'] > 0 ? ($total - $previous['cents']) / $previous['cents'] : null] : null;
            $result[$year] = ['year' => (int) $year, 'total' => $total / 100, 'components' => $breakdown,
                'complete' => $complete, 'comparison' => $comparison, 'signature' => $signature, 'cents' => $total];
        }

        return array_values(array_map(function (array $year): array {
            unset($year['signature'], $year['cents']);

            return $year;
        }, $result));
    }
}
