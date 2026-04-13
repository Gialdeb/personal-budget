<?php

use App\Exceptions\ExchangeRateLookupException;
use App\Models\ExchangeRate;
use App\Supports\Currency\ExchangeRateService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('currencies.providers.frankfurter.base_url', 'https://frankfurter.test/v2');
    config()->set('currencies.providers.fawaz.pages_dev_template', 'https://%s.fawaz.test');
    config()->set('currencies.providers.fawaz.jsdelivr_base_url', 'https://cdn.fawaz.test/currency-api');
});

it('resolves a historical exchange rate from the primary provider and stores it locally', function () {
    Http::fake([
        'https://frankfurter.test/*' => Http::response([
            [
                'date' => '2026-04-09',
                'base' => 'EUR',
                'quote' => 'USD',
                'rate' => 1.0845,
            ],
        ], 200),
    ]);

    $result = app(ExchangeRateService::class)->resolve('EUR', 'USD', '2026-04-09');

    expect($result->fromCurrencyCode)->toBe('EUR')
        ->and($result->toCurrencyCode)->toBe('USD')
        ->and($result->rate)->toBe('1.08450000')
        ->and($result->date->toDateString())->toBe('2026-04-09')
        ->and($result->source)->toBe('frankfurter')
        ->and($result->resolvedFrom)->toBe('provider');

    expect(
        ExchangeRate::query()
            ->forPairOnDate('EUR', 'USD', '2026-04-09')
            ->where('source', 'frankfurter')
            ->exists()
    )->toBeTrue();
});

it('falls back to the secondary provider when the primary provider fails', function () {
    Http::fake([
        'https://frankfurter.test/*' => Http::response([
            'error' => 'upstream unavailable',
        ], 503),
        'https://2026-04-09.fawaz.test/*' => Http::response([
            'date' => '2026-04-09',
            'eur' => [
                'usd' => 1.0789,
            ],
        ], 200),
        'https://cdn.fawaz.test/*' => Http::response([], 500),
    ]);

    $result = app(ExchangeRateService::class)->resolve('EUR', 'USD', '2026-04-09');

    expect($result->source)->toBe('fawaz')
        ->and($result->rate)->toBe('1.07890000');

    expect(
        ExchangeRate::query()
            ->forPairOnDate('EUR', 'USD', '2026-04-09')
            ->where('source', 'fawaz')
            ->exists()
    )->toBeTrue();
});

it('reuses the locally stored exchange rate without external calls', function () {
    ExchangeRate::factory()->create([
        'base_currency_code' => 'EUR',
        'quote_currency_code' => 'GBP',
        'rate' => '0.85670000',
        'rate_date' => '2026-04-08',
        'source' => 'frankfurter',
        'fetched_at' => now()->subHour(),
    ]);

    Http::preventStrayRequests();

    $result = app(ExchangeRateService::class)->resolve('EUR', 'GBP', '2026-04-08');

    expect($result->rate)->toBe('0.85670000')
        ->and($result->source)->toBe('frankfurter')
        ->and($result->resolvedFrom)->toBe('database');
});

it('supports deterministic historical lookup for a specific date', function () {
    Http::fake([
        'https://frankfurter.test/*' => Http::response([
            [
                'date' => '2026-03-15',
                'base' => 'EUR',
                'quote' => 'CHF',
                'rate' => 0.954321,
            ],
        ], 200),
    ]);

    $result = app(ExchangeRateService::class)->resolve('EUR', 'CHF', '2026-03-15');

    expect($result->date->toDateString())->toBe('2026-03-15')
        ->and($result->rate)->toBe('0.95432100');
});

it('falls back to a live provider lookup for a newly used supported currency and persists it locally', function () {
    Http::fake([
        'https://frankfurter.test/*' => Http::response([
            [
                'date' => '2026-04-09',
                'base' => 'EUR',
                'quote' => 'JPY',
                'rate' => 163.42,
            ],
        ], 200),
    ]);

    expect(
        ExchangeRate::query()->forPairOnDate('EUR', 'JPY', '2026-04-09')->exists()
    )->toBeFalse();

    $result = app(ExchangeRateService::class)->resolve('EUR', 'JPY', '2026-04-09');

    expect($result->fromCurrencyCode)->toBe('EUR')
        ->and($result->toCurrencyCode)->toBe('JPY')
        ->and($result->rate)->toBe('163.42000000')
        ->and($result->resolvedFrom)->toBe('provider')
        ->and(
            ExchangeRate::query()
                ->forPairOnDate('EUR', 'JPY', '2026-04-09')
                ->where('source', 'frankfurter')
                ->exists()
        )->toBeTrue();
});

it('throws an explicit error when no provider can resolve the rate', function () {
    Http::fake([
        'https://frankfurter.test/*' => Http::response([], 500),
        'https://2026-04-09.fawaz.test/*' => Http::response([], 500),
        'https://cdn.fawaz.test/*' => Http::response([], 500),
    ]);

    expect(fn () => app(ExchangeRateService::class)->resolve('EUR', 'USD', '2026-04-09'))
        ->toThrow(ExchangeRateLookupException::class, 'Unable to resolve exchange rate EUR/USD for 2026-04-09');
});

it('rejects unsupported currencies and future dates before calling providers', function () {
    Http::preventStrayRequests();

    expect(fn () => app(ExchangeRateService::class)->resolve('EUR', 'ZZZ', '2026-04-09'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported currency code [ZZZ].');

    expect(fn () => app(ExchangeRateService::class)->resolve('EUR', 'USD', now()->addDay()->toDateString()))
        ->toThrow(InvalidArgumentException::class, 'Exchange rates are not available for future dates.');
});
