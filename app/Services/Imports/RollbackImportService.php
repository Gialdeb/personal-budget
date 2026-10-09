<?php

namespace App\Services\Imports;

use App\Enums\ImportRowStatusEnum;
use App\Models\Account;
use App\Models\Import;
use App\Models\Transaction;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RollbackImportService
{
    public function __construct(
        protected TransactionMutationService $transactionMutationService,
        protected SyncImportStateService $syncImportStateService
    ) {}

    public function execute(Import $import): Import
    {
        $import->loadMissing(['user', 'rows']);

        $transactions = Transaction::query()
            ->where('import_id', $import->id)
            ->orderByDesc('id')
            ->get();

        if ($transactions->isEmpty()) {
            throw ValidationException::withMessages([
                'import' => __('ui.this_import_has_no_transactions_to_roll_back_b244017f'),
            ]);
        }

        DB::transaction(function () use ($import, $transactions): void {
            $affectedAccounts = Account::query()
                ->whereIn('id', $transactions->pluck('account_id')->unique()->all())
                ->get();

            foreach ($transactions as $transaction) {
                $transaction->forceDelete();
            }

            foreach ($affectedAccounts as $account) {
                $this->transactionMutationService->recalculateAccount($account);
            }

            $rolledBackAt = now();

            $import->rows()
                ->where('status', ImportRowStatusEnum::IMPORTED)
                ->get()
                ->each(function ($row) use ($rolledBackAt): void {
                    $row->forceFill([
                        'status' => ImportRowStatusEnum::ROLLED_BACK,
                        'transaction_id' => null,
                        'rolled_back_at' => $rolledBackAt,
                    ])->save();
                });
        });

        return $this->syncImportStateService->sync($import->fresh(['rows']));
    }
}
