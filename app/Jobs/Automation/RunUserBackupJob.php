<?php

namespace App\Jobs\Automation;

use App\Enums\AutomationTriggerTypeEnum;
use App\Services\Automation\AutomationPipelineRunner;
use App\Services\Automation\Backups\UserBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class RunUserBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public AutomationTriggerTypeEnum $triggerType = AutomationTriggerTypeEnum::SCHEDULED,
    ) {}

    public function handle(
        AutomationPipelineRunner $runner,
        UserBackupService $userBackupService,
    ): void {
        $backupDisk = (string) config('automation.backups.disk', 'local');
        $disk = Storage::disk($backupDisk);

        $runner->run(
            automationKey: 'user_backup',
            pipeline: 'user_backup',
            triggerType: $this->triggerType,
            callback: function () use ($userBackupService): array {
                $result = $userBackupService->run();

                return [
                    'status' => 'success',
                    'processed_count' => (int) ($result['user_count'] ?? 0),
                    'success_count' => (int) ($result['user_count'] ?? 0),
                    'warning_count' => 0,
                    'error_count' => 0,
                    'result' => $result,
                ];
            },
            jobClass: self::class,
            context: [
                'environment' => app()->environment(),
                'backup_disk' => $backupDisk,
                'backup_root' => $disk->path(''),
            ],
            attempt: 1,
        );
    }
}
