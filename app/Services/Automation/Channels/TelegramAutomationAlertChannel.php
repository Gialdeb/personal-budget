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

        Http::timeout(10)->post(
            "https://api.telegram.org/bot{$botToken}/sendMessage",
            [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ],
        );
    }

    protected function buildMessage(AutomationAlertData $alert): string
    {
        $lines = [
            "*{$alert->title}*",
            "Pipeline: `{$alert->pipeline}`",
            $alert->message,
        ];

        foreach ($alert->context as $key => $value) {
            $encoded = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value);

            $lines[] = "{$key}: `{$encoded}`";
        }

        return implode("\n", $lines);
    }
}
