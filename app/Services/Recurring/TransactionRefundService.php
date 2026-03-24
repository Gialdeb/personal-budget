<?php

namespace App\Services\Recurring;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Transaction;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionRefundService
{
    public function __construct(
        protected TransactionMutationService $transactionMutationService
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function refund(Transaction $transaction, array $attributes = []): Transaction
    {
        $transaction->refresh();
        $transaction->load('refundTransaction', 'recurringOccurrence');

        if ($transaction->kind === TransactionKindEnum::REFUND) {
            throw ValidationException::withMessages([
                'transaction' => 'Non è possibile rimborsare una transazione di rimborso.',
            ]);
        }

        if ($transaction->kind === TransactionKindEnum::OPENING_BALANCE) {
            throw ValidationException::withMessages([
                'transaction' => 'Non è possibile rimborsare un saldo iniziale.',
            ]);
        }

        if ($transaction->refundTransaction instanceof Transaction) {
            throw ValidationException::withMessages([
                'transaction' => 'La transazione risulta già rimborsata.',
            ]);
        }

        if ($transaction->recurringOccurrence !== null && ! $this->isLatestConvertedOccurrence($transaction)) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.recurring_refund_only_latest_occurrence'),
            ]);
        }

        return DB::transaction(function () use ($transaction, $attributes): Transaction {
            $refund = Transaction::query()->create([
                'user_id' => $transaction->user_id,
                'account_id' => $transaction->account_id,
                'scope_id' => $transaction->scope_id,
                'category_id' => $transaction->category_id,
                'merchant_id' => $transaction->merchant_id,
                'tracked_item_id' => $transaction->tracked_item_id,
                'transaction_date' => $attributes['transaction_date'] ?? $transaction->transaction_date,
                'value_date' => $attributes['value_date'] ?? $transaction->value_date ?? $transaction->transaction_date,
                'direction' => $transaction->direction === TransactionDirectionEnum::EXPENSE
                    ? TransactionDirectionEnum::INCOME->value
                    : TransactionDirectionEnum::EXPENSE->value,
                'kind' => TransactionKindEnum::REFUND->value,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'description' => $attributes['description'] ?? $transaction->description,
                'notes' => $attributes['notes'] ?? $transaction->notes,
                'source_type' => TransactionSourceTypeEnum::GENERATED->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'refunded_transaction_id' => $transaction->id,
            ]);

            if ($transaction->recurringOccurrence !== null) {
                $transaction->recurringOccurrence->forceFill([
                    'status' => RecurringOccurrenceStatusEnum::REFUNDED,
                ])->save();
            }

            $this->transactionMutationService->recalculateAccount($refund->account);

            return $refund->fresh(['refundedTransaction']);
        });
    }

    protected function isLatestConvertedOccurrence(Transaction $transaction): bool
    {
        $occurrence = $transaction->recurringOccurrence;

        if ($occurrence === null) {
            return true;
        }

        $latestConvertedOccurrenceId = $occurrence->newQuery()
            ->where('recurring_entry_id', $occurrence->recurring_entry_id)
            ->whereNotNull('converted_transaction_id')
            ->orderByRaw('COALESCE(due_date, expected_date) desc')
            ->orderByDesc('sequence_number')
            ->orderByDesc('id')
            ->value('id');

        return $latestConvertedOccurrenceId === $occurrence->id;
    }
}
