<?php

use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Scope;
use App\Models\User;
use App\Models\UserBank;
use App\Services\UserYearService;
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
            ->where('accounts.data.0.opening_balance_date', null)
            ->missing('accounts.data.0.id')
            ->where('options.banks.0.uuid', fn (string $uuid) => Str::isUuid($uuid))
            ->missing('options.banks.0.id')
            ->where('options.banks.0.name', 'Banca demo'),
        );
});

test('accounts page keeps owned accounts separate from shared accounts', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $accountType = AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => 'asset',
    ]);

    $ownedAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto personale',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'current_balance' => 120,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $sharedAccount = Account::query()->create([
        'user_id' => $owner->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto condiviso',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'current_balance' => 450,
        'is_manual' => true,
        'is_active' => true,
    ]);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'user_id' => $user->id,
        'household_id' => $sharedAccount->household_id,
        'role' => 'viewer',
        'status' => 'active',
        'granted_by_user_id' => $owner->id,
        'joined_at' => now(),
        'source' => 'invitation',
    ]);

    $this->actingAs($user)
        ->get(route('accounts.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Accounts')
            ->where('accounts.data', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['uuid'] === $ownedAccount->uuid)
                && ! collect($accounts)->contains(fn ($account) => $account['uuid'] === $sharedAccount->uuid))
            ->where('shared_accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['uuid'] === $sharedAccount->uuid
                    && Str::isUuid($account['membership_uuid'])
                    && $account['name'] === 'Conto condiviso'
                    && $account['membership_role'] === 'viewer'
                    && $account['membership_status'] === 'active'
                    && $account['can_leave'] === true)
                && ! collect($accounts)->contains(fn ($account) => $account['uuid'] === $ownedAccount->uuid)));
});

test('accounts can be created using public uuids for related entities', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    app(UserYearService::class)->ensureYearExists($user, 2026);

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
            'opening_balance_date' => '2026-01-08',
            'current_balance' => 150,
            'is_manual' => true,
            'is_active' => true,
            'settings' => [],
        ])
        ->assertRedirect(route('accounts.edit'));

    $account = Account::query()
        ->where('user_id', $user->id)
        ->where('name', 'Conto con UUID')
        ->firstOrFail();

    expect($account->user_bank_id)->toBe($userBank->id)
        ->and($account->account_type_id)->toBe($accountType->id)
        ->and($account->scope_id)->toBe($scope->id)
        ->and($account->opening_balance_date?->toDateString())->toBe('2026-01-08');
});
