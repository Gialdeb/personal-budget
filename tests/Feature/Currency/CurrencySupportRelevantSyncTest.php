<?php

use App\Models\RecurringEntry;
use App\Models\User;
use App\Supports\Currency\CurrencySupport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds the relevant sync currency sets from configured core, active users, accounts, transactions, and recurring entries', function () {
    config()->set('currencies.sync.base_currency_code', 'EUR');
    config()->set('currencies.sync.core_currency_codes', ['EUR', 'USD', 'GBP', 'CHF']);
    config()->set('currencies.sync.quote_currency_codes', []);

    $activeEuroUser = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);
    $activeDollarUser = User::factory()->create([
        'base_currency_code' => 'USD',
    ]);
    $suspendedUser = User::factory()->create([
        'base_currency_code' => 'JPY',
    ]);
    $suspendedUser->forceFill(['status' => 'suspended'])->save();

    $euroAccount = createTestAccount($activeEuroUser, [
        'currency' => 'GBP',
        'currency_code' => 'GBP',
    ]);

    userTransaction($activeEuroUser, $euroAccount, [
        'currency' => 'AUD',
        'currency_code' => 'AUD',
        'base_currency_code' => 'EUR',
    ]);

    RecurringEntry::query()->create([
        'user_id' => $activeDollarUser->id,
        'account_id' => createTestAccount($activeDollarUser, [
            'currency' => 'CHF',
            'currency_code' => 'CHF',
        ])->id,
        'title' => 'FX recurring entry',
        'direction' => 'expense',
        'expected_amount' => '19.90',
        'currency' => 'CAD',
        'entry_type' => 'recurring',
        'status' => 'active',
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'start_date' => '2026-04-10',
        'next_occurrence_date' => '2026-04-10',
        'end_mode' => 'never',
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
    ]);

    $support = app(CurrencySupport::class);

    expect($support->relevantSyncBaseCurrencyCodes())
        ->toBe(['EUR', 'USD'])
        ->and($support->relevantSyncCurrencyCodes())
        ->toBe(['AUD', 'CAD', 'CHF', 'EUR', 'GBP', 'USD'])
        ->and($support->relevantSyncQuoteCurrencyCodes('EUR'))
        ->toBe(['AUD', 'CAD', 'CHF', 'GBP', 'USD'])
        ->and($support->relevantSyncPlans())
        ->toBe([
            [
                'base_currency_code' => 'EUR',
                'quote_currency_codes' => ['AUD', 'CAD', 'CHF', 'GBP', 'USD'],
            ],
            [
                'base_currency_code' => 'USD',
                'quote_currency_codes' => ['AUD', 'CAD', 'CHF', 'EUR', 'GBP'],
            ],
        ]);
});
