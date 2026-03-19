<?php

use App\Models\AccountType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('accounts page is displayed', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => 'asset',
    ]);

    $this->actingAs($user)
        ->get(route('accounts.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Accounts'),
        );
});
