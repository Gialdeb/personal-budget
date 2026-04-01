<?php

namespace App\Services\Notifications;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

class TelegramAdminAlertService
{
    public function sendVerifiedUserAlert(User $user): void
    {
        if (! config('services.telegram.enabled')) {
            return;
        }

        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (! is_string($botToken) || $botToken === '' || ! is_string($chatId) || $chatId === '') {
            return;
        }

        try {
            Http::timeout(10)->post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $this->buildVerifiedUserMessage($user),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ],
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    protected function buildVerifiedUserMessage(User $user): string
    {
        $lines = [
            '🆕 <b>Nuovo utente verificato</b>',
            '<b>App:</b> '.$this->escape(config('app.name')),
            '<b>Ambiente:</b> <code>'.$this->escape(app()->environment()).'</code>',
            '<b>Nome:</b> '.$this->escape(trim($user->name.' '.$user->surname) ?: 'N/D'),
            '<b>Email:</b> <code>'.$this->escape($user->email).'</code>',
            '<b>User ID:</b> <code>'.$user->getKey().'</code>',
            '<b>UUID:</b> <code>'.$this->escape($user->uuid).'</code>',
            '<b>Locale:</b> <code>'.$this->escape($user->preferredLocale()).'</code>',
            '<b>Registrato:</b> '.$this->formatDateTime($user->created_at),
            '<b>Email verificata:</b> '.$this->formatDateTime($user->email_verified_at),
            '<b>URL:</b> '.$this->escape(config('app.url')),
        ];

        return implode("\n", $lines);
    }

    protected function formatDateTime(CarbonInterface|string|null $value): string
    {
        if (! $value instanceof CarbonInterface) {
            return 'N/D';
        }

        return $this->escape($value->timezone(config('app.timezone'))->format('Y-m-d H:i:s T'));
    }

    protected function escape(?string $value): string
    {
        return htmlspecialchars($value ?? 'N/D', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
