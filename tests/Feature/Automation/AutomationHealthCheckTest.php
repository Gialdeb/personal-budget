<?php

use App\DTO\Automation\AutomationAlertData;
use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Jobs\Automation\CheckAutomationHealthJob;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('alerts when a pipeline has never run', function () {
    config()->set('automation.pipelines', [
        'recurring_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 90,
        ],
    ]);

    $alertService = Mockery::mock(AutomationAlertService::class);
    $alertService->shouldReceive('send')
        ->once()
        ->withArgs(function (AutomationAlertData $alert) {
            return $alert->type === 'missing_run'
                && $alert->pipeline === 'recurring_pipeline';
        });

    $this->app->instance(AutomationAlertService::class, $alertService);

    $job = app(CheckAutomationHealthJob::class);
    $job->handle($alertService);
});

it('alerts when latest run is stale', function () {
    config()->set('automation.pipelines', [
        'recurring_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 90,
        ],
    ]);

    $run = AutomationRun::query()->create([
        'automation_key' => 'recurring_pipeline',
        'pipeline' => 'recurring_pipeline',
        'status' => AutomationRunStatusEnum::SUCCESS,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
    ]);

    $run->forceFill([
        'created_at' => now()->subMinutes(200),
        'updated_at' => now()->subMinutes(200),
    ])->saveQuietly();

    $alertService = Mockery::mock(AutomationAlertService::class);
    $alertService->shouldReceive('send')
        ->once()
        ->withArgs(function (AutomationAlertData $alert) {
            return $alert->type === 'stale_run'
                && $alert->pipeline === 'recurring_pipeline';
        });

    $this->app->instance(AutomationAlertService::class, $alertService);

    $job = app(CheckAutomationHealthJob::class);
    $job->handle($alertService);
});

it('alerts when latest run failed', function () {
    config()->set('automation.pipelines', [
        'recurring_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 90,
        ],
    ]);

    AutomationRun::query()->create([
        'automation_key' => 'recurring_pipeline',
        'pipeline' => 'recurring_pipeline',
        'status' => AutomationRunStatusEnum::FAILED,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
        'error_message' => 'Boom',
        'exception_class' => RuntimeException::class,
        'finished_at' => now()->subMinutes(5),
    ]);

    $alertService = Mockery::mock(AutomationAlertService::class);
    $alertService->shouldReceive('send')
        ->once()
        ->withArgs(function (AutomationAlertData $alert) {
            return $alert->type === 'failed_run'
                && $alert->pipeline === 'recurring_pipeline';
        });

    $this->app->instance(AutomationAlertService::class, $alertService);

    $job = app(CheckAutomationHealthJob::class);
    $job->handle($alertService);
});
