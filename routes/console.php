<?php

use App\Jobs\Automation\CheckAutomationHealthJob;
use App\Jobs\Automation\RunRecurringPipelineJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RunRecurringPipelineJob)
    ->hourly()
    ->name('recurring-pipeline');

Schedule::job(new CheckAutomationHealthJob)
    ->everyFifteenMinutes()
    ->name('automation-health-check');

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->name('horizon-snapshot');
