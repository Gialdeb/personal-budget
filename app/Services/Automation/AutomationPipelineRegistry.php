<?php

namespace App\Services\Automation;

use App\Jobs\Automation\RunRecurringPipelineJob;
use InvalidArgumentException;

class AutomationPipelineRegistry
{
    public function jobClassFor(string $pipeline): string
    {
        return match ($pipeline) {
            'recurring_pipeline' => RunRecurringPipelineJob::class,
            default => throw new InvalidArgumentException("Unsupported automation pipeline [{$pipeline}]."),
        };
    }

    public function exists(string $pipeline): bool
    {
        try {
            $this->jobClassFor($pipeline);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function isEnabled(string $pipeline): bool
    {
        return (bool) config("automation.pipelines.{$pipeline}.enabled", false);
    }
}
