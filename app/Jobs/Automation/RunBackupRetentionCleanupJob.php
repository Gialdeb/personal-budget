<?php

namespace App\Jobs\Automation;

use App\Enums\AutomationTriggerTypeEnum;
use App\Services\Automation\AutomationPipelineRunner;
use App\Services\Automation\Backups\BackupRetentionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunBackupRetentionCleanupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public AutomationTriggerTypeEnum $triggerType = AutomationTriggerTypeEnum::SCHEDULED,
    ) {}

    public function handle(
        AutomationPipelineRunner $runner,
        BackupRetentionService $backupRetentionService,
    ): void {
        $runner->run(
            automationKey: 'backup_retention_cleanup',
            pipeline: 'backup_retention_cleanup',
            triggerType: $this->triggerType,
            callback: function () use ($backupRetentionService): array {
                $result = $backupRetentionService->run();

                return [
                    'status' => 'success',
                    'processed_count' => (int) ($result['inspected_count'] ?? 0),
                    'success_count' => (int) ($result['deleted_count'] ?? 0),
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
