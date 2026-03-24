<?php

use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Services\Automation\AutomationPipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs a pipeline successfully and records the run', function () {
    $runner = app(AutomationPipelineRunner::class);

    $run = $runner->run(
        automationKey: 'recurring_pipeline',
        pipeline: 'recurring_pipeline',
        triggerType: AutomationTriggerTypeEnum::SCHEDULED,
        jobClass: 'App\\Jobs\\Automation\\RunRecurringPipelineJob',
        context: ['month' => 3, 'year' => 2026],
        callback: function () {
            return [
                'status' => 'success',
                'processed_count' => 10,
                'success_count' => 10,
                'warning_count' => 0,
                'error_count' => 0,
                'result' => [
                    'generated_occurrences' => 4,
                    'created_transactions' => 6,
                ],
            ];
        },
    );

    expect($run->status)->toBe(AutomationRunStatusEnum::SUCCESS)
        ->and($run->processed_count)->toBe(10)
        ->and($run->success_count)->toBe(10)
        ->and($run->result)->toBe([
            'generated_occurrences' => 4,
            'created_transactions' => 6,
        ])
        ->and($run->started_at)->not->toBeNull()
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->duration_ms)->not->toBeNull();
});

it('records a failed pipeline run when callback throws', function () {
    $runner = app(AutomationPipelineRunner::class);

    $run = $runner->run(
        automationKey: 'recurring_pipeline',
        pipeline: 'recurring_pipeline',
        triggerType: AutomationTriggerTypeEnum::SCHEDULED,
        callback: function () {
            throw new RuntimeException('Recurring pipeline failed badly');
        },
    );

    expect($run->status)->toBe(AutomationRunStatusEnum::FAILED)
        ->and($run->error_message)->toBe('Recurring pipeline failed badly')
        ->and($run->exception_class)->toBe(RuntimeException::class)
        ->and($run->started_at)->not->toBeNull()
        ->and($run->finished_at)->not->toBeNull();
});

it('records a warning pipeline run', function () {
    $runner = app(AutomationPipelineRunner::class);

    $run = $runner->run(
        automationKey: 'reports_pipeline',
        pipeline: 'reports_pipeline',
        triggerType: AutomationTriggerTypeEnum::MANUAL,
        callback: function () {
            return [
                'status' => 'warning',
                'processed_count' => 5,
                'success_count' => 4,
                'warning_count' => 1,
                'error_count' => 0,
                'message' => 'One report had no recipients',
                'result' => [
                    'reports_sent' => 4,
                ],
            ];
        },
    );

    expect($run->status)->toBe(AutomationRunStatusEnum::WARNING)
        ->and($run->warning_count)->toBe(1)
        ->and($run->error_message)->toBe('One report had no recipients')
        ->and($run->result)->toBe([
            'reports_sent' => 4,
        ]);
});
