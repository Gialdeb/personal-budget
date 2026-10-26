<?php

namespace App\Services\Recurring;

use App\Models\CounterpartyPayment;
use App\Models\RecurringEntryOccurrence;
use App\Models\RecurringOccurrencePaymentAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Support\MoneyAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocateOccurrencePaymentService
{
    public function recordPayment(RecurringEntryOccurrence $occurrence, Transaction $transaction, User $actor, string $amount, string $type, ?string $finalTotal): void
    {
        DB::transaction(function () use ($occurrence, $transaction, $actor, $amount, $type, $finalTotal): void {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $occurrence = RecurringEntryOccurrence::query()->with('recurringEntry')->lockForUpdate()->findOrFail($occurrence->id);
            $existing = $occurrence->paymentAllocations()->where('transaction_id', $transaction->id)->first();
            if ($existing !== null) {
                if ($existing->amount_cents !== MoneyAmount::cents($amount)
                    || ($type === 'final' && MoneyAmount::cents($finalTotal ?? '0') !== $occurrence->paymentTargetCents())) {
                    throw ValidationException::withMessages(['amount' => __('ui.this_transaction_is_already_allocated_with_a_different_amount_afe252e4')]);
                }

                return;
            }
            $this->preparePaymentTarget($occurrence, $amount, $type, $finalTotal);
            $this->allocate($occurrence, $transaction, $actor, $amount);
            $occurrence->refresh()->synchronizeSettlementState();
        });
    }

    public function preparePaymentTarget(RecurringEntryOccurrence $occurrence, string $amount, string $type, ?string $finalTotal, int $replacedPaymentCents = 0): void
    {
        $paid = $occurrence->allocatedPaymentCents() + $occurrence->personalPaymentCents() + $occurrence->externalPaymentCents() - $replacedPaymentCents;
        $payment = MoneyAmount::cents($amount);
        if ($type === 'advance') {
            if ($payment <= 0 || $paid + $payment >= $occurrence->paymentTargetCents()) {
                throw ValidationException::withMessages(['amount' => __('payment_management.advance_requires_residual')]);
            }

            return;
        }
        $total = MoneyAmount::cents($finalTotal ?? '0');
        if ($type !== 'final' || $total <= 0 || $paid + $payment !== $total
            || (! $occurrence->recurringEntry->is_amount_variable && $total !== $occurrence->paymentTargetCents())) {
            throw ValidationException::withMessages(['final_total_amount' => __('payment_management.final_total_mismatch')]);
        }
        $occurrence->forceFill([
            'payment_actual_amount' => $total / 100,
            'amount_confirmed_at' => now(),
        ])->save();
    }

    public function recordVariablePayment(RecurringEntryOccurrence $occurrence, Transaction $transaction, User $actor): void
    {
        DB::transaction(function () use ($occurrence, $transaction, $actor): void {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $occurrence = RecurringEntryOccurrence::query()->with('recurringEntry')->lockForUpdate()->findOrFail($occurrence->id);
            $entry = $occurrence->recurringEntry;
            $entry->ensureComponentIsMutable();
            abort_unless($entry->user_id === $actor->id && $transaction->user_id === $actor->id, 404);
            if (! $entry->is_amount_variable || $entry->salarySchedule !== null
                || ! in_array($occurrence->status?->value, ['pending', 'generated'], true)
                || ($occurrence->converted_transaction_id !== null && $occurrence->convertedTransaction?->payment_rectified_at === null) || $occurrence->matched_transaction_id !== null
                || $occurrence->paymentAllocations()->exists() || $occurrence->personalSettlementTransactions()->exists()
                || $occurrence->sharedExpenseLedgerEntry()->exists() || $entry->shared_expense_counterparty_id !== null
                || $transaction->recurring_entry_occurrence_id !== null || $transaction->occurrencePaymentAllocations()->exists()
                || $transaction->direction !== $entry->direction || $transaction->currency !== $entry->currency
                || $transaction->kind?->value !== 'manual' || $transaction->status?->value !== 'confirmed'
                || $transaction->is_transfer || $transaction->shared_expense_counterparty_id !== null
                || $transaction->refunded_transaction_id !== null || $transaction->related_transaction_id !== null
                || $transaction->creditDebtPayment()->exists() || $transaction->sharedExpenseSettlement()->exists()
                || CounterpartyPayment::query()->where('transaction_id', $transaction->id)->exists()
                || $transaction->refundTransaction()->exists()) {
                throw ValidationException::withMessages(['payment_occurrence_uuid' => __('ui.this_transaction_or_occurrence_cannot_be_allocated_1c160612')]);
            }

            $occurrence->forceFill(['payment_actual_amount' => $transaction->amount, 'payment_actual_date' => $transaction->transaction_date, 'amount_confirmed_at' => now()])->save();
            $transaction->forceFill(['recurring_entry_occurrence_id' => $occurrence->id])->save();
            $occurrence->forceFill(['converted_transaction_id' => $transaction->id])->save();
            $occurrence->synchronizeSettlementState();
        });
    }

    public function allocate(RecurringEntryOccurrence $occurrence, Transaction $transaction, User $actor, string $amount): RecurringOccurrencePaymentAllocation
    {
        return DB::transaction(function () use ($occurrence, $transaction, $actor, $amount): RecurringOccurrencePaymentAllocation {
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $occurrence = RecurringEntryOccurrence::query()->with('recurringEntry')->lockForUpdate()->findOrFail($occurrence->id);
            $entry = $occurrence->recurringEntry;
            $entry->ensureComponentIsMutable();
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
                || CounterpartyPayment::query()->where('transaction_id', $transaction->id)->exists()
                || $transaction->refundTransaction()->where('status', 'confirmed')->exists()
                || ! in_array($transaction->kind?->value, ['manual', 'scheduled'], true)
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
