<?php

namespace App\Console\Commands;

use App\Services\Reminders\DailyCreditDebtReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:credits-debts-due')]
#[Description('Send daily reminders for credits and debts due soon or overdue.')]
class SendCreditsDebtsDueRemindersCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DailyCreditDebtReminderService $creditDebtReminderService): int
    {
        $summary = $creditDebtReminderService->run();

        $this->line('Credits/debts reminders:');
        $this->line('- scanned: '.$summary['scanned']);
        $this->line('- notified: '.$summary['notified']);
        $this->line('- pushed: '.$summary['pushed']);
        $this->line('- skipped: '.$summary['skipped']);
        $this->line('- skipped duplicates: '.$summary['duplicates']);

        return self::SUCCESS;
    }
}
