<?php

namespace App\Jobs\Automation;

use App\Enums\AutomationTriggerTypeEnum;
use App\Services\Automation\AutomationPipelineRunner;
use App\Services\Transactions\RecurringEntryLifecycleService;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RunRecurringPipelineJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public AutomationTriggerTypeEnum $triggerType = AutomationTriggerTypeEnum::SCHEDULED,
        public ?string $referenceDate = null,
    ) {
        $this->onConnection('recurring');
        $this->onQueue('recurring');
    }

    /** @return array<Closure> */
    public function middleware(): array
    {
        return [function (self $job, Closure $next): void {
            Cache::store('redis')->lock('recurring-pipeline', 180)->get(
                fn () => $next($job),
            );
        }];
    }

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
            context: array_filter([
                'reference_date' => $this->referenceDate,
            ], fn ($value) => $value !== null && $value !== ''),
            attempt: 1,
        );
    }
}
