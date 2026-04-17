<?php

namespace App\Console\Commands;

use App\Enums\ImportStatusEnum;
use App\Models\Import;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('imports:prune-old {--file-days=30 : Days to retain uploaded source files for closed imports} {--delete-days=180 : Days to retain failed or rolled back imports before deleting safe records}')]
#[Description('Prune old import source files and safely removable historical imports')]
class PruneOldImportsCommand extends Command
{
    public function handle(): int
    {
        $fileDays = max(1, (int) $this->option('file-days'));
        $deleteDays = max($fileDays, (int) $this->option('delete-days'));
        $fileCutoff = now()->subDays($fileDays);
        $deleteCutoff = now()->subDays($deleteDays);
        $prunedFiles = 0;
        $prunedImports = 0;

        Import::query()
            ->whereIn('status', [
                ImportStatusEnum::COMPLETED->value,
                ImportStatusEnum::FAILED->value,
                ImportStatusEnum::ROLLED_BACK->value,
            ])
            ->whereNotNull('stored_filename')
            ->where('created_at', '<', $fileCutoff)
            ->orderBy('id')
            ->chunkById(100, function ($imports) use (&$prunedFiles): void {
                foreach ($imports as $import) {
                    if (filled($import->stored_filename)) {
                        Storage::disk('local')->delete($import->stored_filename);
                    }

                    $import->forceFill(['stored_filename' => null])->save();
                    $prunedFiles++;
                }
            });

        Import::query()
            ->withCount('transactions')
            ->whereIn('status', [
                ImportStatusEnum::FAILED->value,
                ImportStatusEnum::ROLLED_BACK->value,
            ])
            ->where('created_at', '<', $deleteCutoff)
            ->orderBy('id')
            ->chunkById(100, function ($imports) use (&$prunedImports): void {
                foreach ($imports as $import) {
                    if ((int) $import->transactions_count > 0) {
                        continue;
                    }

                    DB::transaction(function () use ($import): void {
                        $import->rows()->delete();
                        $import->delete();
                    });

                    $prunedImports++;
                }
            });

        $this->info("Pruned {$prunedFiles} import source files.");
        $this->info("Deleted {$prunedImports} old imports.");

        return self::SUCCESS;
    }
}
