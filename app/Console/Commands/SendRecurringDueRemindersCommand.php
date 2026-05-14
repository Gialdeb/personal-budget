<?php

namespace App\Console\Commands;

use App\Services\Reminders\DailyRecurringReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:recurring-due')]
#[Description('Send daily reminders for recurring entries due soon or overdue.')]
class SendRecurringDueRemindersCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DailyRecurringReminderService $recurringReminderService): int
    {
        $summary = $recurringReminderService->run();

        $this->line('Recurring reminders:');
        $this->line('- scanned: '.$summary['scanned']);
        $this->line('- notified: '.$summary['notified']);
        $this->line('- pushed: '.$summary['pushed']);
        $this->line('- skipped: '.$summary['skipped']);
        $this->line('- skipped duplicates: '.$summary['duplicates']);

        return self::SUCCESS;
    }
}
