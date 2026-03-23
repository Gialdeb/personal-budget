<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user without accounts or transactions can change base currency', function () {
    $user = verifiedUser();
    $user->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();

    $this->actingAs($user)
        ->patch(route('settings.profile.update-currency'), [
            'base_currency_code' => 'USD',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->fresh()->base_currency_code)->toBe('USD');
});

test('user with accounts cannot change base currency', function () {
    $user = verifiedUser();
    $user->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();

    $account = userAccount($user, ['currency_code' => 'EUR']);

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

test('user with transactions cannot change base currency', function () {
    $user = verifiedUser();
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
