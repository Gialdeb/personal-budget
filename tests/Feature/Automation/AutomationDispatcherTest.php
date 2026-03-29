<?php

use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Jobs\Automation\RunCreditCardAutopayJob;
use App\Jobs\Automation\RunRecurringPipelineJob;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('dispatches a registered pipeline manually', function () {
    Bus::fake();

    $dispatcher = app(AutomationDispatcher::class);

    $jobClass = $dispatcher->dispatchPipeline('recurring_pipeline', AutomationTriggerTypeEnum::MANUAL);

    expect($jobClass)->toBe(RunRecurringPipelineJob::class);

    Bus::assertDispatched(RunRecurringPipelineJob::class, function ($job) {
        return $job->triggerType === AutomationTriggerTypeEnum::MANUAL;
    });
});

it('dispatches the credit card autopay pipeline manually with a reference date', function () {
    Bus::fake();

    $dispatcher = app(AutomationDispatcher::class);

    $jobClass = $dispatcher->dispatchPipeline('credit_card_autopay', AutomationTriggerTypeEnum::MANUAL, [
        'reference_date' => '2026-02-16',
    ]);

    expect($jobClass)->toBe(RunCreditCardAutopayJob::class);

    Bus::assertDispatched(RunCreditCardAutopayJob::class, function ($job) {
        return $job->triggerType === AutomationTriggerTypeEnum::MANUAL
            && $job->referenceDate === '2026-02-16';
    });
});

it('throws for unsupported pipeline', function () {
    Bus::fake();

    $dispatcher = app(AutomationDispatcher::class);

    expect(fn () => $dispatcher->dispatchPipeline('nope_pipeline', AutomationTriggerTypeEnum::MANUAL))
        ->toThrow(InvalidArgumentException::class);
});

it('dispatches retry for a retryable failed run', function () {
    Bus::fake();

    $run = AutomationRun::query()->create([
        'automation_key' => 'recurring_pipeline',
        'pipeline' => 'recurring_pipeline',
        'status' => AutomationRunStatusEnum::FAILED,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
    ]);

    $dispatcher = app(AutomationDispatcher::class);

    $jobClass = $dispatcher->retryRun($run);

    expect($jobClass)->toBe(RunRecurringPipelineJob::class);

    Bus::assertDispatched(RunRecurringPipelineJob::class, function ($job) {
        return $job->triggerType === AutomationTriggerTypeEnum::RETRY;
    });
});

it('does not retry a non retryable run', function () {
    Bus::fake();

    $run = AutomationRun::query()->create([
        'automation_key' => 'recurring_pipeline',
        'pipeline' => 'recurring_pipeline',
        'status' => AutomationRunStatusEnum::SUCCESS,
        'trigger_type' => AutomationTriggerTypeEnum::SCHEDULED,
    ]);

    $dispatcher = app(AutomationDispatcher::class);

    expect(fn () => $dispatcher->retryRun($run))
        ->toThrow(InvalidArgumentException::class);
});
