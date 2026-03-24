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

    public function dispatchPipeline(string $pipeline, AutomationTriggerTypeEnum $triggerType): string
    {
        if (! $this->registry->exists($pipeline)) {
            throw new InvalidArgumentException("Unsupported automation pipeline [{$pipeline}].");
        }

        if (! $this->registry->isEnabled($pipeline)) {
            throw new InvalidArgumentException("Automation pipeline [{$pipeline}] is disabled.");
        }

        $jobClass = $this->registry->jobClassFor($pipeline);

        Bus::dispatch(new $jobClass($triggerType));

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
        );
    }
}
