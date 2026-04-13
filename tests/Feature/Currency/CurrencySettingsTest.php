<?php

use App\Models\RecurringEntry;
use App\Models\User;
use App\Services\Accounts\AccountProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user with only the default cash account and no transactions can change base currency', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();

    $account = app(AccountProvisioningService::class)->ensureDefaultCashAccount($user);

    $this->actingAs($user)
        ->patch(route('settings.profile.update-currency'), [
            'base_currency_code' => 'USD',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->fresh()->base_currency_code)->toBe('USD')
        ->and($account->fresh()->currency)->toBe('USD')
        ->and($account->fresh()->currency_code)->toBe('USD');
});

test('user with non-default accounts but no transactions can still change base currency', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();

    $account = userAccount($user, ['currency_code' => 'EUR']);

    $this->actingAs($user)
        ->patch(route('settings.profile.update-currency'), [
            'base_currency_code' => 'USD',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->fresh()->base_currency_code)->toBe('USD')
        ->and($account->fresh()->currency)->toBe('EUR')
        ->and($account->fresh()->currency_code)->toBe('EUR');
});

test('base currency change only syncs bootstrap cash account when real accounts exist', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();

    $cashAccount = app(AccountProvisioningService::class)->ensureDefaultCashAccount($user);
    $realAccount = userAccount($user, ['currency_code' => 'EUR']);

    $this->actingAs($user)
        ->patch(route('settings.profile.update-currency'), [
            'base_currency_code' => 'USD',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->fresh()->base_currency_code)->toBe('USD')
        ->and($cashAccount->fresh()->currency)->toBe('USD')
        ->and($cashAccount->fresh()->currency_code)->toBe('USD')
        ->and($realAccount->fresh()->currency)->toBe('EUR')
        ->and($realAccount->fresh()->currency_code)->toBe('EUR');
});

test('base currency change does not update bootstrap cash account with recurring entries', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();

    $account = app(AccountProvisioningService::class)->ensureDefaultCashAccount($user);

    RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'title' => 'Bootstrap recurring entry',
        'direction' => 'expense',
        'expected_amount' => '19.90',
        'currency' => 'EUR',
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

    $this->actingAs($user)
        ->patch(route('settings.profile.update-currency'), [
            'base_currency_code' => 'USD',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->fresh()->base_currency_code)->toBe('USD')
        ->and($account->fresh()->currency)->toBe('EUR')
        ->and($account->fresh()->currency_code)->toBe('EUR');
});

test('user with transactions cannot change base currency', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();

    $account = userAccount($user, ['currency_code' => 'EUR']);

    userTransaction($user, $account, [
        'amount' => '100.00',
        'type' => 'expense',
    ]);

    $this->actingAs($user)
        ->patch(route('settings.profile.update-currency'), [
            'base_currency_code' => 'USD',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('base_currency_code');

    expect($user->fresh()->base_currency_code)->toBe('EUR')
        ->and($account->fresh()->currency)->toBe('EUR')
        ->and($account->fresh()->currency_code)->toBe('EUR');
});
