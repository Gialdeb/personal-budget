<?php

namespace App\Services\Automation;

use App\Enums\AutomationRunStatusEnum;
use App\Models\AutomationRun;

class AutomationStatusService
{
    public function __construct(protected AutomationRunFreshness $freshness) {}

    public function pipelineStatuses(): array
    {
        $pipelines = config('automation.pipelines', []);
        $runningStaleAfterMinutes = (int) config('automation.health.running_stale_after_minutes', 30);

        $result = [];

        foreach ($pipelines as $pipelineKey => $config) {
            $latestRun = AutomationRun::query()
                ->where('automation_key', $pipelineKey)
                ->latest('created_at')
                ->first();

            $isEnabled = (bool) ($config['enabled'] ?? false);
            $maxExpectedIntervalMinutes = (int) ($config['max_expected_interval_minutes'] ?? 0);

            $state = 'never_ran';

            if (! $isEnabled) {
                $state = 'disabled';
            } elseif ($latestRun) {
                $state = $this->resolveState($latestRun, $config, $runningStaleAfterMinutes);
            }

            $result[] = [
                'key' => $pipelineKey,
                'enabled' => $isEnabled,
                'critical' => (bool) ($config['critical'] ?? false),
                'alert_on_failure' => (bool) ($config['alert_on_failure'] ?? false),
                'supports_reference_date' => (bool) ($config['supports_reference_date'] ?? false),
                'max_expected_interval_minutes' => $maxExpectedIntervalMinutes,
                'effective_stale_threshold_minutes' => $this->freshness->effectiveStaleThresholdMinutes($config),
                'state' => $state,
                'latest_run' => $latestRun ? [
                    'uuid' => $latestRun->uuid,
                    'status' => $latestRun->status?->value,
                    'trigger_type' => $latestRun->trigger_type?->value,
                    'started_at' => $latestRun->started_at?->toDateTimeString(),
                    'finished_at' => $latestRun->finished_at?->toDateTimeString(),
                    'created_at' => $latestRun->created_at?->toDateTimeString(),
                    'duration_ms' => $latestRun->duration_ms,
                    'error_message' => $latestRun->error_message,
                ] : null,
            ];
        }

        return $result;
    }

    protected function resolveState(
        AutomationRun $latestRun,
        array $pipelineConfig,
        int $runningStaleAfterMinutes,
    ): string {
        if ($latestRun->status === AutomationRunStatusEnum::RUNNING) {
            if ($latestRun->started_at && $latestRun->started_at->lte(now()->subMinutes($runningStaleAfterMinutes))) {
                return 'stuck';
            }

            return 'running';
        }

        if ($latestRun->status === AutomationRunStatusEnum::FAILED) {
            return 'failed';
        }

        if ($latestRun->status === AutomationRunStatusEnum::TIMED_OUT) {
            return 'timed_out';
        }

        if ($this->freshness->isStale($latestRun, $pipelineConfig)) {
            return 'stale';
        }

        if ($latestRun->status === AutomationRunStatusEnum::WARNING) {
            return 'warning';
        }

        if ($latestRun->status === AutomationRunStatusEnum::SUCCESS) {
            return 'healthy';
        }

        return 'unknown';
    }
}
