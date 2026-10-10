<?php

namespace App\Console\Commands;

use App\Services\Maintenance\TechnicalArchiveService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('maintenance:archive-old-data {--dry-run : Count candidates without changing records} {--batch-size= : Records per atomic batch (1-1000)} {--limit= : Maximum records per table per run}')]
#[Description('Archive allowlisted cold technical records before deleting their online copies')]
class ArchiveOldDataCommand extends Command
{
    public function handle(TechnicalArchiveService $archives): int
    {
        $batch = $this->option('batch-size') ?? config('technical-archive.batch_size');
        $limit = $this->option('limit') ?? config('technical-archive.limit');
        if (filter_var($batch, FILTER_VALIDATE_INT) === false || filter_var($limit, FILTER_VALIDATE_INT) === false) {
            $this->error('Batch size and limit must be integers.');

            return self::FAILURE;
        }
        foreach (TechnicalArchiveService::TABLES as $table) {
            try {
                $stats = $archives->archive($table, (int) config('technical-archive.retention_days.'.$table), (int) $batch, (int) $limit, (bool) $this->option('dry-run'));
                $this->line(json_encode($stats + ['dry_run' => (bool) $this->option('dry-run')], JSON_THROW_ON_ERROR));
            } catch (Throwable $exception) {
                $this->error('Archive failed for '.$table.' ('.$exception::class.'). The failed batch was rolled back; see maintenance logs.');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
