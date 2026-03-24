<?php

namespace App\Services\Communication;

use App\Notifications\AutomationFailedNotification;
use App\Notifications\ImportCompletedNotification;
use App\Notifications\MonthlyReportReadyNotification;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

class NotificationClassResolver
{
    public function resolve(string $topicKey, array $payload = []): Notification
    {
        return match ($topicKey) {
            'automation_failed' => new AutomationFailedNotification($payload),
            'import_completed' => new ImportCompletedNotification($payload),
            'monthly_report_ready' => new MonthlyReportReadyNotification($payload),
            default => throw new InvalidArgumentException("Unsupported notification topic [{$topicKey}]."),
        };
    }
}
