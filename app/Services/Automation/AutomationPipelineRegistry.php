<?php

namespace App\Services\Automation;

use App\Jobs\Automation\RunBackupRetentionCleanupJob;
use App\Jobs\Automation\RunCreditCardAutopayJob;
use App\Jobs\Automation\RunFullBackupJob;
use App\Jobs\Automation\RunRecurringPipelineJob;
use App\Jobs\Automation\RunUserBackupJob;
use InvalidArgumentException;

class AutomationPipelineRegistry
{
    public function jobClassFor(string $pipeline): string
    {
        return match ($pipeline) {
            'recurring_pipeline' => RunRecurringPipelineJob::class,
            'credit_card_autopay' => RunCreditCardAutopayJob::class,
            'backup_retention_cleanup' => RunBackupRetentionCleanupJob::class,
            'full_backup' => RunFullBackupJob::class,
            'user_backup' => RunUserBackupJob::class,
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
