<?php

namespace App\Services\Recurring;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Services\Salary\SalaryOccurrenceService;
use App\Services\SharedExpenses\SharedExpenseOccurrencePostingService;
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
                if (app(SalaryOccurrenceService::class)->isBeyondEmployment($occurrence)) {
                    continue;
                }
                if ($occurrence->automatic_posting_blocked_at !== null || $occurrence->matched_transaction_id !== null) {
                    continue;
                }
                if (! is_numeric($occurrence->expected_amount) || (float) $occurrence->expected_amount <= 0) {
                    continue;
                }
                if (in_array($occurrence->status, [RecurringOccurrenceStatusEnum::COMPLETED, RecurringOccurrenceStatusEnum::SKIPPED, RecurringOccurrenceStatusEnum::CANCELLED, RecurringOccurrenceStatusEnum::REFUNDED], true)) {
                    continue;
                }
                $occurrenceDate = $occurrence->due_date ?? $occurrence->expected_date;

                if ($occurrenceDate->greaterThan($postingCutoff)) {
                    continue;
                }

                if (($occurrence->shared_expense_paid_by ?? $entry->shared_expense_paid_by) === 'counterparty') {
                    app(SharedExpenseOccurrencePostingService::class)->post($occurrence);

                    continue;
                }
                $postedTransactions->push(
                    $this->posting->post($occurrence, metadata: ['automatic_posting' => true])
                );
            }
        }

        return [
            'occurrences' => $occurrences,
            'posted_transactions' => $postedTransactions,
        ];
    }
}
