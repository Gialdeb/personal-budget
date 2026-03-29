<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user with only the default cash account and no transactions can change base currency', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'base_currency_code' => 'EUR',
    ])->save();

    $account = userAccount($user, [
        'name' => 'Cassa contanti',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

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
        ->and($account->fresh()->currency)->toBe('USD')
        ->and($account->fresh()->currency_code)->toBe('USD');
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
