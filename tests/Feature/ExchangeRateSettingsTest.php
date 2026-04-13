<?php

use App\Models\ExchangeRate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the settings exchange-rates page with persisted database rows', function () {
    $user = User::factory()->create();

    ExchangeRate::query()->create([
        'rate_date' => '2026-04-10',
        'base_currency_code' => 'EUR',
        'quote_currency_code' => 'USD',
        'rate' => '1.08310000',
        'source' => 'frankfurter',
        'fetched_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('exchange-rates.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ExchangeRates')
            ->where('filters.rate_date', null)
            ->has('exchange_rates.data', 1)
            ->where('exchange_rates.data.0.base_currency_code', 'EUR')
            ->where('exchange_rates.data.0.quote_currency_code', 'USD')
            ->where('exchange_rates.data.0.source.label', 'Frankfurter')
            ->where('exchange_rates.data.0.source.url', 'https://frankfurter.dev/'));
});

it('filters persisted exchange rates by date and currency pair', function () {
    $user = User::factory()->create();

    ExchangeRate::factory()->create([
        'rate_date' => '2026-04-10',
        'base_currency_code' => 'EUR',
        'quote_currency_code' => 'USD',
        'source' => 'frankfurter',
    ]);

    ExchangeRate::factory()->create([
        'rate_date' => '2026-04-09',
        'base_currency_code' => 'EUR',
        'quote_currency_code' => 'GBP',
        'source' => 'fawaz',
    ]);

    $this->actingAs($user)
        ->get(route('exchange-rates.edit', [
            'rate_date' => '2026-04-09',
            'base_currency_code' => 'EUR',
            'quote_currency_code' => 'GBP',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ExchangeRates')
            ->where('filters.rate_date', '2026-04-09')
            ->where('filters.base_currency_code', 'EUR')
            ->where('filters.quote_currency_code', 'GBP')
            ->has('exchange_rates.data', 1)
            ->where('exchange_rates.data.0.quote_currency_code', 'GBP')
            ->where('exchange_rates.data.0.source.label', 'Fawaz exchange-api')
            ->where('exchange_rates.data.0.source.url', 'https://github.com/fawazahmed0/exchange-api'));
});

it('shows an empty exchange-rates state when no persisted row matches the filters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('exchange-rates.edit', [
            'rate_date' => '2026-04-10',
            'base_currency_code' => 'EUR',
            'quote_currency_code' => 'USD',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ExchangeRates')
            ->has('exchange_rates.data', 0));
});
