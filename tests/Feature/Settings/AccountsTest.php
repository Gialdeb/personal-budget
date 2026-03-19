<?php

use App\Models\AccountType;
use App\Models\Bank;
use App\Models\User;
use App\Models\UserBank;
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

    $bank = Bank::query()->create([
        'name' => 'Banca demo',
        'slug' => 'banca-demo',
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    UserBank::query()->create([
        'user_id' => $user->id,
        'bank_id' => $bank->id,
        'name' => $bank->name,
        'slug' => $bank->slug,
        'is_custom' => false,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Accounts')
            ->where('options.banks.0.name', 'Banca demo'),
        );
});
