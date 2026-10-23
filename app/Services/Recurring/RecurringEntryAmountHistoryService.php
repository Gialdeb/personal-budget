<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEntryTypeEnum;
use App\Models\RecurringEntry;
use Carbon\CarbonImmutable;

class RecurringEntryAmountHistoryService
{
    public function __construct(protected RecurringEntryAmountResolver $amountResolver) {}

    public function record(RecurringEntry $entry, RecurringEntry $original, ?string $effectiveFrom): void
    {
        if ($entry->entry_type !== $original->entry_type) {
            return;
        }
        $previousCents = $this->baseAmountCents($original);
        $newCents = $this->baseAmountCents($entry);
        if ($previousCents === $newCents) {
            return;
        }
        if ($original->promotion !== null) {
            $previousCents = (int) round((float) $this->amountResolver->resolve(
                $original,
                CarbonImmutable::parse($effectiveFrom ?? CarbonImmutable::tomorrow()->toDateString()),
                $original->amountChanges()->reorder('effective_from')->orderBy('id')->get(),
            ) * 100);
        }

        $entry->amountChanges()->create([
            'previous_amount' => $previousCents / 100,
            'new_amount' => $newCents / 100,
            'effective_from' => $effectiveFrom ?? CarbonImmutable::tomorrow()->toDateString(),
            'currency' => $entry->currency,
            'recurrence_comparison' => [
                'previous_type' => $original->recurrence_type?->value, 'previous_interval' => $original->recurrence_interval,
                'new_type' => $entry->recurrence_type?->value, 'new_interval' => $entry->recurrence_interval,
                'comparable' => $original->recurrence_type === $entry->recurrence_type && $original->recurrence_interval === $entry->recurrence_interval,
            ],
        ]);
    }

    protected function baseAmountCents(RecurringEntry $entry): int
    {
        if ($entry->entry_type === RecurringEntryTypeEnum::INSTALLMENT) {
            return intdiv((int) round((float) $entry->total_amount * 100), max(1, (int) $entry->installments_count));
        }

        return (int) round((float) $entry->expected_amount * 100);
    }
}
