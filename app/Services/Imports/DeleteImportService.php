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
                'import' => 'Puoi eliminare solo import già annullati.',
            ]);
        }

        if ($import->transactions->isNotEmpty()) {
            throw ValidationException::withMessages([
                'import' => 'Questo import ha ancora effetti sulle transazioni e non può essere eliminato.',
            ]);
        }

        DB::transaction(function () use ($import): void {
            $import->rows()->delete();
            $import->delete();
        });

        if (filled($import->stored_filename)) {
            Storage::disk('local')->delete($import->stored_filename);
        }
    }
}
