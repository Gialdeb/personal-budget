<?php

use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationRunRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the automation_runs table with expected columns', function () {
    expect(Schema::hasTable('automation_runs'))->toBeTrue();

    foreach ([
        'id',
        'uuid',
        'automation_key',
        'pipeline',
        'job_class',
        'status',
        'trigger_type',
        'started_at',
        'finished_at',
        'duration_ms',
        'processed_count',
        'success_count',
        'warning_count',
        'error_count',
        'batch_id',
        'context',
        'result',
        'error_message',
        'exception_class',
        'host',
        'attempt',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('automation_runs', $column))->toBeTrue();
    }
});

it('casts enums and arrays correctly on AutomationRun model', function () {
    $run = AutomationRun::query()->create([
        'automation_key' => 'recurring_pipeline',
        'pipeline' => 'recurring_pipeline',
        'status' => AutomationRunStatusEnum::PENDING,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
        'context' => ['month' => 3, 'year' => 2026],
        'result' => ['processed' => 0],
    ]);

    expect($run->status)->toBe(AutomationRunStatusEnum::PENDING)
        ->and($run->trigger_type)->toBe(AutomationTriggerTypeEnum::SCHEDULED)
        ->and($run->context)->toBe(['month' => 3, 'year' => 2026])
        ->and($run->result)->toBe(['processed' => 0]);
});

it('starts an automation run', function () {
    $recorder = app(AutomationRunRecorder::class);

    $run = $recorder->start(
        automationKey: 'recurring_pipeline',
        pipeline: 'recurring_pipeline',
        triggerType: AutomationTriggerTypeEnum::SCHEDULED,
        jobClass: 'App\\Jobs\\Automation\\RunRecurringPipelineJob',
        context: ['month' => 3, 'year' => 2026],
        attempt: 1,
        batchId: null,
    );

    expect($run->automation_key)->toBe('recurring_pipeline')
        ->and($run->pipeline)->toBe('recurring_pipeline')
        ->and($run->job_class)->toBe('App\\Jobs\\Automation\\RunRecurringPipelineJob')
        ->and($run->status)->toBe(AutomationRunStatusEnum::PENDING)
        ->and($run->trigger_type)->toBe(AutomationTriggerTypeEnum::SCHEDULED)
        ->and($run->context)->toBe(['month' => 3, 'year' => 2026])
        ->and($run->attempt)->toBe(1);
});

it('marks an automation run as running', function () {
    $recorder = app(AutomationRunRecorder::class);

    $run = $recorder->start(
        automationKey: 'recurring_pipeline',
        pipeline: 'recurring_pipeline',
        triggerType: AutomationTriggerTypeEnum::SCHEDULED,
    );

    $run = $recorder->markRunning($run);

    expect($run->status)->toBe(AutomationRunStatusEnum::RUNNING)
        ->and($run->started_at)->not->toBeNull();
});

it('marks an automation run as success', function () {
    $recorder = app(AutomationRunRecorder::class);

    $run = $recorder->start(
        automationKey: 'recurring_pipeline',
        pipeline: 'recurring_pipeline',
        triggerType: AutomationTriggerTypeEnum::SCHEDULED,
    );

    $run = $recorder->markRunning($run);
    usleep(1000);

    $run = $recorder->markSuccess(
        $run,
        result: ['generated' => 12],
        processedCount: 12,
        successCount: 12,
        warningCount: 0,
        errorCount: 0,
    );

    expect($run->status)->toBe(AutomationRunStatusEnum::SUCCESS)
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->duration_ms)->not->toBeNull()
        ->and($run->processed_count)->toBe(12)
        ->and($run->success_count)->toBe(12)
        ->and($run->result)->toBe(['generated' => 12]);
});

it('marks an automation run as failed', function () {
    $recorder = app(AutomationRunRecorder::class);

    $run = $recorder->start(
        automationKey: 'recurring_pipeline',
        pipeline: 'recurring_pipeline',
        triggerType: AutomationTriggerTypeEnum::SCHEDULED,
    );

    $run = $recorder->markRunning($run);

    $exception = new RuntimeException('Pipeline exploded');

    $run = $recorder->markFailed(
        $run,
        exception: $exception,
        result: ['processed' => 3],
        processedCount: 3,
        successCount: 2,
        warningCount: 0,
        errorCount: 1,
    );

    expect($run->status)->toBe(AutomationRunStatusEnum::FAILED)
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->duration_ms)->not->toBeNull()
        ->and($run->processed_count)->toBe(3)
        ->and($run->success_count)->toBe(2)
        ->and($run->error_count)->toBe(1)
        ->and($run->error_message)->toBe('Pipeline exploded')
        ->and($run->exception_class)->toBe(RuntimeException::class)
        ->and($run->result)->toBe(['processed' => 3]);
});
