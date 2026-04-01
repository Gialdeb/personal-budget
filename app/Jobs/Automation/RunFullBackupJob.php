<?php

namespace App\Jobs\Automation;

use App\Enums\AutomationTriggerTypeEnum;
use App\Services\Automation\AutomationPipelineRunner;
use App\Services\Automation\Backups\FullBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunFullBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public AutomationTriggerTypeEnum $triggerType = AutomationTriggerTypeEnum::SCHEDULED,
    ) {}

    public function handle(
        AutomationPipelineRunner $runner,
        FullBackupService $fullBackupService,
    ): void {
        $runner->run(
            automationKey: 'full_backup',
            pipeline: 'full_backup',
            triggerType: $this->triggerType,
            callback: function () use ($fullBackupService): array {
                $result = $fullBackupService->run();

                return [
                    'status' => 'success',
                    'processed_count' => (int) ($result['table_count'] ?? 0),
                    'success_count' => 1,
                    'warning_count' => 0,
                    'error_count' => 0,
                    'result' => $result,
                ];
            },
            jobClass: self::class,
            attempt: 1,
        );
    }
}
