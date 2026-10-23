<?php

namespace App\Services\Recurring;

use App\Models\RecurringEntry;
use App\Models\RecurringEntryAmountChange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RecurringEntryAmountResolver
{
    /**
     * Resolve planned amounts only; persisted occurrence overrides remain owned by synchronization.
     * The entry is the original plan, not the calendar clone used for a reactivated period.
     * Manual history overrides the promotion, ordered by effective_from and id.
     * Without a promotion, callers retain their existing history policy.
     * Descriptive price conditions are never economic inputs.
     *
     * @param  Collection<int, RecurringEntryAmountChange>|null  $manualChanges
     */
    public function resolve(RecurringEntry $entry, CarbonImmutable $date, ?Collection $manualChanges = null): string|float|null
    {
        $amount = $manualChanges?->first()?->previous_amount ?? $entry->expected_amount;
        $promotion = $entry->promotion;
        if ($promotion !== null && $date->toDateString() >= $promotion['starts_on']) {
            $amount = $promotion['outcome'] === 'normal' && $date->toDateString() >= $promotion['ends_before']
                ? $promotion['normal_amount']
                : $promotion['amount'];
        }

        foreach ($manualChanges ?? [] as $change) {
            if ($change->effective_from->toDateString() <= $date->toDateString()) {
                $amount = $change->new_amount;
            }
        }

        return $amount;
    }

    /** @param Collection<int, RecurringEntryAmountChange>|null $manualChanges */
    public function needsReview(RecurringEntry $entry, CarbonImmutable $date, ?Collection $manualChanges = null): bool
    {
        $promotion = $entry->promotion;
        if ($promotion === null || $promotion['outcome'] !== 'unknown' || $date->toDateString() < $promotion['ends_before']) {
            return false;
        }
        foreach ($manualChanges ?? [] as $change) {
            if ($change->effective_from->toDateString() <= $date->toDateString()) {
                return false;
            }
        }

        return true;
    }
}
