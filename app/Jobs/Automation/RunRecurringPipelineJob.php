<?php

namespace App\Jobs\Automation;

use App\Enums\AutomationTriggerTypeEnum;
use App\Services\Automation\AutomationPipelineRunner;
use App\Services\Transactions\RecurringEntryLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunRecurringPipelineJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public AutomationTriggerTypeEnum $triggerType = AutomationTriggerTypeEnum::SCHEDULED,
    ) {}

    public function handle(
        AutomationPipelineRunner $runner,
        RecurringEntryLifecycleService $lifecycleService,
    ): void {
        $runner->run(
            automationKey: 'recurring_pipeline',
            pipeline: 'recurring_pipeline',
            triggerType: $this->triggerType,
            callback: function () use ($lifecycleService): array {
                $result = $lifecycleService->runAutomationPipeline();

                return [
                    'status' => 'success',
                    'processed_count' => (int) ($result['processed_count'] ?? 0),
                    'success_count' => (int) ($result['success_count'] ?? 0),
                    'warning_count' => (int) ($result['warning_count'] ?? 0),
                    'error_count' => (int) ($result['error_count'] ?? 0),
                    'result' => $result,
                ];
            },
            jobClass: self::class,
            context: [],
            attempt: 1,
        );
    }
}
