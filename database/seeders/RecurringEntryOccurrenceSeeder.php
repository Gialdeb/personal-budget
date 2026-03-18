<?php

namespace Database\Seeders;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            foreach (range(1, 12) as $month) {
                $year = 2025;

                $expectedDate = match ($entry->recurrence_type->value) {
                    'monthly' => sprintf('%04d-%02d-%02d', $year, $month, $entry->due_day ?: 1),
                    default => null,
                };

                if (! $expectedDate) {
                    continue;
                }

                $matched = false;
                if ($entry->category_id && $entry->account_id) {
                    $matched = $entry->account
                        ->transactions()
                        ->whereDate('transaction_date', '>=', date('Y-m-01', strtotime($expectedDate)))
                        ->whereDate('transaction_date', '<=', date('Y-m-t', strtotime($expectedDate)))
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
                        'notes' => 'Occorrenza seed 2025',
                    ]
                );
            }
        }
    }
}
