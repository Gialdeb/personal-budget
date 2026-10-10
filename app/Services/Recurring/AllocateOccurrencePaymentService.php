<?php

namespace App\Services\Recurring;

use App\Models\RecurringEntryOccurrence;
use App\Models\RecurringOccurrencePaymentAllocation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocateOccurrencePaymentService
{
    public function allocate(RecurringEntryOccurrence $occurrence, Transaction $transaction, User $actor, string $amount): RecurringOccurrencePaymentAllocation
    {
        return DB::transaction(function () use ($occurrence, $transaction, $actor, $amount): RecurringOccurrencePaymentAllocation {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $occurrence = RecurringEntryOccurrence::query()->with('recurringEntry')->lockForUpdate()->findOrFail($occurrence->id);
            $entry = $occurrence->recurringEntry;
            abort_unless($entry->user_id === $actor->id && $transaction->user_id === $actor->id, 404);
            $cents = (int) round((float) $amount * 100);
            if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount) || $cents <= 0) {
                throw ValidationException::withMessages(['amount' => __('ui.invalid_amount_8c8c67cb')]);
            }
            $existing = $occurrence->paymentAllocations()->where('transaction_id', $transaction->id)->first();
            if ($existing !== null) {
                if ($existing->amount_cents !== $cents) {
                    throw ValidationException::withMessages(['amount' => __('ui.this_transaction_is_already_allocated_with_a_different_amount_afe252e4')]);
                }

                return $existing;
            }
            if (! $occurrence->newQuery()->openObligations()->whereKey($occurrence->id)->exists()
                || $entry->shared_expense_counterparty_id !== null
                || $transaction->shared_expense_counterparty_id !== null
                || $transaction->recurring_entry_occurrence_id !== null
                || $transaction->creditDebtPayment()->exists()
                || $transaction->sharedExpenseSettlement()->exists()
                || $transaction->refundTransaction()->where('status', 'confirmed')->exists()
                || $transaction->status?->value !== 'confirmed'
                || $transaction->direction !== $entry->direction
                || $transaction->currency !== $entry->currency
                || $transaction->is_transfer
                || RecurringEntryOccurrence::query()->where('converted_transaction_id', $transaction->id)->orWhere('matched_transaction_id', $transaction->id)->exists()) {
                throw ValidationException::withMessages(['transaction_uuid' => __('ui.this_transaction_or_occurrence_cannot_be_allocated_1c160612')]);
            }
            $available = (int) round((float) $transaction->amount * 100) - (int) $transaction->occurrencePaymentAllocations()->sum('amount_cents');
            if ($cents > $available || $cents > $occurrence->remainingPaymentCents()) {
                throw ValidationException::withMessages(['amount' => __('ui.the_amount_exceeds_the_available_remaining_balance_69ce4205')]);
            }
            $allocation = $occurrence->paymentAllocations()->create(['transaction_id' => $transaction->id, 'amount_cents' => $cents]);
            $occurrence->forceFill(['automatic_posting_blocked_at' => $occurrence->automatic_posting_blocked_at ?? now()])->save();

            return $allocation;
        });
    }
}
