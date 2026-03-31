<?php

namespace App\Services\Automation;

use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Events\Admin\AutomationRunUpdated;
use App\Models\AutomationRun;
use Throwable;

class AutomationRunRecorder
{
    public function start(
        string $automationKey,
        string $pipeline,
        AutomationTriggerTypeEnum $triggerType,
        ?string $jobClass = null,
        array $context = [],
        int $attempt = 1,
        ?string $batchId = null,
    ): AutomationRun {
        $run = AutomationRun::query()->create([
            'automation_key' => $automationKey,
            'pipeline' => $pipeline,
            'job_class' => $jobClass,
            'status' => AutomationRunStatusEnum::PENDING,
            'trigger_type' => $triggerType,
            'attempt' => $attempt,
            'batch_id' => $batchId,
            'host' => gethostname() ?: null,
            'context' => $context,
        ]);

        $this->broadcastUpdate($run);

        return $run;
    }

    public function markRunning(AutomationRun $run): AutomationRun
    {
        $run->forceFill([
            'status' => AutomationRunStatusEnum::RUNNING,
            'started_at' => now(),
        ])->save();

        return $this->broadcastUpdate($run);
    }

    public function markSuccess(
        AutomationRun $run,
        array $result = [],
        int $processedCount = 0,
        int $successCount = 0,
        int $warningCount = 0,
        int $errorCount = 0,
    ): AutomationRun {
        $finishedAt = now();

        $run->forceFill([
            'status' => AutomationRunStatusEnum::SUCCESS,
            'finished_at' => $finishedAt,
            'duration_ms' => $this->calculateDurationMs($run->started_at, $finishedAt),
            'processed_count' => $processedCount,
            'success_count' => $successCount,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
            'result' => $result,
        ])->save();

        return $this->broadcastUpdate($run);
    }

    public function markWarning(
        AutomationRun $run,
        array $result = [],
        int $processedCount = 0,
        int $successCount = 0,
        int $warningCount = 0,
        int $errorCount = 0,
        ?string $message = null,
    ): AutomationRun {
        $finishedAt = now();

        $run->forceFill([
            'status' => AutomationRunStatusEnum::WARNING,
            'finished_at' => $finishedAt,
            'duration_ms' => $this->calculateDurationMs($run->started_at, $finishedAt),
            'processed_count' => $processedCount,
            'success_count' => $successCount,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
            'result' => $result,
            'error_message' => $message,
        ])->save();

        return $this->broadcastUpdate($run);
    }

    public function markFailed(
        AutomationRun $run,
        Throwable $exception,
        array $result = [],
        int $processedCount = 0,
        int $successCount = 0,
        int $warningCount = 0,
        int $errorCount = 1,
    ): AutomationRun {
        $finishedAt = now();

        $run->forceFill([
            'status' => AutomationRunStatusEnum::FAILED,
            'finished_at' => $finishedAt,
            'duration_ms' => $this->calculateDurationMs($run->started_at, $finishedAt),
            'processed_count' => $processedCount,
            'success_count' => $successCount,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
            'result' => $result,
            'error_message' => $exception->getMessage(),
            'exception_class' => $exception::class,
        ])->save();

        return $this->broadcastUpdate($run);
    }

    public function markTimedOut(
        AutomationRun $run,
        ?string $message = null,
        array $result = [],
    ): AutomationRun {
        $finishedAt = now();

        $run->forceFill([
            'status' => AutomationRunStatusEnum::TIMED_OUT,
            'finished_at' => $finishedAt,
            'duration_ms' => $this->calculateDurationMs($run->started_at, $finishedAt),
            'result' => $result,
            'error_message' => $message,
        ])->save();

        return $this->broadcastUpdate($run);
    }

    protected function calculateDurationMs($startedAt, $finishedAt): ?int
    {
        if (! $startedAt || ! $finishedAt) {
            return null;
        }

        return (int) $startedAt->diffInMilliseconds($finishedAt);
    }

    protected function broadcastUpdate(AutomationRun $run): AutomationRun
    {
        $run = $run->refresh();

        event(new AutomationRunUpdated($run));

        return $run;
    }
}
