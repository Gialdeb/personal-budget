<?php

namespace App\Services\Recurring;

use App\Models\Account;
use App\Models\RecurringEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transactions\TransactionExchangeSnapshotService;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecalculateInstallmentPlanAmountService
{
    public function __construct(
        protected InstallmentAmountAllocatorService $allocator,
        protected TransactionExchangeSnapshotService $transactionExchangeSnapshotService,
        protected TransactionMutationService $transactionMutationService,
    ) {}

    public function recalculate(RecurringEntry $entry, User $actor, float $totalAmount): void
    {
        $amounts = $this->allocator->allocate($totalAmount, (int) $entry->installments_count);
        $this->synchronizeAmounts($entry, $actor, $amounts, 'sequence_number');
        $entry->forceFill(['total_amount' => $totalAmount])->save();
    }

    /**
     * @param  array<string, float>  $amounts
     */
    public function updateDistribution(RecurringEntry $entry, User $actor, array $amounts): void
    {
        DB::transaction(function () use ($entry, $actor, $amounts): void {
            $lockedEntry = RecurringEntry::query()->lockForUpdate()->findOrFail($entry->getKey());

            if ($lockedEntry->entry_type?->value !== 'installment') {
                throw ValidationException::withMessages(['installments' => 'La distribuzione è disponibile solo per i piani rateali.']);
            }

            $occurrences = $lockedEntry->occurrences()->lockForUpdate()->orderBy('sequence_number')->get();
            $expectedUuids = $occurrences->pluck('uuid')->sort()->values()->all();
            $submittedUuids = collect(array_keys($amounts))->sort()->values()->all();

            if ($expectedUuids !== $submittedUuids) {
                throw ValidationException::withMessages(['installments' => 'La distribuzione deve includere tutte e sole le rate del piano.']);
            }

            $totalCents = (int) round((float) $lockedEntry->total_amount * 100);
            $allocatedCents = collect($amounts)->sum(fn (float $amount): int => (int) round($amount * 100));

            if ($allocatedCents !== $totalCents) {
                throw ValidationException::withMessages(['installments' => 'La somma degli importi delle rate deve coincidere con il totale del piano.']);
            }

            $this->synchronizeAmounts($lockedEntry, $actor, $amounts, 'uuid');
        });
    }

    /**
     * @param  array<int|string, string|float>  $amounts
     */
    protected function synchronizeAmounts(RecurringEntry $entry, User $actor, array $amounts, string $key): void
    {
        $occurrences = $entry->occurrences()
            ->lockForUpdate()
            ->orderBy('sequence_number')
            ->orderBy('id')
            ->get();
        $accounts = collect();
        $transactions = collect();

        foreach ($occurrences as $occurrence) {
            $amount = $key === 'sequence_number'
                ? $amounts[$occurrence->sequence_number - 1] ?? null
                : $amounts[$occurrence->uuid] ?? null;

            if ($amount === null || (float) $amount <= 0) {
                throw ValidationException::withMessages(['installments' => 'Ogni rata deve avere un importo maggiore di zero.']);
            }

            $occurrence->forceFill(['expected_amount' => $amount])->save();

            if ($occurrence->converted_transaction_id === null) {
                continue;
            }

            $transaction = Transaction::query()
                ->lockForUpdate()
                ->find($occurrence->converted_transaction_id);

            if (! $transaction instanceof Transaction
                || $transaction->recurring_entry_occurrence_id !== $occurrence->id) {
                continue;
            }

            $account = $transaction->account()->with('user:id,base_currency_code')->firstOrFail();
            $snapshot = $this->transactionExchangeSnapshotService->buildForAccount(
                $account,
                (float) $amount,
                $transaction->transaction_date->toDateString(),
            );

            $transaction->forceFill([
                'amount' => $amount,
                ...$snapshot,
                'updated_by_user_id' => $actor->id,
            ])->save();

            $accounts->push($account);
            $transactions->push($transaction);
        }

        $accounts->unique('id')->each(
            fn (Account $account) => $this->transactionMutationService->recalculateAccount($account),
        );
        $this->transactionMutationService->reconcileProcessedCreditCardCyclesForTransactions(
            $transactions->all(),
        );
    }
}
