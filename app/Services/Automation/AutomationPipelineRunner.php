<?php

namespace App\Services\Automation;

use App\Enums\AutomationTriggerTypeEnum;
use App\Models\AutomationRun;
use Throwable;

class AutomationPipelineRunner
{
    public function __construct(
        protected AutomationRunRecorder $recorder,
    ) {}

    /**
     * @param  callable(): array{
     *     status?: 'success'|'warning',
     *     processed_count?: int,
     *     success_count?: int,
     *     warning_count?: int,
     *     error_count?: int,
     *     result?: array,
     *     message?: string|null
     * }  $callback
     */
    public function run(
        string $automationKey,
        string $pipeline,
        AutomationTriggerTypeEnum $triggerType,
        callable $callback,
        ?string $jobClass = null,
        array $context = [],
        int $attempt = 1,
        ?string $batchId = null,
    ): AutomationRun {
        $run = $this->recorder->start(
            automationKey: $automationKey,
            pipeline: $pipeline,
            triggerType: $triggerType,
            jobClass: $jobClass,
            context: $context,
            attempt: $attempt,
            batchId: $batchId,
        );

        $run = $this->recorder->markRunning($run);

        try {
            $payload = $callback();

            $status = $payload['status'] ?? 'success';
            $result = $payload['result'] ?? [];
            $processedCount = $payload['processed_count'] ?? 0;
            $successCount = $payload['success_count'] ?? 0;
            $warningCount = $payload['warning_count'] ?? 0;
            $errorCount = $payload['error_count'] ?? 0;
            $message = $payload['message'] ?? null;

            if ($status === 'warning') {
                return $this->recorder->markWarning(
                    $run,
                    result: $result,
                    processedCount: $processedCount,
                    successCount: $successCount,
                    warningCount: $warningCount,
                    errorCount: $errorCount,
                    message: $message,
                );
            }

            return $this->recorder->markSuccess(
                $run,
                result: $result,
                processedCount: $processedCount,
                successCount: $successCount,
                warningCount: $warningCount,
                errorCount: $errorCount,
            );
        } catch (Throwable $exception) {
            return $this->recorder->markFailed($run, $exception);
        }
    }
}
