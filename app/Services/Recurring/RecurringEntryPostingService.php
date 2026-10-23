<?php

namespace App\Services\Recurring;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Salary\SalaryOccurrenceService;
use App\Services\SharedExpenses\SharedOccurrenceCounterpartyResolver;
use App\Services\Transactions\TransactionExchangeSnapshotService;
use App\Services\Transactions\TransactionMutationService;
use App\Services\UserYearService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringEntryPostingService
{
    public function __construct(
        protected TransactionMutationService $transactionMutationService,
        protected TransactionExchangeSnapshotService $transactionExchangeSnapshotService,
        protected SharedOccurrenceCounterpartyResolver $counterparties,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function post(RecurringEntryOccurrence $occurrence, ?User $actor = null, array $metadata = []): Transaction
    {
        return DB::transaction(function () use ($occurrence, $actor, $metadata): Transaction {
            $occurrence = RecurringEntryOccurrence::query()
                ->with(['recurringEntry', 'convertedTransaction'])
                ->lockForUpdate()
                ->findOrFail($occurrence->getKey());

            if ($occurrence->convertedTransaction instanceof Transaction && $occurrence->remainingPaymentCents() === 0) {
                return $occurrence->convertedTransaction;
            }

            $entry = $occurrence->recurringEntry()->lockForUpdate()->firstOrFail();
            $entry->ensureComponentIsMutable();
            $occurrence->setRelation('recurringEntry', $entry);
            if ($occurrence->isRetiredRefundedHistory()) {
                throw ValidationException::withMessages(['occurrence' => __('ui.this_occurrence_cannot_be_converted_in_its_current_state_b799ab22')]);
            }
            if (app(SalaryOccurrenceService::class)->isBeyondEmployment($occurrence)) {
                throw ValidationException::withMessages(['occurrence' => __('salary.outside_employment')]);
            }
            if ($entry->trial_duration && today()->lt($entry->firstBillingDate())) {
                throw ValidationException::withMessages(['occurrence' => __('ui.the_trial_period_is_still_active_5538e145')]);
            }
            if (($metadata['shared_expense_paid_by'] ?? $occurrence->shared_expense_paid_by ?? $entry->shared_expense_paid_by) === 'counterparty') {
                throw ValidationException::withMessages(['occurrence' => __('ui.this_occurrence_cannot_be_converted_in_its_current_state_b799ab22')]);
            }
            if ($occurrence->paymentAllocations()->exists()) {
                throw ValidationException::withMessages(['occurrence' => __('ui.record_payments_against_the_remaining_balance_through_allocation_c3f34351')]);
            }
            if (($occurrence->matchedTransaction !== null && $occurrence->matchedTransaction->refundTransaction === null) || ! in_array($occurrence->status, [RecurringOccurrenceStatusEnum::PENDING, RecurringOccurrenceStatusEnum::GENERATED, RecurringOccurrenceStatusEnum::REFUNDED], true)) {
                throw ValidationException::withMessages(['occurrence' => __('ui.this_occurrence_cannot_be_converted_in_its_current_state_b799ab22')]);
            }
            $paymentCents = $occurrence->remainingPaymentCents();
            if ($paymentCents <= 0 || (isset($metadata['amount']) && (int) round((float) $metadata['amount'] * 100) !== $paymentCents)) {
                throw ValidationException::withMessages(['amount' => __('form_errors.record_the_remaining_occurrence_amount')]);
            }
            $paymentAmount = $paymentCents / 100;
            $automatic = (bool) ($metadata['automatic_posting'] ?? false);
            if ($automatic && (! is_numeric($occurrence->expected_amount) || (float) $occurrence->expected_amount <= 0)) {
                throw ValidationException::withMessages(['amount' => __('ui.invalid_amount_8c8c67cb')]);
            }
            if ($automatic && $occurrence->automatic_posting_blocked_at !== null) {
                throw ValidationException::withMessages(['occurrence' => __('ui.the_payment_requires_manual_posting_1d0f7014')]);
            }
            $verificationStartedAt = $automatic ? now() : null;
            $transactionDate = $occurrence->due_date?->toDateString()
                ?? $occurrence->expected_date->toDateString();
            $effectiveTransactionDate = (string) ($metadata['transaction_date'] ?? $transactionDate);
            if ($entry->salarySchedule) {
                app(UserYearService::class)->ensureDateYearIsOpen($entry->user, $effectiveTransactionDate, 'occurrence');
            }
            $account = $occurrence->account()
                ->with('user:id,base_currency_code')
                ->first()
                ?? $entry->account()->with('user:id,base_currency_code')->firstOrFail();
            if (! empty($metadata['account_uuid'])) {
                $account = app(AccessibleAccountsQuery::class)->editable($actor ?? $entry->user)
                    ->where('accounts.uuid', $metadata['account_uuid'])->where('currency', $entry->currency)
                    ->with('user:id,base_currency_code')->first();
                if ($account === null) {
                    throw ValidationException::withMessages(['account_uuid' => __('form_errors.select_an_editable_account_in_the_plan_currency')]);
                }
            }
            $counterparty = $this->counterparties->forPosting($occurrence);
            if ($counterparty !== null && $account->currency !== $entry->currency) {
                throw ValidationException::withMessages(['occurrence' => __('form_errors.the_account_currency_does_not_match_the_economic_identity_of_the_occurrence')]);
            }
            $expectedCents = (int) round((float) $occurrence->expected_amount * 100);
            $myShareCents = $counterparty === null ? null : intdiv($paymentCents * ($occurrence->shared_expense_my_share_cents ?? $entry->shared_expense_my_share_cents ?? intdiv($expectedCents, 2)), $expectedCents);
            $snapshot = $this->transactionExchangeSnapshotService->buildForAccount(
                $account,
                $paymentAmount,
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
                'shared_expense_counterparty_id' => $counterparty?->id,
                'shared_expense_paid_by' => $counterparty === null ? null : ($metadata['shared_expense_paid_by'] ?? $occurrence->shared_expense_paid_by ?? $entry->shared_expense_paid_by),
                'shared_expense_my_share_cents' => $myShareCents,
                'shared_expense_counterparty_share_cents' => $myShareCents === null ? null : $paymentCents - $myShareCents,
                'transaction_date' => $effectiveTransactionDate,
                'value_date' => $metadata['value_date'] ?? $effectiveTransactionDate,
                'direction' => $entry->direction->value,
                'kind' => TransactionKindEnum::SCHEDULED->value,
                'amount' => $paymentAmount,
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
        }, 5);
    }
}
