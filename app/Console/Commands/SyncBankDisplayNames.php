<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Services\Banks\BankDisplayNameFormatter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('banks:sync-display-names {--dry-run : Preview changes without saving them}')]
#[Description('Populate or refresh catalog bank display names from official bank names')]
class SyncBankDisplayNames extends Command
{
    public function __construct(private readonly BankDisplayNameFormatter $displayNameFormatter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $unchanged = 0;
        $rows = [];

        Bank::query()
            ->orderBy('id')
            ->get(['id', 'name', 'display_name'])
            ->each(function (Bank $bank) use ($dryRun, &$updated, &$unchanged, &$rows): void {
                $displayName = $this->displayNameFormatter->format($bank->name);

                if ($bank->display_name === $displayName) {
                    $unchanged++;

                    return;
                }

                if (! $dryRun) {
                    $bank->forceFill([
                        'display_name' => $displayName,
                    ])->save();
                }

                $updated++;
                $rows[] = [
                    $bank->id,
                    $bank->name,
                    $displayName,
                ];
            });

        if ($rows !== []) {
            $this->table(['ID', 'Official name', 'Display name'], $rows);
        }

        $this->newLine();
        $this->info($dryRun ? 'Display name preview completed.' : 'Display names synchronized.');
        $this->line("Updated: {$updated}");
        $this->line("Unchanged: {$unchanged}");

        return self::SUCCESS;
    }
}
