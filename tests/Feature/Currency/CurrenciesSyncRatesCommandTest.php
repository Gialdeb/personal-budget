<?php

use App\Models\ExchangeRate;
use App\Models\RecurringEntry;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('currencies.providers.frankfurter.base_url', 'https://frankfurter.test/v2');
    config()->set('currencies.providers.fawaz.pages_dev_template', 'https://%s.fawaz.test');
    config()->set('currencies.providers.fawaz.jsdelivr_base_url', 'https://cdn.fawaz.test/currency-api');
});

it('syncs the configured daily rates through the artisan command', function () {
    Http::fake([
        'https://frankfurter.test/*' => Http::sequence()
            ->push([
                [
                    'date' => '2026-04-09',
                    'base' => 'EUR',
                    'quote' => 'USD',
                    'rate' => 1.0831,
                ],
            ], 200)
            ->push([
                [
                    'date' => '2026-04-09',
                    'base' => 'EUR',
                    'quote' => 'GBP',
                    'rate' => 0.8564,
                ],
            ], 200),
    ]);

    $this->artisan('currencies:sync-rates', [
        '--date' => '2026-04-09',
        '--base' => 'EUR',
        '--currencies' => 'USD,GBP',
    ])
        ->expectsOutputToContain('Currency sync for 2026-04-09 completed.')
        ->expectsOutputToContain('frankfurter')
        ->assertExitCode(0);

    expect(
        ExchangeRate::query()->forPairOnDate('EUR', 'USD', '2026-04-09')->exists()
    )->toBeTrue()
        ->and(
            ExchangeRate::query()->forPairOnDate('EUR', 'GBP', '2026-04-09')->exists()
        )->toBeTrue();
});

it('syncs all relevant currency pairs for the day and avoids duplicate persisted rates', function () {
    config()->set('currencies.sync.base_currency_code', 'EUR');
    config()->set('currencies.sync.core_currency_codes', ['EUR', 'USD']);
    config()->set('currencies.sync.quote_currency_codes', []);

    $euroUser = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);
    $dollarUser = User::factory()->create([
        'base_currency_code' => 'USD',
    ]);

    $euroAccount = createTestAccount($euroUser, [
        'currency' => 'GBP',
        'currency_code' => 'GBP',
    ]);
    $dollarAccount = createTestAccount($dollarUser, [
        'currency' => 'CHF',
        'currency_code' => 'CHF',
    ]);

    userTransaction($euroUser, $euroAccount, [
        'currency' => 'AUD',
        'currency_code' => 'AUD',
        'base_currency_code' => 'EUR',
    ]);

    RecurringEntry::query()->create([
        'user_id' => $dollarUser->id,
        'account_id' => $dollarAccount->id,
        'title' => 'FX recurring',
        'direction' => 'expense',
        'expected_amount' => '15.00',
        'currency' => 'CAD',
        'entry_type' => 'recurring',
        'status' => 'active',
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'start_date' => '2026-04-09',
        'next_occurrence_date' => '2026-04-09',
        'end_mode' => 'never',
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
    ]);

    Http::fake([
        'https://frankfurter.test/*' => function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

            $base = strtoupper((string) ($query['base'] ?? ''));
            $quote = strtoupper((string) ($query['quotes'] ?? ''));
            $rateMap = [
                'EUR:AUD' => 1.65,
                'EUR:CAD' => 1.47,
                'EUR:CHF' => 0.97,
                'EUR:GBP' => 0.85,
                'EUR:USD' => 1.08,
                'USD:AUD' => 1.52,
                'USD:CAD' => 1.35,
                'USD:CHF' => 0.89,
                'USD:EUR' => 0.92,
                'USD:GBP' => 0.79,
            ];

            return Http::response([[
                'date' => '2026-04-09',
                'base' => $base,
                'quote' => $quote,
                'rate' => $rateMap["{$base}:{$quote}"] ?? 1.0,
            ]], 200);
        },
    ]);

    $this->artisan('currencies:sync-rates', [
        '--date' => '2026-04-09',
    ])->assertExitCode(0);

    expect(ExchangeRate::query()->count())->toBe(10)
        ->and(ExchangeRate::query()->forPairOnDate('EUR', 'GBP', '2026-04-09')->exists())->toBeTrue()
        ->and(ExchangeRate::query()->forPairOnDate('EUR', 'CAD', '2026-04-09')->exists())->toBeTrue()
        ->and(ExchangeRate::query()->forPairOnDate('USD', 'EUR', '2026-04-09')->exists())->toBeTrue()
        ->and(ExchangeRate::query()->forPairOnDate('USD', 'CHF', '2026-04-09')->exists())->toBeTrue();

    $this->artisan('currencies:sync-rates', [
        '--date' => '2026-04-09',
    ])->assertExitCode(0);

    expect(ExchangeRate::query()->count())->toBe(10);
});

it('registers the daily exchange-rate sync command in the scheduler', function () {
    $scheduledCommandNames = collect(app(Schedule::class)->events())
        ->map(fn ($event): string => $event->command ?? '')
        ->filter();

    expect($scheduledCommandNames->contains(
        fn (string $command): bool => str_contains($command, 'currencies:sync-rates'),
    ))->toBeTrue();
});
