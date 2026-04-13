<?php

use App\Models\Account;
use App\Models\RecurringEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('account creation stores the selected currency from the catalog', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);

    $this->actingAs($user)
        ->post(route('accounts.store'), [
            'name' => 'US Account',
            'account_type_uuid' => accountTypeUuidFor('payment_account'),
            'user_bank_uuid' => null,
            'is_active' => true,
            'is_reported' => true,
            'is_default' => false,
            'settings' => [
                'allow_negative_balance' => false,
            ],
            'currency' => 'USD',
            'opening_balance' => '0.00',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $account = Account::query()
        ->where('user_id', $user->id)
        ->latest('id')
        ->firstOrFail();

    expect($account->currency_code)->toBe('USD')
        ->and($account->currency)->toBe('USD');
});

test('account currency can be updated when the account has no accounting data', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $account = userAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
    ]);

    $this->actingAs($user)
        ->patch(route('accounts.update', $account->uuid), [
            'name' => 'Updated account',
            'user_bank_uuid' => null,
            'account_type_uuid' => $account->accountType->uuid,
            'currency' => 'USD',
            'iban' => '',
            'account_number_masked' => '',
            'opening_balance' => '0.00',
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => '',
            'is_active' => true,
            'is_reported' => true,
            'is_default' => false,
            'notes' => '',
            'settings' => [
                'allow_negative_balance' => false,
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($account->fresh()->currency_code)->toBe('USD')
        ->and($account->fresh()->currency)->toBe('USD');
});

test('account currency cannot be updated after transactions exist', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $account = userAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
    ]);

    userTransaction($user, $account, [
        'amount' => '55.00',
        'type' => 'expense',
    ]);

    $this->actingAs($user)
        ->patch(route('accounts.update', $account->uuid), [
            'name' => 'Updated account',
            'user_bank_uuid' => null,
            'account_type_uuid' => $account->accountType->uuid,
            'currency' => 'USD',
            'iban' => '',
            'account_number_masked' => '',
            'opening_balance' => '0.00',
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => '',
            'is_active' => true,
            'is_reported' => true,
            'is_default' => false,
            'notes' => '',
            'settings' => [
                'allow_negative_balance' => false,
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('currency');

    expect($account->fresh()->currency_code)->toBe('EUR')
        ->and($account->fresh()->currency)->toBe('EUR');
});

test('account currency cannot be updated after recurring entries exist', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $account = userAccount($user, [
        'currency_code' => 'EUR',
        'currency' => 'EUR',
    ]);

    RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'title' => 'Subscription',
        'direction' => 'expense',
        'expected_amount' => 12.90,
        'currency' => 'EUR',
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'start_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('accounts.update', $account->uuid), [
            'name' => 'Updated account',
            'user_bank_uuid' => null,
            'account_type_uuid' => $account->accountType->uuid,
            'currency' => 'USD',
            'iban' => '',
            'account_number_masked' => '',
            'opening_balance' => '0.00',
            'opening_balance_direction' => 'positive',
            'opening_balance_date' => '',
            'is_active' => true,
            'is_reported' => true,
            'is_default' => false,
            'notes' => '',
            'settings' => [
                'allow_negative_balance' => false,
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('currency');

    expect($account->fresh()->currency_code)->toBe('EUR')
        ->and($account->fresh()->currency)->toBe('EUR');
});
