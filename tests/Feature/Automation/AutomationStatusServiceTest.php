<?php

use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns never ran when no run exists', function () {
    config()->set('automation.pipelines', [
        'recurring_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 90,
        ],
    ]);

    $statuses = app(AutomationStatusService::class)->pipelineStatuses();

    expect($statuses)->toHaveCount(1)
        ->and($statuses[0]['key'])->toBe('recurring_pipeline')
        ->and($statuses[0]['state'])->toBe('never_ran');
});

it('returns healthy when latest run is recent and successful', function () {
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
        'finished_at' => now()->subMinutes(5),
    ]);

    $statuses = app(AutomationStatusService::class)->pipelineStatuses();

    expect($statuses[0]['state'])->toBe('healthy');
});

it('returns stale when latest run is too old', function () {
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

    $statuses = app(AutomationStatusService::class)->pipelineStatuses();

    expect($statuses[0]['state'])->toBe('stale');
});

it('returns failed when latest run failed', function () {
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
    ]);

    $statuses = app(AutomationStatusService::class)->pipelineStatuses();

    expect($statuses[0]['state'])->toBe('failed');
});
