<?php

namespace App\Services\Automation;

use App\Enums\AutomationTriggerTypeEnum;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;

class AutomationDispatcher
{
    public function __construct(
        protected AutomationPipelineRegistry $registry,
    ) {}

    /**
     * @param  array{reference_date?: ?string}  $context
     */
    public function dispatchPipeline(
        string $pipeline,
        AutomationTriggerTypeEnum $triggerType,
        array $context = [],
    ): string {
        if (! $this->registry->exists($pipeline)) {
            throw new InvalidArgumentException("Unsupported automation pipeline [{$pipeline}].");
        }

        if (! $this->registry->isEnabled($pipeline)) {
            throw new InvalidArgumentException("Automation pipeline [{$pipeline}] is disabled.");
        }

        $jobClass = $this->registry->jobClassFor($pipeline);
        $referenceDate = $context['reference_date'] ?? null;

        Bus::dispatch(new $jobClass($triggerType, $referenceDate));

        return $jobClass;
    }

    public function retryRun(AutomationRun $run): string
    {
        if (! in_array($run->status?->value, ['failed', 'timed_out', 'warning'], true)) {
            throw new InvalidArgumentException("Automation run [{$run->uuid}] is not retryable.");
        }

        return $this->dispatchPipeline(
            pipeline: $run->automation_key,
            triggerType: AutomationTriggerTypeEnum::RETRY,
            context: is_array($run->context) ? $run->context : [],
        );
    }
}
