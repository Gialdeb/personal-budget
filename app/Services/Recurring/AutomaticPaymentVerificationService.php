<?php

namespace App\Services\Recurring;

use App\Enums\TransactionKindEnum;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomaticPaymentVerificationService
{
    public function __construct(protected UndoRecurringOccurrenceConversionService $undoConversionService) {}

    public function confirm(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $this->lockedOccurrence($transaction);
            $current = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($current->payment_verified_at === null) {
                $current->forceFill([
                    'payment_verified_at' => now(),
                    'payment_verification_method' => 'manual',
                ])->save();
            }
        });
    }

    public function reportProblem(Transaction $transaction): RecurringEntryOccurrence
    {
        return DB::transaction(function () use ($transaction): RecurringEntryOccurrence {
            $occurrence = $this->lockedOccurrence($transaction);
            $current = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($current->refundTransaction()->exists() || $current->matchedRecurringOccurrences()->exists()) {
                throw ValidationException::withMessages(['payment' => __('ui.the_posting_has_other_links_and_cannot_be_invalidated_3dfeed15')]);
            }
            $occurrence = $this->undoConversionService->undo($occurrence);
            $occurrence->forceFill([
                'automatic_posting_blocked_at' => now(),
                'payment_failed_at' => now(),
            ])->save();

            return $occurrence;
        });
    }

    public function confirmExpired(): int
    {
        return Transaction::query()
            ->whereNotNull('payment_verification_started_at')
            ->whereNull('payment_verified_at')
            ->where('payment_verification_due_at', '<=', now())
            ->update([
                'payment_verified_at' => now(),
                'payment_verification_method' => 'automatic',
            ]);
    }

    protected function lockedOccurrence(Transaction $transaction): RecurringEntryOccurrence
    {
        $occurrence = RecurringEntryOccurrence::query()->lockForUpdate()
            ->find($transaction->recurring_entry_occurrence_id);
        if ($occurrence === null
            || $occurrence->converted_transaction_id !== $transaction->id
            || $occurrence->matched_transaction_id !== null
            || $transaction->kind !== TransactionKindEnum::SCHEDULED
            || $transaction->payment_verification_started_at === null) {
            throw ValidationException::withMessages(['payment' => __('ui.this_transaction_is_not_an_automatic_posting_that_can_be_verifie_b94a80ff')]);
        }

        return $occurrence;
    }
}
