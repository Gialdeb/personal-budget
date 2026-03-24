<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\TransactionKindEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UndoRecurringOccurrenceConversionService
{
    public function __construct(
        protected TransactionMutationService $transactionMutationService
    ) {}

    public function undo(RecurringEntryOccurrence $occurrence): RecurringEntryOccurrence
    {
        $occurrence->loadMissing(
            'recurringEntry',
            'convertedTransaction.account',
            'convertedTransaction.refundTransaction'
        );

        $transaction = $occurrence->convertedTransaction;

        if (! $transaction instanceof Transaction) {
            throw ValidationException::withMessages([
                'occurrence' => __('transactions.validation.recurring_conversion_not_found'),
            ]);
        }

        if ($transaction->kind !== TransactionKindEnum::SCHEDULED) {
            throw ValidationException::withMessages([
                'occurrence' => __('transactions.validation.recurring_conversion_undo_only_scheduled'),
            ]);
        }

        if ($transaction->refundTransaction instanceof Transaction) {
            throw ValidationException::withMessages([
                'occurrence' => __('transactions.validation.recurring_conversion_undo_refunded_blocked'),
            ]);
        }

        return DB::transaction(function () use ($occurrence): RecurringEntryOccurrence {
            $occurrence->refresh();
            $occurrence->loadMissing(
                'recurringEntry',
                'convertedTransaction.account',
                'convertedTransaction.refundTransaction'
            );

            $currentTransaction = $occurrence->convertedTransaction;

            if (! $currentTransaction instanceof Transaction) {
                throw ValidationException::withMessages([
                    'occurrence' => __('transactions.validation.recurring_conversion_not_found'),
                ]);
            }

            if ($currentTransaction->kind !== TransactionKindEnum::SCHEDULED) {
                throw ValidationException::withMessages([
                    'occurrence' => __('transactions.validation.recurring_conversion_undo_only_scheduled'),
                ]);
            }

            if ($currentTransaction->refundTransaction instanceof Transaction) {
                throw ValidationException::withMessages([
                    'occurrence' => __('transactions.validation.recurring_conversion_undo_refunded_blocked'),
                ]);
            }

            $account = $currentTransaction->account;
            $entry = $occurrence->recurringEntry;
            $occurrenceDate = $occurrence->due_date ?? $occurrence->expected_date;

            $occurrence->forceFill([
                'converted_transaction_id' => null,
                'status' => RecurringOccurrenceStatusEnum::PENDING,
            ])->save();

            $currentTransaction->forceDelete();

            if ($account !== null) {
                $this->transactionMutationService->recalculateAccount($account);
            }

            if ($entry !== null) {
                $this->refreshEntryState($entry, $occurrenceDate?->toDateString());
            }

            return $occurrence->fresh([
                'recurringEntry',
                'convertedTransaction',
            ]);
        });
    }

    protected function refreshEntryState(RecurringEntry $entry, ?string $occurrenceDate): void
    {
        if ($entry->status === RecurringEntryStatusEnum::COMPLETED) {
            $entry->forceFill([
                'status' => $entry->is_active
                    ? RecurringEntryStatusEnum::ACTIVE
                    : RecurringEntryStatusEnum::PAUSED,
            ])->save();
        }

        if ($occurrenceDate === null) {
            return;
        }

        if ($entry->next_occurrence_date === null || $occurrenceDate < $entry->next_occurrence_date->toDateString()) {
            $entry->forceFill([
                'next_occurrence_date' => $occurrenceDate,
            ])->save();
        }
    }
}
