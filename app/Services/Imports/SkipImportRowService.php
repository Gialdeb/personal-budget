<?php

namespace App\Services\Imports;

use App\Enums\ImportRowStatusEnum;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Facades\DB;

class SkipImportRowService
{
    public function __construct(
        protected SyncImportStateService $syncImportStateService,
    ) {}

    public function execute(Import $import, ImportRow $row): ImportRow
    {
        return DB::transaction(function () use ($import, $row) {
            if (in_array($row->status, [
                ImportRowStatusEnum::IMPORTED->value,
                ImportRowStatusEnum::ROLLED_BACK->value,
            ], true)) {
                return $row;
            }

            $warnings = $row->warnings ?? [];
            $warnings[] = __('imports.validation.skipped_manually');

            $row->update([
                'status' => ImportRowStatusEnum::SKIPPED->value,
                'warnings' => array_values(array_unique($warnings)),
            ]);

            $this->syncImportStateService->sync($import->fresh());

            return $row->fresh();
        });
    }
}
