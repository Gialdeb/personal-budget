<?php

namespace App\Services\Recurring;

use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RecurringEntryLifecycleService
{
    public function __construct(
        protected RecurringEntryOccurrenceGeneratorService $generator,
        protected RecurringEntryPostingService $posting
    ) {}

    /**
     * @return array{occurrences: Collection<int, RecurringEntryOccurrence>, posted_transactions: Collection<int, Transaction>}
     */
    public function synchronize(RecurringEntry $entry, ?CarbonImmutable $throughDate = null, ?int $maxOccurrences = null): array
    {
        $occurrences = $this->generator->generate($entry, $throughDate, $maxOccurrences);
        $postedTransactions = collect();

        if ($entry->auto_create_transaction) {
            $postingCutoff = $throughDate ?? CarbonImmutable::today();

            $entry->load('occurrences.convertedTransaction');

            foreach ($entry->occurrences as $occurrence) {
                $occurrenceDate = $occurrence->due_date ?? $occurrence->expected_date;

                if ($occurrenceDate->greaterThan($postingCutoff)) {
                    continue;
                }

                $postedTransactions->push(
                    $this->posting->post($occurrence)
                );
            }
        }

        return [
            'occurrences' => $occurrences,
            'posted_transactions' => $postedTransactions,
        ];
    }
}
