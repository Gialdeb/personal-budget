<?php

namespace App\Console\Commands;

use App\Services\Maintenance\TechnicalArchiveService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('maintenance:export-archive {source_table : Allowlisted source table} {source_key : Original record ID}')]
#[Description('Export a verified archived technical record as JSON without restoring or replaying it')]
class ExportTechnicalArchiveCommand extends Command
{
    public function handle(TechnicalArchiveService $archives): int
    {
        try {
            $record = $archives->retrieve((string) $this->argument('source_table'), (string) $this->argument('source_key'));
            if ($record === null) {
                $this->error('Archived record not found.');

                return self::FAILURE;
            }
            $this->line(json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
