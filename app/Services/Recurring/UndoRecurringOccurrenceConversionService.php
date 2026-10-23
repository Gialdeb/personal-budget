<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEntryStatusEnum;
use App\Enums\TransactionKindEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UndoRecurringOccurrenceConversionService
{
    public function __construct(
        protected TransactionMutationService $transactionMutationService
    ) {}

    public function undo(RecurringEntryOccurrence $occurrence, bool $externalOnly = false): RecurringEntryOccurrence
    {
        if ($occurrence->sharedExpenseLedgerEntry()->exists() && ($externalOnly || $occurrence->convertedTransaction === null || $occurrence->convertedTransaction->refundTransaction !== null)) {
            return DB::transaction(function () use ($occurrence): RecurringEntryOccurrence {
                $occurrence = RecurringEntryOccurrence::query()->lockForUpdate()->findOrFail($occurrence->id);
                $occurrence->recurringEntry->ensureComponentIsMutable();
                $occurrence->sharedExpenseLedgerEntry()->firstOrFail()->delete();
                $this->refreshEntryState($occurrence->recurringEntry, ($occurrence->due_date ?? $occurrence->expected_date)?->toDateString());

                return $occurrence->fresh();
            });
        }
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

        return app(ManageOccurrencePaymentService::class)->manage($occurrence, auth()->user() ?? $occurrence->recurringEntry->user, [
            'request_id' => (string) Str::uuid(), 'reason' => 'not_charged', 'action' => 'pending', 'confirm_rectification' => true,
        ]);
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
