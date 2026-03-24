<?php

namespace App\Services\Imports;

use App\Enums\ImportRowStatusEnum;
use App\Enums\ImportStatusEnum;
use App\Models\Import;
use App\Services\Communication\DomainNotificationService;

class SyncImportStateService
{
    public function __construct(
        protected DomainNotificationService $domainNotificationService,
    ) {}

    public function sync(Import $import): Import
    {
        $import->loadMissing('rows');

        $rows = $import->rows;

        $readyRowsCount = $rows->where('status', ImportRowStatusEnum::READY)->count();
        $reviewRowsCount = $rows->where('status', ImportRowStatusEnum::NEEDS_REVIEW)->count();
        $invalidRowsCount = $rows->whereIn('status', [
            ImportRowStatusEnum::INVALID,
            ImportRowStatusEnum::BLOCKED_YEAR,
        ])->count();
        $duplicateRowsCount = $rows->whereIn('status', [
            ImportRowStatusEnum::DUPLICATE_CANDIDATE,
            ImportRowStatusEnum::ALREADY_IMPORTED,
        ])->count();
        $importedRowsCount = $rows->where('status', ImportRowStatusEnum::IMPORTED)->count();
        $rolledBackRowsCount = $rows->where('status', ImportRowStatusEnum::ROLLED_BACK)->count();
        $rowsCount = $rows->count();
        $previousStatus = $import->status;

        $status = match (true) {
            $rowsCount > 0 && $rolledBackRowsCount === $rowsCount => ImportStatusEnum::ROLLED_BACK,
            $readyRowsCount === 0
                && $reviewRowsCount === 0
                && $invalidRowsCount === 0
                && $duplicateRowsCount === 0
                && $importedRowsCount > 0 => ImportStatusEnum::COMPLETED,
            $reviewRowsCount > 0 || $invalidRowsCount > 0 || $duplicateRowsCount > 0 => ImportStatusEnum::REVIEW_REQUIRED,
            default => ImportStatusEnum::PARSED,
        };

        $import->forceFill([
            'status' => $status,
            'rows_count' => $rowsCount,
            'ready_rows_count' => $readyRowsCount,
            'review_rows_count' => $reviewRowsCount,
            'invalid_rows_count' => $invalidRowsCount,
            'duplicate_rows_count' => $duplicateRowsCount,
            'imported_rows_count' => $importedRowsCount,
            'completed_at' => $status === ImportStatusEnum::COMPLETED ? now() : null,
            'rolled_back_at' => $status === ImportStatusEnum::ROLLED_BACK ? now() : null,
        ])->save();

        $import = $import->fresh(['rows', 'account.bank', 'importFormat.bank']);

        if (
            $previousStatus !== ImportStatusEnum::COMPLETED
            && $status === ImportStatusEnum::COMPLETED
        ) {
            $this->domainNotificationService->sendImportCompleted($import);
        }

        return $import;
    }
}
