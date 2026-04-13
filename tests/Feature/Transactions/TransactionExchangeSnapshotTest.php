<?php

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

it('stores an identity exchange snapshot when the account currency matches the user base currency', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    prepareTransactionExchangeContext($user);

    $account = userAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);
    $category = expenseCategoryForUser($user);

    $this->actingAs($user)
        ->post(route('transactions.store', ['year' => 2025, 'month' => 3]), transactionStorePayload($account, $category, [
            'amount' => 32.40,
            'description' => 'FX identity transaction',
        ]))
        ->assertStatus(302);

    $transaction = Transaction::query()
        ->where('description', 'FX identity transaction')
        ->firstOrFail();

    expect($transaction->currency)->toBe('EUR')
        ->and($transaction->currency_code)->toBe('EUR')
        ->and($transaction->base_currency_code)->toBe('EUR')
        ->and($transaction->exchange_rate)->toBe('1.00000000')
        ->and($transaction->exchange_rate_date?->toDateString())->toBe('2025-03-22')
        ->and($transaction->converted_base_amount)->toBe('32.40')
        ->and($transaction->exchange_rate_source)->toBe('identity');
});

it('stores a historical exchange snapshot for a transaction in a different account currency', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    prepareTransactionExchangeContext($user);

    $account = userAccount($user, [
        'currency' => 'USD',
        'currency_code' => 'USD',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);
    $category = expenseCategoryForUser($user);

    ExchangeRate::factory()->create([
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'EUR',
        'rate' => '0.92000000',
        'rate_date' => '2025-03-22',
        'source' => 'frankfurter',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store', ['year' => 2025, 'month' => 3]), transactionStorePayload($account, $category, [
            'amount' => 10,
            'description' => 'FX usd transaction',
        ]))
        ->assertStatus(302);

    $transaction = Transaction::query()
        ->where('description', 'FX usd transaction')
        ->firstOrFail();

    expect($transaction->currency)->toBe('USD')
        ->and($transaction->currency_code)->toBe('USD')
        ->and($transaction->base_currency_code)->toBe('EUR')
        ->and($transaction->exchange_rate)->toBe('0.92000000')
        ->and($transaction->exchange_rate_date?->toDateString())->toBe('2025-03-22')
        ->and($transaction->converted_base_amount)->toBe('9.20')
        ->and($transaction->exchange_rate_source)->toBe('frankfurter');
});

it('refreshes the exchange snapshot when an updated transaction changes account date or amount', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    prepareTransactionExchangeContext($user);

    $usdAccount = userAccount($user, [
        'name' => 'USD account',
        'currency' => 'USD',
        'currency_code' => 'USD',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);
    $gbpAccount = userAccount($user, [
        'name' => 'GBP account',
        'currency' => 'GBP',
        'currency_code' => 'GBP',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);
    $category = expenseCategoryForUser($user);

    ExchangeRate::factory()->create([
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'EUR',
        'rate' => '0.91000000',
        'rate_date' => '2025-03-22',
        'source' => 'frankfurter',
    ]);
    ExchangeRate::factory()->create([
        'base_currency_code' => 'GBP',
        'quote_currency_code' => 'EUR',
        'rate' => '1.17000000',
        'rate_date' => '2025-03-24',
        'source' => 'fawaz',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store', ['year' => 2025, 'month' => 3]), transactionStorePayload($usdAccount, $category, [
            'amount' => 10,
            'description' => 'FX update me',
        ]))
        ->assertStatus(302);

    $transaction = Transaction::query()
        ->where('description', 'FX update me')
        ->firstOrFail();

    $this->actingAs($user)
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_day' => 24,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $gbpAccount->id,
            'category_id' => $category->id,
            'amount' => 20,
            'description' => 'FX updated transaction',
            'notes' => 'Updated snapshot',
        ])
        ->assertStatus(302);

    $transaction->refresh();

    expect($transaction->account_id)->toBe($gbpAccount->id)
        ->and($transaction->currency_code)->toBe('GBP')
        ->and($transaction->base_currency_code)->toBe('EUR')
        ->and($transaction->exchange_rate)->toBe('1.17000000')
        ->and($transaction->exchange_rate_date?->toDateString())->toBe('2025-03-24')
        ->and($transaction->converted_base_amount)->toBe('23.40')
        ->and($transaction->exchange_rate_source)->toBe('fawaz');
});

