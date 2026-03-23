<?php

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('account creation inherits the user base currency', function () {
    $user = verifiedUser();
    $user->forceFill([
        'base_currency_code' => 'USD',
    ])->save();

    $this->actingAs($user)
        ->post(route('accounts.store'), [
            'name' => 'US Account',
            'account_type_uuid' => accountTypeUuidFor('payment_account'),
            'bank_uuid' => null,
            'is_active' => true,
            'allow_negative_balance' => false,
            'currency' => 'GBP',
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

test('account update ignores currency code sent by the client', function () {
    $user = verifiedUser();
    $user->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();

    $account = userAccount($user, [
        'currency_code' => 'EUR',
    ]);

    $this->actingAs($user)
        ->patch(route('accounts.update', $account->uuid), [
            'name' => 'Updated account',
            'account_type_uuid' => $account->accountType->uuid,
            'bank_uuid' => $account->bank?->uuid,
            'is_active' => true,
            'allow_negative_balance' => false,
            'currency' => 'USD',
            'currency_code' => 'GBP',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($account->fresh()->currency_code)->toBe('EUR')
        ->and($account->fresh()->currency)->toBe('EUR');
});
