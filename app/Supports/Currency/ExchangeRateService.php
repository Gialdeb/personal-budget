<?php

namespace App\Supports\Currency;

use App\DTO\Currency\ExchangeRateData;
use App\Exceptions\ExchangeRateLookupException;
use App\Models\ExchangeRate;
use App\Supports\Currency\Contracts\ExchangeRateProviderInterface;
use App\Supports\Currency\Providers\FawazExchangeRateProvider;
use App\Supports\Currency\Providers\FrankfurterExchangeRateProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ExchangeRateService
{
    /**
     * @var array<int, ExchangeRateProviderInterface>
     */
    protected array $providers;

    public function __construct(
        protected CurrencySupport $currencySupport,
        FrankfurterExchangeRateProvider $frankfurterProvider,
        FawazExchangeRateProvider $fawazProvider,
    ) {
        $this->providers = [$frankfurterProvider, $fawazProvider];
    }

    public function resolve(
        string $fromCurrencyCode,
        string $toCurrencyCode,
        Carbon|string $rateDate,
    ): ExchangeRateData {
        $normalizedDate = $this->normalizeRateDate($rateDate);
        $normalizedFromCurrencyCode = $this->normalizeCurrencyCode($fromCurrencyCode);
        $normalizedToCurrencyCode = $this->normalizeCurrencyCode($toCurrencyCode);

        if ($normalizedFromCurrencyCode === $normalizedToCurrencyCode) {
            return new ExchangeRateData(
                fromCurrencyCode: $normalizedFromCurrencyCode,
                toCurrencyCode: $normalizedToCurrencyCode,
                rate: '1.00000000',
                date: $normalizedDate,
                source: 'identity',
                fetchedAt: CarbonImmutable::now(),
                resolvedFrom: 'identity',
            );
        }

        $cachedRate = ExchangeRate::query()
            ->forPairOnDate(
                $normalizedFromCurrencyCode,
                $normalizedToCurrencyCode,
                $normalizedDate,
            )
            ->first();

        if ($cachedRate instanceof ExchangeRate) {
            return new ExchangeRateData(
                fromCurrencyCode: $cachedRate->base_currency_code,
                toCurrencyCode: $cachedRate->quote_currency_code,
                rate: (string) $cachedRate->rate,
                date: CarbonImmutable::parse($cachedRate->rate_date),
                source: (string) ($cachedRate->source ?: 'unknown'),
                fetchedAt: $cachedRate->fetched_at instanceof CarbonImmutable
                    ? $cachedRate->fetched_at
                    : CarbonImmutable::parse($cachedRate->updated_at ?? now()),
                resolvedFrom: 'database',
            );
        }

        $attempts = [];

        foreach ($this->providers as $provider) {
            try {
                $resolvedRate = $provider->fetch(
                    $normalizedFromCurrencyCode,
                    $normalizedToCurrencyCode,
                    $normalizedDate,
                );

                $storedRate = ExchangeRate::query()->updateOrCreate(
                    [
                        'base_currency_code' => $resolvedRate->fromCurrencyCode,
                        'quote_currency_code' => $resolvedRate->toCurrencyCode,
                        'rate_date' => $resolvedRate->date->toDateString(),
                    ],
                    [
                        'rate' => $resolvedRate->rate,
                        'source' => $resolvedRate->source,
                        'fetched_at' => $resolvedRate->fetchedAt,
                    ],
                );

                return new ExchangeRateData(
                    fromCurrencyCode: $storedRate->base_currency_code,
                    toCurrencyCode: $storedRate->quote_currency_code,
                    rate: (string) $storedRate->rate,
                    date: CarbonImmutable::parse($storedRate->rate_date),
                    source: (string) ($storedRate->source ?: $resolvedRate->source),
                    fetchedAt: $storedRate->fetched_at instanceof CarbonImmutable
                        ? $storedRate->fetched_at
                        : $resolvedRate->fetchedAt,
                    resolvedFrom: 'provider',
                );
            } catch (Throwable $exception) {
                $attempts[] = [
                    'provider' => $provider->key(),
                    'message' => $exception->getMessage(),
                ];

                Log::warning('Exchange rate provider failed.', [
                    'provider' => $provider->key(),
                    'from_currency_code' => $normalizedFromCurrencyCode,
                    'to_currency_code' => $normalizedToCurrencyCode,
                    'rate_date' => $normalizedDate->toDateString(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        throw ExchangeRateLookupException::providersFailed(
            $normalizedFromCurrencyCode,
            $normalizedToCurrencyCode,
            $normalizedDate->toDateString(),
            $attempts,
        );
    }

    /**
     * @param  array<int, string>  $quoteCurrencyCodes
     * @return array<int, array{quote_currency_code: string, status: string, source: string|null, resolved_from: string|null, rate: string|null, error: string|null}>
     */
    public function sync(
        string $baseCurrencyCode,
        array $quoteCurrencyCodes,
        Carbon|string|null $rateDate = null,
    ): array {
        $normalizedDate = $this->normalizeRateDate($rateDate ?? CarbonImmutable::now());
        $normalizedBaseCurrencyCode = $this->normalizeCurrencyCode($baseCurrencyCode);

        return collect($quoteCurrencyCodes)
            ->map(fn (mixed $code): ?string => is_string($code) ? $this->currencySupport->normalize($code) : null)
            ->filter()
            ->reject(fn (string $quoteCurrencyCode): bool => $quoteCurrencyCode === $normalizedBaseCurrencyCode)
            ->values()
            ->map(function (string $quoteCurrencyCode) use ($normalizedBaseCurrencyCode, $normalizedDate): array {
                try {
                    $result = $this->resolve(
                        $normalizedBaseCurrencyCode,
                        $quoteCurrencyCode,
                        $normalizedDate,
                    );

                    return [
                        'quote_currency_code' => $quoteCurrencyCode,
                        'status' => 'success',
                        'source' => $result->source,
                        'resolved_from' => $result->resolvedFrom,
                        'rate' => $result->rate,
                        'error' => null,
                    ];
                } catch (Throwable $exception) {
                    return [
                        'quote_currency_code' => $quoteCurrencyCode,
                        'status' => 'failed',
                        'source' => null,
                        'resolved_from' => null,
                        'rate' => null,
                        'error' => $exception->getMessage(),
                    ];
                }
            })
            ->values()
            ->all();
    }

    protected function normalizeCurrencyCode(string $currencyCode): string
    {
        $normalizedCurrencyCode = $this->currencySupport->normalize($currencyCode);

        if ($normalizedCurrencyCode === null) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported currency code [%s].',
                $currencyCode,
            ));
        }

        return $normalizedCurrencyCode;
    }

    protected function normalizeRateDate(Carbon|string $rateDate): CarbonImmutable
    {
        $normalizedDate = $rateDate instanceof Carbon
            ? CarbonImmutable::parse($rateDate->toDateString())
            : CarbonImmutable::parse($rateDate);

        if ($normalizedDate->isFuture()) {
            throw new InvalidArgumentException('Exchange rates are not available for future dates.');
        }

        return $normalizedDate->startOfDay();
    }
}
