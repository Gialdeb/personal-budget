<?php

namespace App\Console\Commands;

use App\Services\Banks\BankMfiImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('banks:import-mfi {path}')]
#[Description('Import banks from an MFI UTF-16 TSV dataset')]
class ImportMfiBanks extends Command
{
    public function __construct(private readonly BankMfiImportService $importService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        try {
            $summary = $this->importService->importFromPath($path);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('MFI bank import completed.');
        $this->table(
            ['Read', 'Imported', 'Updated', 'Skipped', 'Errors'],
            [[
                $summary['read'],
                $summary['imported'],
                $summary['updated'],
                $summary['skipped'],
                $summary['errors'],
            ]]
        );

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
