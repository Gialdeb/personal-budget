<?php

use App\Models\AccountType;
use App\Models\Bank;
use App\Models\User;
use App\Models\UserBank;
use App\Models\UserSetting;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('banks page shows only user available banks and remaining catalog options', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $settings = UserSetting::query()->create([
        'user_id' => $user->id,
        'active_year' => 2026,
        'base_currency' => 'EUR',
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
            ->where('auth.user.uuid', $user->uuid)
            ->missing('auth.user.id')
            ->where('auth.user.settings.uuid', $settings->uuid)
            ->missing('auth.user.settings.id')
            ->where('banks.summary.total_count', 2)
            ->where('banks.summary.custom_count', 1)
            ->where('banks.summary.catalog_count', 1)
            ->where('banks.data.0.uuid', fn (string $uuid) => Str::isUuid($uuid))
            ->missing('banks.data.0.id')
            ->where('banks.data.0.source_label', fn (string $value) => in_array($value, ['Globale', 'Personalizzata'], true))
            ->where('catalog.available.0.uuid', $remainingCatalogBank->uuid)
            ->missing('catalog.available.0.id')
            ->where('catalog.available.0.name', $remainingCatalogBank->name),
        );
});

test('catalog banks can be attached using public bank uuid', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => 'asset',
    ]);

    $catalogBank = Bank::query()->create([
        'name' => 'Banco Tre',
        'slug' => 'banco-tre',
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('banks.store'), [
            'mode' => 'catalog',
            'bank_uuid' => $catalogBank->uuid,
            'is_active' => true,
            'create_base_account' => false,
        ])
        ->assertRedirect(route('banks.edit'));

    $this->assertDatabaseHas('user_banks', [
        'user_id' => $user->id,
        'bank_id' => $catalogBank->id,
        'name' => $catalogBank->name,
    ]);
});
