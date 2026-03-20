<?php

namespace App\Services\Imports;

use App\Enums\ImportRowStatusEnum;
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
                'import' => 'Questo import non ha transazioni da annullare.',
            ]);
        }

        DB::transaction(function () use ($import, $transactions): void {
            foreach ($transactions as $transaction) {
                $this->transactionMutationService->destroy($import->user, $transaction);
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
