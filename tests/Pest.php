<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Scope;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function accountTypeUuidFor(string $code): string
{
    return AccountType::query()->firstOrCreate(
        ['code' => $code],
        [
            'name' => str($code)->replace('_', ' ')->title()->value(),
            'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
        ],
    )->uuid;
}

function userAccount(User $user, array $attributes = []): Account
{
    $accountTypeId = $attributes['account_type_id']
        ?? AccountType::query()->firstOrCreate(
            ['code' => 'payment_account'],
            [
                'name' => 'Conto di pagamento',
                'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
            ],
        )->id;

    return Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountTypeId,
        'name' => 'Account '.fake()->unique()->word(),
        'currency_code' => $user->base_currency_code,
        'currency' => $user->base_currency_code,
        'is_manual' => true,
        'is_active' => true,
        ...$attributes,
    ]);
}

function createTestAccount(User $user, array $attributes = []): Account
{
    $bankId = $attributes['bank_id']
        ?? Bank::query()->firstOrCreate(
            ['slug' => 'test-bank'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Test Bank',
                'country_code' => 'IT',
                'is_active' => true,
            ],
        )->id;

    $accountTypeId = $attributes['account_type_id']
        ?? AccountType::query()->firstOrCreate(
            ['code' => 'payment_account'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Conto di pagamento',
                'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
            ],
        )->id;

    $scopeId = $attributes['scope_id']
        ?? Scope::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'name' => 'Personal',
            ],
            [
                'is_active' => true,
            ],
        )->id;

    return Account::query()->create([
        'user_id' => $user->id,
        'bank_id' => $bankId,
        'account_type_id' => $accountTypeId,
        'scope_id' => $scopeId,
        'name' => 'Test account',
        'iban' => null,
        'account_number_masked' => '****1234',
        'currency' => $user->base_currency_code,
        'currency_code' => $user->base_currency_code,
        'opening_balance' => 0,
        'current_balance' => 0,
        'opening_balance_date' => now()->toDateString(),
        'is_manual' => true,
        'is_active' => true,
        'notes' => null,
        'settings' => null,
        'user_bank_id' => null,
        ...$attributes,
    ]);
}

function userTransaction(User $user, Account $account, array $attributes = []): Transaction
{
    return Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => '100.00',
        'currency' => $account->currency,
        'description' => 'Test transaction',
        'transaction_date' => now()->toDateString(),
        'value_date' => now()->toDateString(),
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        ...$attributes,
    ]);
}
