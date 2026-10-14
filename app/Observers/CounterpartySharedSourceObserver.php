<?php

namespace App\Observers;

use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\Transaction;
use App\Services\Counterparties\SharedCounterpartyShadowService;

/** Synchronous shadow writes share the legacy transaction and isolate failures with savepoints. */
class CounterpartySharedSourceObserver
{
    public function __construct(private readonly SharedCounterpartyShadowService $shadow) {}

    public function saved(Transaction|SharedExpenseLedgerEntry|SharedExpenseSettlement $source): void
    {
        if ($source instanceof Transaction && ! $source->wasRecentlyCreated && ! $source->wasChanged(['amount', 'status', 'currency', 'account_id', 'transaction_date', 'shared_expense_counterparty_id', 'shared_expense_paid_by', 'shared_expense_my_share_cents', 'shared_expense_counterparty_share_cents', 'refunded_transaction_id'])) {
            return;
        }
        $this->shadow->changed($source);
    }

    public function deleted(Transaction|SharedExpenseLedgerEntry|SharedExpenseSettlement $source): void
    {
        $this->shadow->changed($source, true);
    }

    public function restored(Transaction $source): void
    {
        $this->shadow->changed($source);
    }
}
