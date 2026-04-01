<?php

namespace App\Services\Automation\Channels;

use App\Contracts\Automation\AutomationAlertChannelInterface;
use App\DTO\Automation\AutomationAlertData;
use Illuminate\Support\Facades\Http;

class TelegramAutomationAlertChannel implements AutomationAlertChannelInterface
{
    public function send(AutomationAlertData $alert): void
    {
        if (! config('automation.alerts.telegram.enabled')) {
            return;
        }

        $botToken = config('automation.alerts.telegram.bot_token');
        $chatId = config('automation.alerts.telegram.chat_id');

        if (! $botToken || ! $chatId) {
            return;
        }

        $text = $this->buildMessage($alert);

        try {
            Http::timeout(10)->post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ],
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    protected function buildMessage(AutomationAlertData $alert): string
    {
        return match ($alert->type) {
            'failed_run' => $this->buildFailedRunMessage($alert),
            'full_backup_success', 'full_backup_failed', 'user_backup_success', 'user_backup_failed' => $this->buildBackupMessage($alert),
            default => $this->buildGenericMessage($alert),
        };
    }

    protected function buildFailedRunMessage(AutomationAlertData $alert): string
    {
        $runUuid = $this->stringValue($alert->context['run_uuid'] ?? null, 'N/D');
        $environment = $this->stringValue($alert->context['environment'] ?? app()->environment(), app()->environment());
        $status = $this->stringValue($alert->context['status'] ?? 'failed', 'failed');
        $occurredAt = $this->stringValue($alert->context['occurred_at'] ?? null, now()->toDateTimeString());
        $adminUrl = $this->stringValue($alert->context['admin_url'] ?? null);

        $lines = [
            '🚨 <b>Automazione fallita</b>',
            '<b>Nome:</b> <code>'.$this->escape($alert->pipeline).'</code>',
            '<b>Ambiente:</b> <code>'.$this->escape($environment).'</code>',
            '<b>Data/Ora:</b> <code>'.$this->escape($occurredAt).'</code>',
            '<b>Stato:</b> <code>'.$this->escape($status).'</code>',
            '<b>Run:</b> <code>'.$this->escape($runUuid).'</code>',
            '<b>Errore:</b> '.$this->escape($alert->message),
        ];

        if ($adminUrl !== '') {
            $lines[] = '<b>Admin:</b> '.$this->escape($adminUrl);
        }

        return implode("\n", $lines);
    }

    protected function buildBackupMessage(AutomationAlertData $alert): string
    {
        $environment = $this->stringValue($alert->context['environment'] ?? app()->environment(), app()->environment());
        $status = $this->stringValue($alert->context['status'] ?? null, str_contains($alert->type, 'failed') ? 'failed' : 'success');
        $timestamp = $this->stringValue($alert->context['timestamp'] ?? null, now()->toDateTimeString());
        $path = $this->stringValue($alert->context['path'] ?? null);
        $size = $this->stringValue($alert->context['size_human'] ?? null);
        $duration = $this->stringValue($alert->context['duration_human'] ?? null);
        $subject = $this->stringValue($alert->context['subject'] ?? null);
        $count = $this->stringValue($alert->context['user_count'] ?? null);
        $icon = str_contains($alert->type, 'failed') ? '❌' : '✅';

        $lines = [
            $icon.' <b>'.$this->escape($alert->title).'</b>',
            '<b>Tipo:</b> <code>'.$this->escape($alert->pipeline).'</code>',
            '<b>Ambiente:</b> <code>'.$this->escape($environment).'</code>',
            '<b>Stato:</b> <code>'.$this->escape($status).'</code>',
            '<b>Timestamp:</b> <code>'.$this->escape($timestamp).'</code>',
        ];

        if ($subject !== '') {
            $lines[] = '<b>Utente/Scope:</b> '.$this->escape($subject);
        }

        if ($count !== '') {
            $lines[] = '<b>Utenti inclusi:</b> <code>'.$this->escape($count).'</code>';
        }

        if ($size !== '') {
            $lines[] = '<b>Dimensione:</b> <code>'.$this->escape($size).'</code>';
        }

        if ($duration !== '') {
            $lines[] = '<b>Durata:</b> <code>'.$this->escape($duration).'</code>';
        }

        if ($path !== '') {
            $lines[] = '<b>Path:</b> <code>'.$this->escape($path).'</code>';
        }

        $lines[] = '<b>Dettaglio:</b> '.$this->escape($alert->message);

        return implode("\n", $lines);
    }

    protected function buildGenericMessage(AutomationAlertData $alert): string
    {
        return implode("\n", [
            'ℹ️ <b>'.$this->escape($alert->title).'</b>',
            '<b>Pipeline:</b> <code>'.$this->escape($alert->pipeline).'</code>',
            $this->escape($alert->message),
        ]);
    }

    protected function stringValue(mixed $value, string $fallback = ''): string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $fallback;
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
