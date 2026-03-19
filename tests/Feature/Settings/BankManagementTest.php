<?php

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\User;
use App\Models\UserBank;

function verifiedBankUser(): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
    ]);
}

function bankCsrfToken(): string
{
    return 'bank-test-token';
}

function makeUserBank(User $user, array $attributes = []): UserBank
{
    return UserBank::query()->create([
        'user_id' => $user->id,
        'bank_id' => null,
        'name' => 'Banca '.fake()->unique()->company(),
        'slug' => 'banca-'.fake()->unique()->slug(),
        'is_custom' => true,
        'is_active' => true,
        ...$attributes,
    ]);
}

test('user can create a custom bank', function () {
    $user = verifiedBankUser();

    $this
        ->withSession(['_token' => bankCsrfToken()])
        ->actingAs($user)
        ->post(route('banks.store'), [
            '_token' => bankCsrfToken(),
            'mode' => 'custom',
            'name' => 'Banca quartiere',
            'slug' => '',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('banks.edit'));

    $this->assertDatabaseHas('user_banks', [
        'user_id' => $user->id,
        'bank_id' => null,
        'name' => 'Banca quartiere',
        'slug' => 'banca-quartiere',
        'is_custom' => true,
        'is_active' => true,
    ]);
});

test('user can add a bank from catalog without duplicates', function () {
    $user = verifiedBankUser();
    $bank = Bank::query()->create([
        'name' => 'Revolut',
        'slug' => 'revolut',
        'country_code' => 'LT',
        'is_active' => true,
    ]);

    $payload = [
        '_token' => bankCsrfToken(),
        'mode' => 'catalog',
        'bank_id' => $bank->id,
        'is_active' => true,
    ];

    $this
        ->withSession(['_token' => bankCsrfToken()])
        ->actingAs($user)
        ->post(route('banks.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('banks.edit'));

    $this
        ->withSession(['_token' => bankCsrfToken()])
        ->actingAs($user)
        ->post(route('banks.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('banks.edit'));

    expect(
        UserBank::query()
            ->where('user_id', $user->id)
            ->where('bank_id', $bank->id)
            ->count()
    )->toBe(1);
});

test('user can update only a custom bank', function () {
    $user = verifiedBankUser();
    $customBank = makeUserBank($user, [
        'name' => 'Banca locale',
        'slug' => 'banca-locale',
    ]);

    $this
        ->withSession(['_token' => bankCsrfToken()])
        ->actingAs($user)
        ->patch(route('banks.update', $customBank), [
            '_token' => bankCsrfToken(),
            'name' => 'Banca locale aggiornata',
            'slug' => '',
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('banks.edit'));

    $this->assertDatabaseHas('user_banks', [
        'id' => $customBank->id,
        'name' => 'Banca locale aggiornata',
        'slug' => 'banca-locale-aggiornata',
        'is_active' => false,
    ]);
});

test('user can toggle active state of available bank', function () {
    $user = verifiedBankUser();
    $userBank = makeUserBank($user, ['is_active' => true]);

    $this
        ->actingAs($user)
        ->patch(route('banks.toggle-active', $userBank))
        ->assertRedirect(route('banks.edit'));

    expect($userBank->fresh()->is_active)->toBeFalse();
});

test('used user bank cannot be deleted but unused custom bank can', function () {
    $user = verifiedBankUser();
    $accountType = AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => 'asset',
    ]);

    $usedUserBank = makeUserBank($user, [
        'name' => 'Banca usata',
        'slug' => 'banca-usata',
    ]);

    Account::query()->create([
        'user_id' => $user->id,
        'user_bank_id' => $usedUserBank->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto collegato',
        'currency' => 'EUR',
        'is_manual' => true,
        'is_active' => true,
    ]);

    $this
        ->withSession(['_token' => bankCsrfToken()])
        ->actingAs($user)
        ->from(route('banks.edit'))
        ->delete(route('banks.destroy', $usedUserBank), [
            '_token' => bankCsrfToken(),
        ])
        ->assertSessionHasErrors('delete')
        ->assertRedirect(route('banks.edit'));

    expect($usedUserBank->fresh())->not->toBeNull();

    $unusedUserBank = makeUserBank($user, [
        'name' => 'Banca archivio',
        'slug' => 'banca-archivio',
    ]);

    $this
        ->withSession(['_token' => bankCsrfToken()])
        ->actingAs($user)
        ->delete(route('banks.destroy', $unusedUserBank), [
            '_token' => bankCsrfToken(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('banks.edit'));

    $this->assertDatabaseMissing('user_banks', [
        'id' => $unusedUserBank->id,
    ]);
});
