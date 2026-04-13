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
            $occurrence->refresh();
            $occurrence->loadMissing('recurringEntry', 'convertedTransaction');

            if ($occurrence->convertedTransaction instanceof Transaction) {
                return $occurrence->convertedTransaction;
            }

            $entry = $occurrence->recurringEntry;
            $transactionDate = $occurrence->due_date?->toDateString()
                ?? $occurrence->expected_date->toDateString();
            $effectiveTransactionDate = (string) ($metadata['transaction_date'] ?? $transactionDate);
            $snapshot = $this->transactionExchangeSnapshotService->buildForAccount(
                $entry->account()->with('user:id,base_currency_code')->firstOrFail(),
                (float) $occurrence->expected_amount,
                $effectiveTransactionDate,
            );

            $transaction = Transaction::query()->create([
                'user_id' => $entry->user_id,
                'created_by_user_id' => $actor?->id ?? $entry->updated_by_user_id ?? $entry->created_by_user_id ?? $entry->user_id,
                'updated_by_user_id' => $actor?->id ?? $entry->updated_by_user_id ?? $entry->created_by_user_id ?? $entry->user_id,
                'account_id' => $entry->account_id,
                'scope_id' => $entry->scope_id,
                'category_id' => $entry->category_id,
                'merchant_id' => $entry->merchant_id,
                'tracked_item_id' => $entry->tracked_item_id,
                'transaction_date' => $effectiveTransactionDate,
                'value_date' => $metadata['value_date'] ?? $effectiveTransactionDate,
                'direction' => $entry->direction->value,
                'kind' => TransactionKindEnum::SCHEDULED->value,
                'amount' => $occurrence->expected_amount,
                'currency' => $entry->currency,
                ...$snapshot,
                'description' => $metadata['description'] ?? $entry->title,
                'notes' => $metadata['notes'] ?? $occurrence->notes ?? $entry->notes ?? $entry->description,
                'source_type' => TransactionSourceTypeEnum::GENERATED->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'recurring_entry_occurrence_id' => $occurrence->id,
            ]);

            $occurrence->forceFill([
                'converted_transaction_id' => $transaction->id,
                'status' => RecurringOccurrenceStatusEnum::COMPLETED,
            ])->save();

            $this->transactionMutationService->recalculateAccount($transaction->account);

            return $transaction->fresh(['recurringOccurrence', 'account', 'category', 'trackedItem', 'createdByUser', 'updatedByUser']);
        });
    }
}
