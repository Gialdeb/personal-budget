<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Scope;
use App\Models\User;
use App\Models\UserBank;
use Illuminate\Support\Str;
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

    $userBank = UserBank::query()->create([
        'user_id' => $user->id,
        'bank_id' => $bank->id,
        'name' => $bank->name,
        'slug' => $bank->slug,
        'is_custom' => false,
        'is_active' => true,
    ]);
    $accountType = AccountType::query()->first();
    $scope = Scope::query()->create([
        'user_id' => $user->id,
        'name' => 'Famiglia',
        'is_active' => true,
    ]);
    Account::query()->create([
        'user_id' => $user->id,
        'bank_id' => $bank->id,
        'user_bank_id' => $userBank->id,
        'account_type_id' => $accountType->id,
        'scope_id' => $scope->id,
        'name' => 'Conto demo',
        'currency' => 'EUR',
        'opening_balance' => 10,
        'current_balance' => 10,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Accounts')
            ->where('accounts.data.0.uuid', fn (string $uuid) => Str::isUuid($uuid))
            ->missing('accounts.data.0.id')
            ->where('options.banks.0.uuid', fn (string $uuid) => Str::isUuid($uuid))
            ->missing('options.banks.0.id')
            ->where('options.banks.0.name', 'Banca demo'),
        );
});

test('accounts can be created using public uuids for related entities', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $accountType = AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => 'asset',
    ]);

    $bank = Bank::query()->create([
        'name' => 'Banca operativa',
        'slug' => 'banca-operativa',
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    $userBank = UserBank::query()->create([
        'user_id' => $user->id,
        'bank_id' => $bank->id,
        'name' => $bank->name,
        'slug' => $bank->slug,
        'is_custom' => false,
        'is_active' => true,
    ]);

    $scope = Scope::query()->create([
        'user_id' => $user->id,
        'name' => 'Privato',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('accounts.store'), [
            'name' => 'Conto con UUID',
            'user_bank_uuid' => $userBank->uuid,
            'account_type_uuid' => $accountType->uuid,
            'scope_uuid' => $scope->uuid,
            'currency' => 'EUR',
            'opening_balance' => 150,
            'current_balance' => 150,
            'is_manual' => true,
            'is_active' => true,
            'settings' => [],
        ])
        ->assertRedirect(route('accounts.edit'));

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'user_bank_id' => $userBank->id,
        'account_type_id' => $accountType->id,
        'scope_id' => $scope->id,
        'name' => 'Conto con UUID',
    ]);
});
