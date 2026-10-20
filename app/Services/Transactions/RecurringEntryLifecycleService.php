<?php

namespace App\Services\Transactions;

use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Services\Recurring\RecurringEntryOccurrenceGeneratorService;
use App\Services\Recurring\RecurringEntryPeriodService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Salary\SalaryOccurrenceService;
use App\Services\SharedExpenses\SharedExpenseAllocation;
use App\Services\SharedExpenses\SharedExpenseOccurrencePostingService;
use Carbon\CarbonImmutable;
use Throwable;

class RecurringEntryLifecycleService
{
    public function __construct(
        protected RecurringEntryOccurrenceGeneratorService $generatorService,
        protected RecurringEntryPostingService $postingService,
        protected SharedExpenseOccurrencePostingService $sharedExpenseOccurrencePostingService,
    ) {}

    public function runAutomationPipeline(): array
    {
        $generationResult = $this->runGenerationPhase();
        $postingResult = $this->runPostingPhase();

        $generatedOccurrences = (int) ($generationResult['generated_count'] ?? 0);
        $createdTransactions = (int) ($postingResult['created_count'] ?? 0);
        $warningCount = (int) ($generationResult['warning_count'] ?? 0)
            + (int) ($postingResult['warning_count'] ?? 0);
        $errorCount = (int) ($generationResult['error_count'] ?? 0)
            + (int) ($postingResult['error_count'] ?? 0);

        return [
            'processed_count' => $generatedOccurrences + $createdTransactions,
            'success_count' => $generatedOccurrences + $createdTransactions,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
            'generated_occurrences' => $generatedOccurrences,
            'created_transactions' => $createdTransactions,
            'generation' => $generationResult,
            'posting' => $postingResult,
        ];
    }

    protected function runGenerationPhase(): array
    {
        $generatedCount = 0;
        $warningCount = 0;
        $errorCount = 0;
        $today = CarbonImmutable::today();

        RecurringEntry::query()
            ->where('status', RecurringEntryStatusEnum::ACTIVE->value)
            ->where('is_active', true)
            ->where('auto_generate_occurrences', true)
            ->orderBy('id')
            ->each(function (RecurringEntry $entry) use (&$generatedCount, &$warningCount, &$errorCount, $today): void {
                try {
                    $generatedCount += $this->generatorService->generate($entry, $entry->generationHorizon($today))->count();
                } catch (Throwable) {
                    $errorCount++;
                }
            });

        return [
            'generated_count' => $generatedCount,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
        ];
    }

    protected function runPostingPhase(): array
    {
        $createdCount = 0;
        $warningCount = 0;
        $errorCount = 0;
        $today = CarbonImmutable::today()->toDateString();

        RecurringEntryOccurrence::query()
            ->select('recurring_entry_occurrences.*')
            ->join('recurring_entries', 'recurring_entries.id', '=', 'recurring_entry_occurrences.recurring_entry_id')
            ->where('recurring_entries.status', RecurringEntryStatusEnum::ACTIVE->value)
            ->where('recurring_entries.is_active', true)
            ->where('recurring_entries.auto_create_transaction', true)
            ->whereNull('recurring_entry_occurrences.converted_transaction_id')
            ->whereNull('recurring_entry_occurrences.matched_transaction_id')
            ->whereNull('recurring_entry_occurrences.automatic_posting_blocked_at')
            ->whereIn('recurring_entry_occurrences.status', [RecurringOccurrenceStatusEnum::PENDING->value, RecurringOccurrenceStatusEnum::GENERATED->value])
            ->where(function ($query) use ($today): void {
                $query->whereDate('recurring_entry_occurrences.due_date', '<=', $today)
                    ->orWhere(function ($fallback) use ($today): void {
                        $fallback->whereNull('recurring_entry_occurrences.due_date')
                            ->whereDate('recurring_entry_occurrences.expected_date', '<=', $today);
                    });
            })
            ->orderByRaw('COALESCE(recurring_entry_occurrences.due_date, recurring_entry_occurrences.expected_date)')
            ->orderBy('recurring_entry_occurrences.id')
            ->get()
            ->each(function (RecurringEntryOccurrence $occurrence) use (&$createdCount, &$warningCount, &$errorCount): void {
                try {
                    if (! app(RecurringEntryPeriodService::class)->allowsAutomaticPosting($occurrence)) {
                        return;
                    }
                    if (app(SalaryOccurrenceService::class)->isBeyondEmployment($occurrence)) {
                        return;
                    }
                    if ($occurrence->converted_transaction_id !== null) {
                        $warningCount++;

                        return;
                    }

                    if (! is_numeric($occurrence->expected_amount) || (float) $occurrence->expected_amount <= 0) {
                        return;
                    }

                    $paidBy = $occurrence->shared_expense_paid_by ?? $occurrence->recurringEntry?->shared_expense_paid_by;
                    if ($paidBy === SharedExpenseAllocation::PAID_BY_COUNTERPARTY) {
                        $this->sharedExpenseOccurrencePostingService->post($occurrence);
                    } else {
                        $this->postingService->post($occurrence, metadata: ['automatic_posting' => true]);
                    }

                    if ($occurrence->fresh()?->converted_transaction_id !== null) {
                        $createdCount++;
                    }
                } catch (Throwable) {
                    $errorCount++;
                }
            });

        return [
            'created_count' => $createdCount,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
        ];
    }
}
