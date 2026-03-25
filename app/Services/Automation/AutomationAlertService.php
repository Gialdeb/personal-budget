<?php

namespace App\Services\Automation;

use App\DTO\Automation\AutomationAlertData;
use App\Services\Automation\Channels\LogAutomationAlertChannel;
use App\Services\Automation\Channels\TelegramAutomationAlertChannel;
use App\Services\Communication\DomainNotificationService;

class AutomationAlertService
{
    public function __construct(
        protected LogAutomationAlertChannel $logChannel,
        protected TelegramAutomationAlertChannel $telegramChannel,
        protected DomainNotificationService $domainNotificationService,
    ) {}

    public function send(AutomationAlertData $alert): void
    {
        if (! config('automation.alerts.enabled')) {
            return;
        }

        $this->logChannel->send($alert);
        $this->telegramChannel->send($alert);

        if ($this->shouldSendDomainNotification($alert)) {
            $this->domainNotificationService->sendAutomationFailed([
                'type' => $alert->type,
                'pipeline' => $alert->pipeline,
                'title' => $alert->title,
                'message' => $alert->message,
                'context' => $alert->context,
            ]);
        }
    }

    protected function shouldSendDomainNotification(AutomationAlertData $alert): bool
    {
        if (! in_array($alert->type, ['failed_run', 'stale_run', 'running_too_long', 'missing_run'], true)) {
            return false;
        }

        if ($alert->type === 'missing_run' && app()->environment('local')) {
            return false;
        }

        return true;
    }
}
