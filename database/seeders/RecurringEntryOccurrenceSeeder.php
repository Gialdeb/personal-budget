<?php

namespace Database\Seeders;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class RecurringEntryOccurrenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entries = RecurringEntry::where('is_active', true)->get();

        foreach ($entries as $entry) {
            foreach ($this->expectedDatesFor($entry) as $expectedDate) {
                $matched = false;
                if ($entry->category_id && $entry->account_id) {
                    $expected = CarbonImmutable::parse($expectedDate);

                    $matched = $entry->account
                        ->transactions()
                        ->whereDate('transaction_date', '>=', $expected->startOfMonth()->toDateString())
                        ->whereDate('transaction_date', '<=', $expected->endOfMonth()->toDateString())
                        ->where('category_id', $entry->category_id)
                        ->exists();
                }

                RecurringEntryOccurrence::updateOrCreate(
                    [
                        'recurring_entry_id' => $entry->id,
                        'expected_date' => $expectedDate,
                    ],
                    [
                        'due_date' => $expectedDate,
                        'expected_amount' => $entry->expected_amount,
                        'status' => $matched
                            ? RecurringOccurrenceStatusEnum::MATCHED
                            : RecurringOccurrenceStatusEnum::PLANNED,
                        'matched_transaction_id' => null,
                        'converted_transaction_id' => null,
                        'notes' => 'Occorrenza seed 2024-2025',
                    ]
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function expectedDatesFor(RecurringEntry $entry): array
    {
        $dates = [];
        $startDate = CarbonImmutable::parse($entry->start_date)->startOfDay();
        $endDate = CarbonImmutable::parse($entry->end_date ?? $entry->start_date)->startOfDay();
        $cursor = $startDate->startOfMonth();

        while ($cursor->lessThanOrEqualTo($endDate->startOfMonth())) {
            $occurrenceDate = $cursor->day(min($entry->due_day ?: 1, $cursor->endOfMonth()->day));

            if ($occurrenceDate->greaterThanOrEqualTo($startDate) && $occurrenceDate->lessThanOrEqualTo($endDate)) {
                $dates[] = $occurrenceDate->toDateString();
            }

            $nextCursor = $this->advanceCursor($entry, $cursor);

            if (! $nextCursor) {
                break;
            }

            $cursor = $nextCursor;
        }

        return $dates;
    }

    private function advanceCursor(RecurringEntry $entry, CarbonImmutable $cursor): ?CarbonImmutable
    {
        return match ($entry->recurrence_type->value) {
            'monthly' => $cursor->addMonthsNoOverflow($entry->recurrence_interval ?: 1),
            'quarterly' => $cursor->addMonthsNoOverflow(3 * ($entry->recurrence_interval ?: 1)),
            'yearly' => $cursor->addYears($entry->recurrence_interval ?: 1),
            default => null,
        };
    }
}
