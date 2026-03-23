<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

uses(RefreshDatabase::class);

test('new users default to eur as base currency', function () {
    $user = User::factory()->create()->fresh();

    expect($user->base_currency_code)->toBe('EUR');
});

test('new users default to it-IT as format locale', function () {
    $user = User::factory()->create()->fresh();

    expect($user->format_locale)->toBe('it-IT');
});

test('accounts table exposes a currency code column', function () {
    expect(Schema::hasColumn('accounts', 'currency_code'))->toBeTrue();
});