it('does not dynamically recalculate the saved exchange snapshot for existing transactions', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    prepareTransactionExchangeContext($user);

    $account = userAccount($user, [
        'currency' => 'USD',
        'currency_code' => 'USD',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);
    $category = expenseCategoryForUser($user);

    ExchangeRate::factory()->create([
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'EUR',
        'rate' => '0.93000000',
        'rate_date' => '2025-03-22',
        'source' => 'frankfurter',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store', ['year' => 2025, 'month' => 3]), transactionStorePayload($account, $category, [
            'amount' => 10,
            'description' => 'FX frozen transaction',
        ]))
        ->assertStatus(302);

    $transaction = Transaction::query()
        ->where('description', 'FX frozen transaction')
        ->firstOrFail();

    DB::table('currency_exchange_rates')
        ->where('base_currency_code', 'USD')
        ->where('quote_currency_code', 'EUR')
        ->where('rate_date', '2025-03-22')
        ->update([
            'rate' => '0.99000000',
            'updated_at' => now(),
        ]);

    $transaction->refresh();

    expect($transaction->exchange_rate)->toBe('0.93000000')
        ->and($transaction->converted_base_amount)->toBe('9.30');
});

it('returns no fx preview when the account currency already matches the base currency', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    prepareTransactionExchangeContext($user);

    $account = userAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);

    $this->actingAs($user)
        ->postJson(route('transactions.exchange-preview', ['year' => 2025, 'month' => 3]), [
            'account_uuid' => $account->uuid,
            'transaction_day' => 22,
            'amount' => 25,
        ])
        ->assertSuccessful()
        ->assertJson([
            'currency_code' => 'EUR',
            'base_currency_code' => 'EUR',
            'exchange_rate' => '1.00000000',
            'exchange_rate_date' => '2025-03-22',
            'converted_base_amount_raw' => 25.0,
            'is_multi_currency' => false,
            'should_preview' => false,
        ]);
});

it('returns a stored fx preview payload for multi currency transactions', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    prepareTransactionExchangeContext($user);

    $account = userAccount($user, [
        'currency' => 'GBP',
        'currency_code' => 'GBP',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);

    ExchangeRate::factory()->create([
        'base_currency_code' => 'GBP',
        'quote_currency_code' => 'EUR',
        'rate' => '1.16000000',
        'rate_date' => '2025-03-22',
        'source' => 'frankfurter',
    ]);

    $this->actingAs($user)
        ->postJson(route('transactions.exchange-preview', ['year' => 2025, 'month' => 3]), [
            'account_uuid' => $account->uuid,
            'transaction_day' => 22,
            'amount' => 25,
        ])
        ->assertSuccessful()
        ->assertJson([
            'currency_code' => 'GBP',
            'base_currency_code' => 'EUR',
            'exchange_rate' => '1.16000000',
            'exchange_rate_date' => '2025-03-22',
            'converted_base_amount_raw' => 29.0,
            'exchange_rate_source' => 'frankfurter',
            'is_multi_currency' => true,
            'should_preview' => true,
        ]);
});

