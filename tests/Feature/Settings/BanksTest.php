<?php

use App\Models\AccountType;
use App\Models\Bank;
use App\Models\User;
use App\Models\UserBank;
use Inertia\Testing\AssertableInertia as Assert;

test('banks page shows only user available banks and remaining catalog options', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => 'asset',
    ]);

    $catalogBank = Bank::query()->create([
        'name' => 'Banco Uno',
        'slug' => 'banco-uno',
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    $remainingCatalogBank = Bank::query()->create([
        'name' => 'Banco Due',
        'slug' => 'banco-due',
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    UserBank::query()->create([
        'user_id' => $user->id,
        'bank_id' => $catalogBank->id,
        'name' => $catalogBank->name,
        'slug' => $catalogBank->slug,
        'is_custom' => false,
        'is_active' => true,
    ]);

    UserBank::query()->create([
        'user_id' => $user->id,
        'bank_id' => null,
        'name' => 'Cassa circolo',
        'slug' => 'cassa-circolo',
        'is_custom' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('banks.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Banks')
            ->where('banks.summary.total_count', 2)
            ->where('banks.summary.custom_count', 1)
            ->where('banks.summary.catalog_count', 1)
            ->where('banks.data.0.source_label', fn (string $value) => in_array($value, ['Globale', 'Personalizzata'], true))
            ->where('catalog.available.0.name', $remainingCatalogBank->name),
        );
});
