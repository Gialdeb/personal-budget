<?php

namespace App\Services\Imports;

use App\Enums\ImportStatusEnum;
use App\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteImportService
{
    public function execute(Import $import): void
    {
        $import->loadMissing('transactions');

        if ($import->status !== ImportStatusEnum::ROLLED_BACK) {
            throw ValidationException::withMessages([
                'import' => __('ui.only_rolled_back_imports_can_be_deleted_42debe74'),
            ]);
        }

        if ($import->transactions->isNotEmpty()) {
            throw ValidationException::withMessages([
                'import' => __('ui.this_import_still_affects_transactions_and_cannot_be_deleted_9728bff8'),
            ]);
        }

        DB::transaction(function () use ($import): void {
            $import->rows()->delete();
            $import->forceDelete();
        });

        if (filled($import->stored_filename)) {
            Storage::disk('local')->delete($import->stored_filename);
        }
    }
}
