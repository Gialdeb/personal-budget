<?php

namespace App\Services\Automation;

use App\Enums\AutomationRunStatusEnum;
use App\Models\AutomationRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class AutomationRunFreshness
{
    public function lastActivityAt(AutomationRun $run): ?CarbonInterface
    {
        if ($run->status === AutomationRunStatusEnum::RUNNING) {
            return $this->timestamp($run, 'started_at') ?? $this->timestamp($run, 'created_at');
        }

        return $this->timestamp($run, 'finished_at')
            ?? $this->timestamp($run, 'started_at')
            ?? $this->timestamp($run, 'created_at');
    }

    public function effectiveStaleThresholdMinutes(array $pipelineConfig): int
    {
        $maxExpectedIntervalMinutes = (int) ($pipelineConfig['max_expected_interval_minutes'] ?? 0);

        if ($maxExpectedIntervalMinutes <= 0) {
            return 0;
        }

        $graceMinutes = array_key_exists('stale_grace_minutes', $pipelineConfig)
            ? (int) $pipelineConfig['stale_grace_minutes']
            : (int) config('automation.health.stale_grace_minutes', 15);

        return $maxExpectedIntervalMinutes + max($graceMinutes, 0);
    }

    public function isStale(AutomationRun $run, array $pipelineConfig, ?CarbonInterface $now = null): bool
    {
        $thresholdMinutes = $this->effectiveStaleThresholdMinutes($pipelineConfig);

        if ($thresholdMinutes <= 0) {
            return false;
        }

        $lastActivityAt = $this->lastActivityAt($run);

        if (! $lastActivityAt) {
            return false;
        }

        return $lastActivityAt->lt(($now ?? now())->copy()->subMinutes($thresholdMinutes));
    }

    protected function timestamp(AutomationRun $run, string $column): ?CarbonInterface
    {
        $raw = $run->getRawOriginal($column);

        if (is_string($raw) && trim($raw) !== '') {
            return Carbon::parse($raw, config('app.timezone'));
        }

        $date = $run->getAttribute($column);

        if ($date instanceof CarbonInterface) {
            return $date->copy();
        }

        return null;
    }
}
