<?php

use App\DTO\Automation\AutomationAlertData;
use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Jobs\Automation\CheckAutomationHealthJob;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationAlertService;
use App\Services\Automation\Channels\LogAutomationAlertChannel;
use App\Services\Automation\Channels\TelegramAutomationAlertChannel;
use App\Services\Communication\DomainNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('does not alert immediately when a pipeline has never run during the bootstrap grace window', function () {
    config()->set('automation.pipelines', [
        'recurring_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 90,
        ],
    ]);
    config()->set('automation.health.missing_run_grace_multiplier', 1);
    config()->set('automation.health.skip_missing_run_alert_in_local', false);

    $alertService = Mockery::mock(AutomationAlertService::class);
    $alertService->shouldNotReceive('send');

    $this->app->instance(AutomationAlertService::class, $alertService);

    $job = app(CheckAutomationHealthJob::class);
    $job->handle($alertService);
});

it('records the first missing run observation to start the bootstrap grace window', function () {
    config()->set('automation.pipelines', [
        'recurring_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 90,
        ],
    ]);
    config()->set('automation.health.missing_run_grace_multiplier', 1);
    config()->set('automation.health.skip_missing_run_alert_in_local', false);

    Cache::shouldReceive('get')
        ->once()
        ->with('automation:health:missing-run-first-observed:recurring_pipeline')
        ->andReturn(null);

    Cache::shouldReceive('forever')
        ->once()
        ->with(
            'automation:health:missing-run-first-observed:recurring_pipeline',
            Mockery::type('string'),
        );

    $job = app(CheckAutomationHealthJob::class);
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('shouldDeferMissingRunAlert');

    expect($method->invoke($job, 'recurring_pipeline', 90))->toBeTrue();
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

    AutomationRun::query()->create([
        'automation_key' => 'recurring_pipeline',
        'pipeline' => 'recurring_pipeline',
        'status' => AutomationRunStatusEnum::SUCCESS,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
    ]);

    $alertService = Mockery::mock(AutomationAlertService::class);
    $alertService->shouldReceive('send')
        ->once()
        ->withArgs(function (AutomationAlertData $alert) {
            return $alert->type === 'stale_run'
                && $alert->pipeline === 'recurring_pipeline'
                && ($alert->context['environment'] ?? null) === app()->environment();
        });

    $this->app->instance(AutomationAlertService::class, $alertService);

    $this->travel(200)->minutes();

    $job = app(CheckAutomationHealthJob::class);
    $job->handle($alertService);
});

it('does not alert stale when a queued run finished recently even if it was created earlier', function () {
    config()->set('automation.pipelines', [
        'horizon_snapshot' => [
            'enabled' => true,
            'critical' => false,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 15,
        ],
    ]);
    config()->set('automation.health.stale_grace_minutes', 15);

    $run = AutomationRun::query()->create([
        'automation_key' => 'horizon_snapshot',
        'pipeline' => 'horizon_snapshot',
        'status' => AutomationRunStatusEnum::SUCCESS,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
        'finished_at' => now()->subMinutes(5),
    ]);

    $run->forceFill([
        'created_at' => now()->subMinutes(40),
        'updated_at' => now()->subMinutes(5),
    ])->saveQuietly();

    $alertService = Mockery::mock(AutomationAlertService::class);
    $alertService->shouldNotReceive('send');

    app(CheckAutomationHealthJob::class)->handle($alertService);
});

it('does not alert stale while the latest run is inside the grace window', function () {
    config()->set('automation.pipelines', [
        'recurring_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 90,
        ],
    ]);
    config()->set('automation.health.stale_grace_minutes', 15);

    AutomationRun::query()->create([
        'automation_key' => 'recurring_pipeline',
        'pipeline' => 'recurring_pipeline',
        'status' => AutomationRunStatusEnum::SUCCESS,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
        'finished_at' => now()->subMinutes(100),
    ]);

    $alertService = Mockery::mock(AutomationAlertService::class);
    $alertService->shouldNotReceive('send');

    app(CheckAutomationHealthJob::class)->handle($alertService);
});

it('alerts stale when the latest activity is older than the expected interval plus grace', function () {
    config()->set('automation.pipelines', [
        'recurring_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 90,
        ],
    ]);
    config()->set('automation.health.stale_grace_minutes', 15);

    AutomationRun::query()->create([
        'automation_key' => 'recurring_pipeline',
        'pipeline' => 'recurring_pipeline',
        'status' => AutomationRunStatusEnum::SUCCESS,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
        'finished_at' => now()->subMinutes(106),
    ]);

    $alertService = Mockery::mock(AutomationAlertService::class);
    $alertService->shouldReceive('send')
        ->once()
        ->withArgs(function (AutomationAlertData $alert) {
            return $alert->type === 'stale_run'
                && $alert->pipeline === 'recurring_pipeline'
                && ($alert->context['effective_threshold_minutes'] ?? null) === 105
                && ($alert->context['stale_grace_minutes'] ?? null) === 15;
        });

    app(CheckAutomationHealthJob::class)->handle($alertService);
});

it('compares stale timestamps consistently across application timezones', function () {
    config()->set('automation.pipelines', [
        'horizon_snapshot' => [
            'enabled' => true,
            'critical' => false,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 15,
        ],
    ]);
    config()->set('automation.health.stale_grace_minutes', 15);

    $this->travelTo(Carbon::parse('2026-04-24 17:20:00', 'Europe/Rome'));

    $run = AutomationRun::query()->create([
        'automation_key' => 'horizon_snapshot',
        'pipeline' => 'horizon_snapshot',
        'status' => AutomationRunStatusEnum::SUCCESS,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
        'finished_at' => Carbon::parse('2026-04-24 15:10:00', 'UTC'),
    ]);

    $run->forceFill([
        'created_at' => Carbon::parse('2026-04-24 14:35:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-04-24 15:10:00', 'UTC'),
    ])->saveQuietly();

    $alertService = Mockery::mock(AutomationAlertService::class);
    $alertService->shouldNotReceive('send');

    app(CheckAutomationHealthJob::class)->handle($alertService);
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
                && $alert->pipeline === 'recurring_pipeline'
                && ($alert->context['environment'] ?? null) === app()->environment();
        });

    $this->app->instance(AutomationAlertService::class, $alertService);

    $job = app(CheckAutomationHealthJob::class);
    $job->handle($alertService);
});

it('does not send automation failed notifications for missing run alerts in local environment', function () {
    config()->set('automation.alerts.enabled', true);

    $logChannel = Mockery::mock(LogAutomationAlertChannel::class);
    $logChannel->shouldReceive('send')->once();

    $telegramChannel = Mockery::mock(TelegramAutomationAlertChannel::class);
    $telegramChannel->shouldNotReceive('send');

    $domainNotifications = Mockery::mock(DomainNotificationService::class);
    $domainNotifications->shouldNotReceive('sendAutomationFailed');

    $originalEnvironment = $this->app->environment();
    $this->app->instance('env', 'local');

    try {
        $service = new AutomationAlertService(
            $logChannel,
            $telegramChannel,
            $domainNotifications,
        );

        $service->send(new AutomationAlertData(
            type: 'missing_run',
            pipeline: 'recurring_pipeline',
            title: 'Automation pipeline has never run',
            message: 'No execution has been recorded yet for this pipeline.',
        ));
    } finally {
        $this->app->instance('env', $originalEnvironment);
    }
});
