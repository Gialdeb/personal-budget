<?php

namespace App\Jobs\Automation;

use App\DTO\Automation\AutomationAlertData;
use App\Enums\AutomationRunStatusEnum;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CheckAutomationHealthJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function handle(AutomationAlertService $alertService): void
    {
        $pipelines = config('automation.pipelines', []);
        $staleRunningMinutes = (int) config('automation.health.running_stale_after_minutes', 30);

        foreach ($pipelines as $pipelineKey => $pipelineConfig) {
            if (! ($pipelineConfig['enabled'] ?? false)) {
                continue;
            }

            $this->checkMissingOrStaleRun($pipelineKey, $pipelineConfig, $alertService);
            $this->checkRunningTooLong($pipelineKey, $staleRunningMinutes, $alertService);
            $this->checkLatestFailure($pipelineKey, $pipelineConfig, $alertService);
        }
    }

    protected function checkMissingOrStaleRun(
        string $pipelineKey,
        array $pipelineConfig,
        AutomationAlertService $alertService,
    ): void {
        $maxExpectedIntervalMinutes = (int) ($pipelineConfig['max_expected_interval_minutes'] ?? 0);

        if ($maxExpectedIntervalMinutes <= 0) {
            return;
        }

        $latestRun = AutomationRun::query()
            ->where('automation_key', $pipelineKey)
            ->latest('created_at')
            ->first();

        if (! $latestRun) {
            if ($this->shouldDeferMissingRunAlert($pipelineKey, $maxExpectedIntervalMinutes)) {
                return;
            }

            $alertService->send(new AutomationAlertData(
                type: 'missing_run',
                pipeline: $pipelineKey,
                title: 'Automation pipeline has never run',
                message: 'No execution has been recorded yet for this pipeline.',
                context: [
                    'max_expected_interval_minutes' => $maxExpectedIntervalMinutes,
                ],
            ));

            return;
        }

        Cache::forget($this->missingRunCacheKey($pipelineKey));

        if ($latestRun->created_at->diffInMinutes(now()) > $maxExpectedIntervalMinutes) {
            $alertService->send(new AutomationAlertData(
                type: 'stale_run',
                pipeline: $pipelineKey,
                title: 'Automation pipeline is stale',
                message: 'The latest execution is older than the expected interval.',
                context: [
                    'last_run_at' => $latestRun->created_at?->toDateTimeString(),
                    'max_expected_interval_minutes' => $maxExpectedIntervalMinutes,
                    'last_status' => $latestRun->status?->value,
                    'run_uuid' => $latestRun->uuid,
                ],
            ));
        }
    }

    protected function checkRunningTooLong(
        string $pipelineKey,
        int $staleRunningMinutes,
        AutomationAlertService $alertService,
    ): void {
        $stuckRun = AutomationRun::query()
            ->where('automation_key', $pipelineKey)
            ->where('status', AutomationRunStatusEnum::RUNNING)
            ->whereNotNull('started_at')
            ->where('started_at', '<=', now()->subMinutes($staleRunningMinutes))
            ->latest('started_at')
            ->first();

        if (! $stuckRun) {
            return;
        }

        $alertService->send(new AutomationAlertData(
            type: 'running_too_long',
            pipeline: $pipelineKey,
            title: 'Automation pipeline appears stuck',
            message: 'A run is still marked as running beyond the configured threshold.',
            context: [
                'started_at' => $stuckRun->started_at?->toDateTimeString(),
                'threshold_minutes' => $staleRunningMinutes,
                'run_uuid' => $stuckRun->uuid,
            ],
        ));
    }

    protected function checkLatestFailure(
        string $pipelineKey,
        array $pipelineConfig,
        AutomationAlertService $alertService,
    ): void {
        if (! ($pipelineConfig['alert_on_failure'] ?? false)) {
            return;
        }

        $latestRun = AutomationRun::query()
            ->where('automation_key', $pipelineKey)
            ->latest('created_at')
            ->first();

        if (! $latestRun) {
            return;
        }

        if ($latestRun->status !== AutomationRunStatusEnum::FAILED) {
            return;
        }

        $alertService->send(new AutomationAlertData(
            type: 'failed_run',
            pipeline: $pipelineKey,
            title: 'Automation pipeline failed',
            message: $latestRun->error_message ?: 'The latest run failed without an explicit error message.',
            context: [
                'run_uuid' => $latestRun->uuid,
                'exception_class' => $latestRun->exception_class,
                'finished_at' => $latestRun->finished_at?->toDateTimeString(),
            ],
        ));
    }

    protected function shouldDeferMissingRunAlert(
        string $pipelineKey,
        int $maxExpectedIntervalMinutes,
    ): bool {
        if (
            app()->environment('local')
            && (bool) config('automation.health.skip_missing_run_alert_in_local', true)
        ) {
            return true;
        }

        $graceMinutes = $this->missingRunGraceMinutes($maxExpectedIntervalMinutes);

        if ($graceMinutes <= 0) {
            return false;
        }

        $cacheKey = $this->missingRunCacheKey($pipelineKey);
        $firstObservedAt = Cache::get($cacheKey);

        if (! is_string($firstObservedAt)) {
            Cache::forever($cacheKey, now()->toIso8601String());

            return true;
        }

        return now()->diffInMinutes(Carbon::parse($firstObservedAt)) < $graceMinutes;
    }

    protected function missingRunGraceMinutes(int $maxExpectedIntervalMinutes): int
    {
        $multiplier = (int) config('automation.health.missing_run_grace_multiplier', 1);

        if ($maxExpectedIntervalMinutes <= 0 || $multiplier <= 0) {
            return 0;
        }

        return $maxExpectedIntervalMinutes * $multiplier;
    }

    protected function missingRunCacheKey(string $pipelineKey): string
    {
        return "automation:health:missing-run-first-observed:{$pipelineKey}";
    }
}
