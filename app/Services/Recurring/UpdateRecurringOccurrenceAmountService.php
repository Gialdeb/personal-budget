<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SharedExpenses\SharedExpenseAllocation;
use App\Services\Transactions\TransactionExchangeSnapshotService;
use App\Services\Transactions\TransactionMutationService;
use App\Services\UserYearService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRecurringOccurrenceAmountService
{
    public function __construct(
        protected TransactionExchangeSnapshotService $transactionExchangeSnapshotService,
        protected TransactionMutationService $transactionMutationService,
        protected UserYearService $userYearService,
        protected SharedExpenseAllocation $sharedExpenseAllocation,
    ) {}

    public function update(
        RecurringEntryOccurrence $occurrence,
        User $actor,
        float $amount,
    ): RecurringEntryOccurrence {
        $normalizedAmount = round($amount, 2);

        return DB::transaction(function () use ($occurrence, $actor, $normalizedAmount): RecurringEntryOccurrence {
            $lockedOccurrence = RecurringEntryOccurrence::query()
                ->lockForUpdate()
                ->findOrFail($occurrence->getKey());
            $entry = RecurringEntry::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($lockedOccurrence->recurring_entry_id);
            $entry->ensureComponentIsMutable();

            $this->ensureAmountCanBeUpdated($lockedOccurrence, $entry);
            $salaryDetail = $lockedOccurrence->salaryDetail;
            if ($salaryDetail && $lockedOccurrence->paymentAllocations()->exists()) {
                throw ValidationException::withMessages(['amount' => __('salary.composition_locked')]);
            }

            $transaction = $this->lockedConvertedTransaction($lockedOccurrence);
            $effectiveDate = $transaction?->transaction_date?->toDateString()
                ?? $lockedOccurrence->due_date?->toDateString()
                ?? $lockedOccurrence->expected_date->toDateString();

            $this->userYearService->ensureDateYearIsOpen($entry->user, $effectiveDate, 'amount');

            $sharedAttributes = [];
            if ($entry->shared_expense_counterparty_id !== null) {
                $allocation = $this->sharedExpenseAllocation->rescale(
                    number_format($normalizedAmount, 2, '.', ''),
                    $entry->shared_expense_my_share_cents,
                    $entry->shared_expense_counterparty_share_cents,
                );
                $sharedAttributes = [
                    'shared_expense_my_share_cents' => $allocation['my_share_cents'],
                    'shared_expense_counterparty_share_cents' => $allocation['counterparty_share_cents'],
                ];
            }

            $lockedOccurrence->forceFill([
                ...$sharedAttributes,
                'expected_amount' => $salaryDetail && $transaction !== null ? $salaryDetail->expectedTotal() : $normalizedAmount,
                'amount_confirmed_at' => now(),
            ])->save();

            if ($salaryDetail && $transaction === null) {
                $components = (int) round((float) $salaryDetail->components()->sum('amount') * 100);
                $base = (int) round($normalizedAmount * 100) - $components;
                if ($base < 0) {
                    throw ValidationException::withMessages(['amount' => __('salary.total_positive')]);
                }
                $salaryDetail->update(['base_expected_amount' => $base / 100, 'is_overridden' => true]);
            }

            if ($transaction instanceof Transaction) {
                $account = $transaction->account()
                    ->with('user:id,base_currency_code')
                    ->firstOrFail();
                $snapshot = $this->transactionExchangeSnapshotService->buildForAccount(
                    $account,
                    $normalizedAmount,
                    $transaction->transaction_date->toDateString(),
                );

                $transaction->forceFill([
                    'amount' => $normalizedAmount,
                    ...$sharedAttributes,
                    ...$snapshot,
                    'updated_by_user_id' => $actor->id,
                ])->save();

                $this->transactionMutationService->recalculateAccount($account);
                $this->transactionMutationService->reconcileProcessedCreditCardCyclesForTransactions([$transaction]);
            }

            return $lockedOccurrence->fresh([
                'recurringEntry',
                'convertedTransaction.refundTransaction',
            ]);
        });
    }

    protected function ensureAmountCanBeUpdated(
        RecurringEntryOccurrence $occurrence,
        RecurringEntry $entry,
    ): void {
        if (
            ! $entry->is_amount_variable
            || $entry->entry_type !== RecurringEntryTypeEnum::RECURRING
        ) {
            throw ValidationException::withMessages([
                'amount' => __('transactions.validation.recurring_occurrence_amount_fixed'),
            ]);
        }

        if ($occurrence->matched_transaction_id !== null) {
            throw ValidationException::withMessages([
                'amount' => __('transactions.validation.recurring_occurrence_amount_matched'),
            ]);
        }

        $canUpdatePendingOccurrence = in_array($occurrence->status, [
            RecurringOccurrenceStatusEnum::PENDING,
            RecurringOccurrenceStatusEnum::GENERATED,
        ], true);
        $canUpdateCompletedOccurrence = $occurrence->status === RecurringOccurrenceStatusEnum::COMPLETED
            && $occurrence->converted_transaction_id !== null;

        if (! $canUpdatePendingOccurrence && ! $canUpdateCompletedOccurrence) {
            throw ValidationException::withMessages([
                'amount' => __('transactions.validation.recurring_occurrence_amount_locked'),
            ]);
        }
    }

    protected function lockedConvertedTransaction(RecurringEntryOccurrence $occurrence): ?Transaction
    {
        if ($occurrence->converted_transaction_id === null) {
            return null;
        }

        $transaction = Transaction::query()
            ->with('refundTransaction')
            ->lockForUpdate()
            ->find($occurrence->converted_transaction_id);

        if (
            ! $transaction instanceof Transaction
            || $transaction->recurring_entry_occurrence_id !== $occurrence->id
        ) {
            throw ValidationException::withMessages([
                'amount' => __('transactions.validation.recurring_occurrence_amount_transaction_unavailable'),
            ]);
        }

        if ($transaction->refundTransaction instanceof Transaction) {
            throw ValidationException::withMessages([
                'amount' => __('transactions.validation.recurring_occurrence_amount_refunded'),
            ]);
        }

        return $transaction;
    }
}
