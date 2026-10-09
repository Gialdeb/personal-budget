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
                'row' => __('ui.the_selected_row_does_not_belong_to_this_import_17af4e86'),
            ]);
        }

        if ($row->status !== ImportRowStatusEnum::DUPLICATE_CANDIDATE) {
            throw ValidationException::withMessages([
                'row' => __('ui.only_candidate_duplicate_rows_can_be_manually_approved_2c4a8918'),
            ]);
        }

        return DB::transaction(function () use ($import, $row) {
            $warnings = $row->warnings ?? [];
            $warnings[] = __('ui.candidate_duplicate_manually_approved_by_the_user_e23443b7');

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
