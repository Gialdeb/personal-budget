<?php

namespace App\Services\Imports;

use App\Enums\ImportRowStatusEnum;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveDuplicateCandidateRowService
{
    public function __construct(
        protected SyncImportStateService $syncImportStateService,
    ) {}

    public function execute(Import $import, ImportRow $row): ImportRow
    {
        if ((int) $row->import_id !== (int) $import->id) {
            throw ValidationException::withMessages([
                'row' => 'La riga selezionata non appartiene a questo import.',
            ]);
        }

        if ($row->status !== ImportRowStatusEnum::DUPLICATE_CANDIDATE) {
            throw ValidationException::withMessages([
                'row' => 'Solo le righe duplicate candidate possono essere approvate manualmente.',
            ]);
        }

        return DB::transaction(function () use ($import, $row) {
            $warnings = $row->warnings ?? [];
            $warnings[] = 'Duplicato candidato approvato manualmente dall’utente.';

            $row->forceFill([
                'status' => ImportRowStatusEnum::READY,
                'errors' => [],
                'warnings' => array_values(array_unique($warnings)),
            ])->save();

            $this->syncImportStateService->sync($import->fresh());

            return $row->fresh();
        });
    }
}