it('fails clearly when no historical exchange rate is available for a different currency transaction', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    prepareTransactionExchangeContext($user);

    $account = userAccount($user, [
        'currency' => 'USD',
        'currency_code' => 'USD',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);
    $category = expenseCategoryForUser($user);

    Http::preventStrayRequests();

    $this->actingAs($user)
        ->from(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->post(route('transactions.store', ['year' => 2025, 'month' => 3]), transactionStorePayload($account, $category, [
            'amount' => 11,
            'description' => 'FX failing transaction',
        ]))
        ->assertRedirect(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->assertSessionHasErrors([
            'transaction_date' => __('transactions.validation.exchange_rate_unavailable', [
                'from' => 'USD',
                'to' => 'EUR',
                'date' => '2025-03-22',
            ]),
        ]);

    expect(Transaction::query()->where('description', 'FX failing transaction')->exists())->toBeFalse();
});

it('fails preview clearly when no historical exchange rate is available', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    prepareTransactionExchangeContext($user);

    $account = userAccount($user, [
        'currency' => 'USD',
        'currency_code' => 'USD',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);

    Http::preventStrayRequests();

    $this->actingAs($user)
        ->postJson(route('transactions.exchange-preview', ['year' => 2025, 'month' => 3]), [
            'account_uuid' => $account->uuid,
            'transaction_day' => 22,
            'amount' => 11,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'transaction_date' => __('transactions.validation.exchange_rate_unavailable', [
                'from' => 'USD',
                'to' => 'EUR',
                'date' => '2025-03-22',
            ]),
        ]);
});

it('exposes fx snapshot fields in the monthly transaction sheet payload', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    prepareTransactionExchangeContext($user);

    $account = userAccount($user, [
        'currency' => 'USD',
        'currency_code' => 'USD',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);
    $category = expenseCategoryForUser($user);

    ExchangeRate::factory()->create([
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'EUR',
        'rate' => '0.94000000',
        'rate_date' => '2025-03-22',
        'source' => 'frankfurter',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store', ['year' => 2025, 'month' => 3]), transactionStorePayload($account, $category, [
            'amount' => 12,
            'description' => 'FX payload transaction',
        ]))
        ->assertRedirect();

    $this->actingAs($user)
        ->getJson(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->assertSuccessful()
        ->assertJsonPath('transactions.0.currency_code', 'USD')
        ->assertJsonPath('transactions.0.base_currency_code', 'EUR')
        ->assertJsonPath('transactions.0.exchange_rate', '0.94000000')
        ->assertJsonPath('transactions.0.exchange_rate_date', '2025-03-22')
        ->assertJsonPath('transactions.0.converted_base_amount_raw', 11.28)
        ->assertJsonPath('transactions.0.is_multi_currency', true);
});

it('keeps legacy inserts compatible because the exchange snapshot columns stay nullable', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    $account = userAccount($user, [
        'currency' => 'USD',
        'currency_code' => 'USD',
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);

    $transactionId = DB::table('transactions')->insertGetId([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2025-03-22',
        'value_date' => '2025-03-22',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => '18.50',
        'currency' => 'USD',
        'description' => 'Legacy insert transaction',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $transaction = Transaction::query()->findOrFail($transactionId);

    expect($transaction->currency)->toBe('USD')
        ->and($transaction->currency_code)->toBeNull()
        ->and($transaction->base_currency_code)->toBeNull()
        ->and($transaction->exchange_rate)->toBeNull()
        ->and($transaction->converted_base_amount)->toBeNull();
});

function prepareTransactionExchangeContext(User $user, int $year = 2025): void
{
    UserYear::query()->updateOrCreate([
        'user_id' => $user->id,
        'year' => $year,
    ], [
        'is_closed' => false,
    ]);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => $year,
        'base_currency' => $user->base_currency_code,
    ]);
}

function expenseCategoryForUser(User $user): Category
{
    return Category::query()->create([
        'user_id' => $user->id,
        'name' => 'FX expenses',
        'slug' => 'fx-expenses-'.$user->id.'-'.str()->lower(str()->random(6)),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
}

/**
 * @return array<string, mixed>
 */
function transactionStorePayload(Account $account, Category $category, array $overrides = []): array
{
    return [
        'transaction_day' => 22,
        'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
        'account_uuid' => $account->uuid,
        'category_uuid' => $category->uuid,
        'amount' => 10,
        'description' => 'FX transaction',
        'notes' => 'FX notes',
        ...$overrides,
    ];
}
