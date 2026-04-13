<?php

namespace App\Supports\Currency\Providers;

use App\DTO\Currency\ExchangeRateData;
use App\Exceptions\ExchangeRateLookupException;
use App\Supports\Currency\Contracts\ExchangeRateProviderInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class FawazExchangeRateProvider implements ExchangeRateProviderInterface
{
    public function key(): string
    {
        return 'fawaz';
    }

    public function label(): string
    {
        return 'fawazahmed0/exchange-api';
    }

    public function fetch(
        string $fromCurrencyCode,
        string $toCurrencyCode,
        CarbonImmutable $rateDate,
    ): ExchangeRateData {
        $lastException = null;

        foreach ($this->candidateUrls($fromCurrencyCode, $rateDate) as $url) {
            $response = Http::acceptJson()
                ->timeout((int) config('currencies.providers.fawaz.timeout', 10))
                ->get($url);

            if (! $response->successful()) {
                $lastException = ExchangeRateLookupException::invalidResponse(
                    $this->key(),
                    sprintf('HTTP %s returned by provider mirror.', $response->status()),
                );

                continue;
            }

            $payload = $response->json();
            $baseKey = strtolower($fromCurrencyCode);
            $quoteKey = strtolower($toCurrencyCode);
            $payloadDate = isset($payload['date'])
                ? CarbonImmutable::parse((string) $payload['date'])
                : null;
            $rate = is_array($payload[$baseKey] ?? null)
                ? ($payload[$baseKey][$quoteKey] ?? null)
                : null;

            if (! $payloadDate instanceof CarbonImmutable) {
                $lastException = ExchangeRateLookupException::invalidResponse(
                    $this->key(),
                    'Missing expected response date.',
                );

                continue;
            }

            if ($payloadDate->toDateString() !== $rateDate->toDateString()) {
                $lastException = ExchangeRateLookupException::invalidResponse(
                    $this->key(),
                    sprintf(
                        'Provider returned %s instead of requested %s.',
                        $payloadDate->toDateString(),
                        $rateDate->toDateString(),
                    ),
                );

                continue;
            }

            if (! is_numeric($rate)) {
                $lastException = ExchangeRateLookupException::invalidResponse(
                    $this->key(),
                    'Missing expected quote currency or numeric rate.',
                );

                continue;
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

        throw $lastException ?? ExchangeRateLookupException::invalidResponse(
            $this->key(),
            'No fallback mirrors were available.',
        );
    }

    /**
     * @return array<int, string>
     */
    protected function candidateUrls(string $fromCurrencyCode, CarbonImmutable $rateDate): array
    {
        $baseKey = strtolower($fromCurrencyCode);
        $dateSegment = $rateDate->toDateString();
        $apiVersion = (string) config('currencies.providers.fawaz.api_version', 'v1');
        $pagesDevTemplate = (string) config('currencies.providers.fawaz.pages_dev_template');
        $jsdelivrBaseUrl = rtrim((string) config('currencies.providers.fawaz.jsdelivr_base_url'), '/');

        return [
            sprintf($pagesDevTemplate, $dateSegment)."/{$apiVersion}/currencies/{$baseKey}.json",
            "{$jsdelivrBaseUrl}@{$dateSegment}/{$apiVersion}/currencies/{$baseKey}.json",
        ];
    }
}
