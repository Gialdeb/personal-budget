<?php

use App\Models\ExchangeRate;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('currencies.providers.frankfurter.base_url', 'https://frankfurter.test/v2');
    config()->set('currencies.providers.fawaz.pages_dev_template', 'https://%s.fawaz.test');
    config()->set('currencies.providers.fawaz.jsdelivr_base_url', 'https://cdn.fawaz.test/currency-api');
});

it('keeps the currency sync command scheduled daily', function () {
    $scheduledCommandNames = collect(app(Schedule::class)->events())
        ->map(fn ($event): string => $event->command ?? '')
        ->filter();

    expect($scheduledCommandNames->contains(
        fn (string $command): bool => str_contains($command, 'currencies:sync-rates'),
    ))->toBeTrue();
});

it('sends a telegram alert when the sync fails and telegram alerts are configured', function () {
    config()->set('currencies.alerts.telegram.enabled', true);
    config()->set('currencies.alerts.telegram.bot_token', 'fx-token');
    config()->set('currencies.alerts.telegram.chat_id', '123456');

    Http::fake([
        'https://frankfurter.test/*' => Http::response([], 500),
        'https://*.fawaz.test/*' => Http::response([], 500),
        'https://cdn.fawaz.test/*' => Http::response([], 500),
        'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $this->artisan('currencies:sync-rates', [
        '--date' => '2026-04-09',
        '--base' => 'EUR',
        '--currencies' => 'USD',
    ])->assertExitCode(1);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'api.telegram.org/botfx-token/sendMessage')
            && str_contains((string) data_get($request->data(), 'text'), 'currencies:sync-rates')
            && str_contains((string) data_get($request->data(), 'text'), 'USD');
    });

    expect(
        ExchangeRate::query()->forPairOnDate('EUR', 'USD', '2026-04-09')->exists()
    )->toBeFalse();
});

it('does not send a telegram alert when telegram is not configured', function () {
    config()->set('currencies.alerts.telegram.enabled', false);

    Http::fake([
        'https://frankfurter.test/*' => Http::response([], 500),
        'https://*.fawaz.test/*' => Http::response([], 500),
        'https://cdn.fawaz.test/*' => Http::response([], 500),
    ]);

    $this->artisan('currencies:sync-rates', [
        '--date' => '2026-04-09',
        '--base' => 'EUR',
        '--currencies' => 'USD',
    ])->assertExitCode(1);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.telegram.org/'));
});

it('does not send a telegram alert when the sync succeeds', function () {
    config()->set('currencies.alerts.telegram.enabled', true);
    config()->set('currencies.alerts.telegram.bot_token', 'fx-token');
    config()->set('currencies.alerts.telegram.chat_id', '123456');

    Http::fake([
        'https://frankfurter.test/*' => Http::response([
            [
                'date' => '2026-04-09',
                'base' => 'EUR',
                'quote' => 'USD',
                'rate' => 1.0831,
            ],
        ], 200),
    ]);

    $this->artisan('currencies:sync-rates', [
        '--date' => '2026-04-09',
        '--base' => 'EUR',
        '--currencies' => 'USD',
    ])->assertExitCode(0);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.telegram.org/'));
});
