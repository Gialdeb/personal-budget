<?php

namespace App\Services\Recurring;

use App\Models\Account;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\RecurringPlanRefundPayment;
use App\Models\ReminderDelivery;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\Transaction;
use App\Services\Counterparties\CounterpartySourceGuard;
use App\Services\Counterparties\SharedCounterpartyShadowService;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteRecurringEntryService
{
    public function __construct(
        protected TransactionMutationService $transactionMutationService,
    ) {}

    public function delete(RecurringEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            if (RecurringPlanRefundPayment::query()->whereIn('occurrence_id', $entry->occurrences()->select('id'))->exists()) {
                throw ValidationException::withMessages(['entry' => __('recurring_refunds.locked')]);
            }
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

            SharedExpenseLedgerEntry::query()->whereIn('recurring_entry_occurrence_id', $occurrenceIds)->get()->each(function (SharedExpenseLedgerEntry $source): void {
                app(CounterpartySourceGuard::class)->assertChange($source, true);
                app(SharedCounterpartyShadowService::class)->changed($source, true);
            });
            $entry->delete();

            Account::query()
                ->whereIn('id', $accountIds)
                ->get()
                ->each(fn (Account $account) => $this->transactionMutationService->recalculateAccount($account));
        });
    }
}
