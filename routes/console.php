<?php

use App\Jobs\Automation\CheckAutomationHealthJob;
use App\Jobs\Automation\RunBackupRetentionCleanupJob;
use App\Jobs\Automation\RunCreditCardAutopayJob;
use App\Jobs\Automation\RunFullBackupJob;
use App\Jobs\Automation\RunHorizonSnapshotJob;
use App\Jobs\Automation\RunImportsPruneOldJob;
use App\Jobs\Automation\RunRecurringMonthlySummaryJob;
use App\Jobs\Automation\RunRecurringPipelineJob;
use App\Jobs\Automation\RunRecurringWeeklySummaryJob;
use App\Jobs\Automation\RunUserBackupJob;
use App\Services\Recurring\AutomaticPaymentVerificationService;
use App\Services\Support\SupportTicketInactivityService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('maintenance:archive-old-data')
    ->dailyAt((string) config('technical-archive.daily_at', '05:15'))
    ->withoutOverlapping()
    ->onOneServer()
    ->name('technical-archive-maintenance');

Schedule::call(fn () => app(AutomaticPaymentVerificationService::class)->confirmExpired())
    ->everyMinute()
    ->name('automatic-payment-confirmation')
    ->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RunRecurringPipelineJob)
    ->hourly()
    ->name('recurring-pipeline');

Schedule::job(new RunCreditCardAutopayJob)
    ->daily()
    ->withoutOverlapping()
    ->name('credit-card-autopay');

Schedule::job(new RunRecurringWeeklySummaryJob)
    ->mondays()
    ->at('07:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('recurring-weekly-summary');

Schedule::job(new RunRecurringMonthlySummaryJob)
    ->monthlyOn(1, '07:10')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('recurring-monthly-summary');

Schedule::command('reminders:daily')
    ->dailyAt((string) config('reminders.daily_run_time', '08:00'))
    ->withoutOverlapping()
    ->onOneServer()
    ->name('daily-reminders');

Schedule::command('reminders:daily-expenses')
    ->dailyAt('21:00')
    ->timezone('Europe/Rome')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('daily-expense-reminders');

Schedule::job(new RunFullBackupJob)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->name('full-backup');

Schedule::job(new RunUserBackupJob)
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->name('user-backup');

Schedule::job(new RunBackupRetentionCleanupJob)
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->name('backup-retention-cleanup');

Schedule::job(new CheckAutomationHealthJob)
    ->everyFifteenMinutes()
    ->name('automation-health-check');

Schedule::job(new RunHorizonSnapshotJob)
    ->everyFiveMinutes()
    ->name('horizon-snapshot');

Schedule::command('currencies:sync-rates')
    ->dailyAt((string) config('currencies.sync.daily_at', '18:00'))
    ->withoutOverlapping()
    ->name('currencies-sync-rates');

Schedule::job(new RunImportsPruneOldJob)
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->name('imports-prune-old');

Schedule::call(fn () => app(SupportTicketInactivityService::class)->process())
    ->hourly()->name('support-ticket-inactivity')->withoutOverlapping()->onOneServer();
