<?php

namespace App\Console\Commands;

use App\Services\Reminders\DailyExpenseReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:daily-expenses')]
#[Description('Send the daily expense push reminder at 21:00 in each user timezone.')]
class SendDailyExpenseRemindersCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DailyExpenseReminderService $service): int
    {
        $this->line('Daily expense reminders sent: '.$service->run());

        return self::SUCCESS;
    }
}
