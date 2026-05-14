<?php

namespace App\Console\Commands;

use App\Services\Reminders\DailyCreditDebtReminderService;
use App\Services\Reminders\DailyRecurringReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:daily')]
#[Description('Send daily reminders for recurring entries and credits/debts.')]
class SendDailyRemindersCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        DailyRecurringReminderService $recurringReminderService,
        DailyCreditDebtReminderService $creditDebtReminderService,
    ): int {
        $recurring = $recurringReminderService->run();
        $creditsDebts = $creditDebtReminderService->run();

        $this->printSummary('Recurring reminders', $recurring);
        $this->newLine();
        $this->printSummary('Credits/debts reminders', $creditsDebts);

        return self::SUCCESS;
    }

    /**
     * @param  array{scanned: int, skipped: int, notified: int, pushed: int, duplicates: int}  $summary
     */
    protected function printSummary(string $title, array $summary): void
    {
        $this->line($title.':');
        $this->line('- scanned: '.$summary['scanned']);
        $this->line('- notified: '.$summary['notified']);
        $this->line('- pushed: '.$summary['pushed']);
        $this->line('- skipped: '.$summary['skipped']);
        $this->line('- skipped duplicates: '.$summary['duplicates']);
    }
}
