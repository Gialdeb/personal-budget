<?php

namespace App\Services\Recurring;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transactions\TransactionExchangeSnapshotService;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringEntryPostingService
{
    public function __construct(
        protected TransactionMutationService $transactionMutationService,
        protected TransactionExchangeSnapshotService $transactionExchangeSnapshotService,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function post(RecurringEntryOccurrence $occurrence, ?User $actor = null, array $metadata = []): Transaction
    {
        $occurrence->loadMissing('recurringEntry', 'convertedTransaction');

        if ($occurrence->convertedTransaction instanceof Transaction) {
            return $occurrence->convertedTransaction;
        }

        return DB::transaction(function () use ($occurrence, $metadata): Transaction {
            $occurrence = RecurringEntryOccurrence::query()
                ->with(['recurringEntry', 'convertedTransaction'])
                ->lockForUpdate()
                ->findOrFail($occurrence->getKey());

            if ($occurrence->convertedTransaction instanceof Transaction) {
                return $occurrence->convertedTransaction;
            }

            $entry = $occurrence->recurringEntry;
            if ($entry->trial_duration && today()->lt($entry->firstBillingDate())) {
                throw ValidationException::withMessages(['occurrence' => __('ui.the_trial_period_is_still_active_5538e145')]);
            }
            if ($occurrence->paymentAllocations()->exists()) {
                throw ValidationException::withMessages(['occurrence' => __('ui.record_payments_against_the_remaining_balance_through_allocation_c3f34351')]);
            }
            if ($occurrence->matched_transaction_id !== null || ! in_array($occurrence->status, [RecurringOccurrenceStatusEnum::PENDING, RecurringOccurrenceStatusEnum::GENERATED], true)) {
                throw ValidationException::withMessages(['occurrence' => __('ui.this_occurrence_cannot_be_converted_in_its_current_state_b799ab22')]);
            }
            $automatic = (bool) ($metadata['automatic_posting'] ?? false);
            if ($automatic && $occurrence->automatic_posting_blocked_at !== null) {
                throw ValidationException::withMessages(['occurrence' => __('ui.the_payment_requires_manual_posting_1d0f7014')]);
            }
            $verificationStartedAt = $automatic ? now() : null;
            $transactionDate = $occurrence->due_date?->toDateString()
                ?? $occurrence->expected_date->toDateString();
            $effectiveTransactionDate = (string) ($metadata['transaction_date'] ?? $transactionDate);
            $account = $occurrence->account()
                ->with('user:id,base_currency_code')
                ->first()
                ?? $entry->account()->with('user:id,base_currency_code')->firstOrFail();
            $snapshot = $this->transactionExchangeSnapshotService->buildForAccount(
                $account,
                (float) $occurrence->expected_amount,
                $effectiveTransactionDate,
            );

            $transaction = Transaction::query()->create([
                'user_id' => $entry->user_id,
                'created_by_user_id' => $actor?->id ?? $entry->updated_by_user_id ?? $entry->created_by_user_id ?? $entry->user_id,
                'updated_by_user_id' => $actor?->id ?? $entry->updated_by_user_id ?? $entry->created_by_user_id ?? $entry->user_id,
                'account_id' => $account->id,
                'scope_id' => $entry->scope_id,
                'category_id' => $entry->category_id,
                'merchant_id' => $entry->merchant_id,
                'tracked_item_id' => $entry->tracked_item_id,
                'shared_expense_counterparty_id' => $entry->shared_expense_counterparty_id,
                'shared_expense_paid_by' => $occurrence->shared_expense_paid_by ?? $entry->shared_expense_paid_by,
                'shared_expense_my_share_cents' => $occurrence->shared_expense_my_share_cents ?? $entry->shared_expense_my_share_cents,
                'shared_expense_counterparty_share_cents' => $occurrence->shared_expense_counterparty_share_cents ?? $entry->shared_expense_counterparty_share_cents,
                'transaction_date' => $effectiveTransactionDate,
                'value_date' => $metadata['value_date'] ?? $effectiveTransactionDate,
                'direction' => $entry->direction->value,
                'kind' => TransactionKindEnum::SCHEDULED->value,
                'amount' => $occurrence->expected_amount,
                'currency' => $account->currency,
                ...$snapshot,
                'description' => $metadata['description'] ?? $entry->title,
                'notes' => $metadata['notes'] ?? $occurrence->notes ?? $entry->notes ?? $entry->description,
                'source_type' => TransactionSourceTypeEnum::GENERATED->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'recurring_entry_occurrence_id' => $occurrence->id,
                'payment_verification_started_at' => $verificationStartedAt,
                'payment_verification_due_at' => $verificationStartedAt?->copy()->addDays(7),
            ]);

            $occurrence->forceFill([
                'converted_transaction_id' => $transaction->id,
                'status' => RecurringOccurrenceStatusEnum::COMPLETED,
                'payment_failed_at' => null,
            ])->save();

            $this->transactionMutationService->recalculateAccount($transaction->account);

            return $transaction->fresh(['recurringOccurrence', 'account', 'category', 'trackedItem', 'createdByUser', 'updatedByUser']);
        });
    }
}
