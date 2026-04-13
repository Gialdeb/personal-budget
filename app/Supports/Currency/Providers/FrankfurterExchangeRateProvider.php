<?php

namespace App\Supports\Currency\Providers;

use App\DTO\Currency\ExchangeRateData;
use App\Exceptions\ExchangeRateLookupException;
use App\Supports\Currency\Contracts\ExchangeRateProviderInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class FrankfurterExchangeRateProvider implements ExchangeRateProviderInterface
{
    public function key(): string
    {
        return 'frankfurter';
    }

    public function label(): string
    {
        return 'Frankfurter';
    }

    public function fetch(
        string $fromCurrencyCode,
        string $toCurrencyCode,
        CarbonImmutable $rateDate,
    ): ExchangeRateData {
        $response = Http::acceptJson()
            ->timeout((int) config('currencies.providers.frankfurter.timeout', 10))
            ->get(rtrim((string) config('currencies.providers.frankfurter.base_url'), '/').'/rates', [
                'date' => $rateDate->toDateString(),
                'base' => $fromCurrencyCode,
                'quotes' => $toCurrencyCode,
            ]);

        if (! $response->successful()) {
            throw ExchangeRateLookupException::invalidResponse(
                $this->key(),
                sprintf('HTTP %s returned by provider.', $response->status()),
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload[0]) || ! is_array($payload[0])) {
            throw ExchangeRateLookupException::invalidResponse(
                $this->key(),
                'Missing expected rate array payload.',
            );
        }

        $item = $payload[0];
        $payloadDate = CarbonImmutable::parse((string) ($item['date'] ?? ''));
        $quoteCurrencyCode = strtoupper((string) ($item['quote'] ?? ''));
        $rate = $item['rate'] ?? null;

        if ($payloadDate->toDateString() !== $rateDate->toDateString()) {
            throw ExchangeRateLookupException::invalidResponse(
                $this->key(),
                sprintf(
                    'Provider returned %s instead of requested %s.',
                    $payloadDate->toDateString(),
                    $rateDate->toDateString(),
                ),
            );
        }

        if ($quoteCurrencyCode !== $toCurrencyCode || ! is_numeric($rate)) {
            throw ExchangeRateLookupException::invalidResponse(
                $this->key(),
                'Missing expected quote currency or numeric rate.',
            );
        }

        return new ExchangeRateData(
            fromCurrencyCode: $fromCurrencyCode,
            toCurrencyCode: $toCurrencyCode,
            rate: number_format((float) $rate, 8, '.', ''),
            date: $rateDate,
            source: $this->key(),
            fetchedAt: CarbonImmutable::now(),
            resolvedFrom: 'provider',
        );
    }
}
