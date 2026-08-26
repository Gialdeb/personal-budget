<?php

namespace App\Services\Recurring;

use App\Models\Account;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\ReminderDelivery;
use App\Models\Transaction;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;

class DeleteRecurringEntryService
{
    public function __construct(
        protected TransactionMutationService $transactionMutationService,
    ) {}

    public function delete(RecurringEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $occurrenceIds = $entry->occurrences()->pluck('id');
            $convertedTransactionIds = $entry->occurrences()
                ->whereNotNull('converted_transaction_id')
                ->pluck('converted_transaction_id');
            $linkedTransactionIds = Transaction::withTrashed()
                ->whereIn('recurring_entry_occurrence_id', $occurrenceIds)
                ->pluck('id')
                ->merge($convertedTransactionIds)
                ->unique()
                ->values();

            $refundTransactionIds = Transaction::withTrashed()
                ->whereIn('refunded_transaction_id', $linkedTransactionIds)
                ->pluck('id');
            $transactionIds = $linkedTransactionIds
                ->merge($refundTransactionIds)
                ->unique()
                ->values();
            $accountIds = Transaction::withTrashed()
                ->whereIn('id', $transactionIds)
                ->pluck('account_id')
                ->unique()
                ->values();

            ReminderDelivery::query()
                ->where('remindable_type', (new RecurringEntryOccurrence)->getMorphClass())
                ->whereIn('remindable_id', $occurrenceIds)
                ->delete();

            Transaction::withTrashed()
                ->whereIn('id', $refundTransactionIds)
                ->get()
                ->each->forceDelete();

            Transaction::withTrashed()
                ->whereIn('id', $linkedTransactionIds)
                ->get()
                ->each->forceDelete();

            $entry->delete();

            Account::query()
                ->whereIn('id', $accountIds)
                ->get()
                ->each(fn (Account $account) => $this->transactionMutationService->recalculateAccount($account));
        });
    }
}
