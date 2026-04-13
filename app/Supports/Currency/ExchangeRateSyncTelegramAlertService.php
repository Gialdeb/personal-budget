<?php

namespace App\Supports\Currency;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExchangeRateSyncTelegramAlertService
{
    /**
     * @param  array<int, array{quote_currency_code: string, status: string, source: string|null, resolved_from: string|null, rate: string|null, error: string|null}>  $results
     */
    public function sendFailureAlert(
        string $command,
        CarbonInterface $rateDate,
        string $baseCurrencyCode,
        array $results,
        ?string $exceptionMessage = null,
    ): void {
        if (! config('currencies.alerts.telegram.enabled')) {
            return;
        }

        $botToken = config('currencies.alerts.telegram.bot_token');
        $chatId = config('currencies.alerts.telegram.chat_id');

        if (! is_string($botToken) || trim($botToken) === '' || ! is_string($chatId) || trim($chatId) === '') {
            return;
        }

        $failedResults = collect($results)
            ->filter(fn (array $result): bool => $result['status'] === 'failed')
            ->values();

        $providers = $failedResults
            ->pluck('error')
            ->filter(fn (?string $error): bool => is_string($error) && trim($error) !== '')
            ->map(function (string $error): array {
                preg_match_all('/([a-z0-9_-]+):/i', $error, $matches);

                return $matches[1] ?? [];
            })
            ->flatten()
            ->map(fn (string $provider): string => strtolower(trim($provider)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $message = implode("\n", [
            '🚨 <b>Currency sync failed</b>',
            '<b>Environment:</b> <code>'.$this->escape(app()->environment()).'</code>',
            '<b>Date/Time:</b> <code>'.$this->escape(now()->toDateTimeString()).'</code>',
            '<b>Command:</b> <code>'.$this->escape($command).'</code>',
            '<b>Rate date:</b> <code>'.$this->escape($rateDate->toDateString()).'</code>',
            '<b>Base:</b> <code>'.$this->escape($baseCurrencyCode).'</code>',
            '<b>Providers:</b> <code>'.$this->escape($providers !== [] ? implode(', ', $providers) : 'unknown').'</code>',
            '<b>Failed pairs:</b> <code>'.$this->escape($failedResults->pluck('quote_currency_code')->implode(', ')).'</code>',
            '<b>Error:</b> '.$this->escape($exceptionMessage ?: (string) ($failedResults->pluck('error')->filter()->first() ?? 'Unknown error')),
        ]);

        try {
            Http::timeout(10)->post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('Exchange-rate sync telegram alert failed.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
