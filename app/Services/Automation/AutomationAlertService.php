<?php

namespace App\Services\Automation;

use App\DTO\Automation\AutomationAlertData;
use App\Models\AutomationRun;
use App\Services\Automation\Channels\LogAutomationAlertChannel;
use App\Services\Automation\Channels\TelegramAutomationAlertChannel;
use App\Services\Communication\DomainNotificationService;
use Illuminate\Support\Facades\Cache;

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

        if ($this->shouldSendTelegram($alert) && $this->shouldSendTelegramAlertOnce($alert)) {
            $this->telegramChannel->send($alert);
        }

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

    public function sendFailureAlertForRun(AutomationRun $run): void
    {
        if (! in_array($run->status?->value, ['failed', 'timed_out'], true)) {
            return;
        }

        $this->send(new AutomationAlertData(
            type: 'failed_run',
            pipeline: $run->pipeline,
            title: 'Automation pipeline failed',
            message: $run->error_message ?: 'The automation run failed without an explicit error message.',
            context: [
                'run_uuid' => $run->uuid,
                'environment' => app()->environment(),
                'status' => $run->status?->value,
                'occurred_at' => $run->finished_at?->toDateTimeString() ?? now()->toDateTimeString(),
                'exception_class' => $run->exception_class,
                'admin_url' => url('/admin/automation/runs/'.$run->uuid),
            ],
        ));
    }

    public function sendBackupAlertForRun(AutomationRun $run): void
    {
        if (! in_array($run->pipeline, ['full_backup', 'user_backup'], true)) {
            return;
        }

        $isFailure = in_array($run->status?->value, ['failed', 'timed_out'], true);

        if (! $isFailure && $run->status?->value !== 'success') {
            return;
        }

        $context = is_array($run->result) ? $run->result : [];
        $type = match ($run->pipeline) {
            'full_backup' => $isFailure ? 'full_backup_failed' : 'full_backup_success',
            'user_backup' => $isFailure ? 'user_backup_failed' : 'user_backup_success',
        };
        $title = match ($type) {
            'full_backup_success' => 'Backup completo completato',
            'full_backup_failed' => 'Backup completo fallito',
            'user_backup_success' => 'Backup utente completato',
            'user_backup_failed' => 'Backup utente fallito',
        };
        $message = $isFailure
            ? ($run->error_message ?: 'The backup failed without an explicit error message.')
            : ($context['summary'] ?? 'Backup completed successfully.');

        $this->send(new AutomationAlertData(
            type: $type,
            pipeline: $run->pipeline,
            title: $title,
            message: $message,
            context: [
                'run_uuid' => $run->uuid,
                'environment' => app()->environment(),
                'status' => $run->status?->value,
                'timestamp' => $run->finished_at?->toDateTimeString() ?? now()->toDateTimeString(),
                'path' => $context['path'] ?? null,
                'size_human' => $context['size_human'] ?? null,
                'duration_human' => $context['duration_human'] ?? null,
                'subject' => $context['subject'] ?? null,
                'user_count' => $context['user_count'] ?? null,
            ],
        ));
    }

    protected function shouldSendDomainNotification(AutomationAlertData $alert): bool
    {
        if (in_array($alert->pipeline, ['full_backup', 'user_backup', 'backup_retention_cleanup'], true)) {
            return false;
        }

        if (! in_array($alert->type, ['failed_run', 'stale_run', 'running_too_long', 'missing_run'], true)) {
            return false;
        }

        if ($alert->type === 'missing_run' && app()->environment('local')) {
            return false;
        }

        return true;
    }

    protected function shouldSendTelegram(AutomationAlertData $alert): bool
    {
        return in_array($alert->type, [
            'failed_run',
            'full_backup_success',
            'full_backup_failed',
            'user_backup_success',
            'user_backup_failed',
        ], true);
    }

    protected function shouldSendTelegramAlertOnce(AutomationAlertData $alert): bool
    {
        $ttl = (int) config('automation.alerts.dedupe_ttl_minutes', 1440);

        if ($ttl <= 0) {
            return true;
        }

        $signature = implode('|', [
            $alert->type,
            $alert->pipeline,
            (string) ($alert->context['run_uuid'] ?? $alert->context['timestamp'] ?? $alert->message),
        ]);

        return Cache::add(
            'automation:telegram-alert:'.sha1($signature),
            now()->toIso8601String(),
            now()->addMinutes($ttl),
        );
    }
}
