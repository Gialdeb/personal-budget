<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from transactions pages', function () {
    $this->get(route('transactions.index'))
        ->assertRedirect(route('login'));
});

test('transactions index follows the active management year instead of the real calendar date', function () {
    $this->travelTo(now()->setDate(2026, 3, 19));

    $user = User::factory()->create();

    seedTransactionsFixture($user);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 5,
        ]));
});

test('transactions month page renders the shared monthly navigation widget and placeholder shell', function () {
    $user = User::factory()->create();

    seedTransactionsFixture($user);

    $response = $this->actingAs($user)->get(route('transactions.show', [
        'year' => 2025,
        'month' => 3,
    ]));

    $response
        ->assertSuccessful()
        ->assertSessionHas('dashboard_year', 2025)
        ->assertSessionHas('dashboard_month', 3)
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Show')
            ->where('transactionsPage.year', 2025)
            ->where('transactionsPage.month', 3)
            ->where('transactionsPage.records_count', 2)
            ->where('transactionsNavigation.context.year', 2025)
            ->where('transactionsNavigation.context.month', 3)
            ->where('transactionsNavigation.context.period_label', 'marzo 2025')
            ->where('transactionsNavigation.summary.records_count', 2)
            ->where('transactionsNavigation.summary.coverage_months_count', 1)
            ->where('transactionsNavigation.summary.last_recorded_at', '2025-03-18')
            ->where('transactionsNavigation.months', fn ($months) => collect($months)
                ->contains(fn ($month) => $month['value'] === 3
                    && $month['is_selected'] === true
                    && $month['has_data'] === true))
            ->where('transactionsNavigation.months', fn ($months) => collect($months)
                ->contains(fn ($month) => $month['value'] === 5
                    && $month['is_selected'] === false
                    && $month['has_data'] === false)),
        );

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'active_year' => 2025,
    ]);
});

test('transaction navigation limits annual coverage and latest date to today for the current year', function () {
    $this->travelTo(now()->setDate(2026, 3, 19));

    $user = User::factory()->create();

    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => 2026,
        'base_currency' => 'EUR',
    ]);

    $accountType = AccountType::query()->create([
        'code' => 'checking-current-year',
        'name' => 'Checking current year',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto anno corrente',
        'currency' => 'EUR',
        'opening_balance' => 1000,
        'current_balance' => 1300,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese anno corrente',
        'slug' => 'spese-anno-corrente',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    createTransactionForNavigation($user, $account, $category, 50, '2026-01-12');
    createTransactionForNavigation($user, $account, $category, 80, '2026-03-10');
    createTransactionForNavigation($user, $account, $category, 95, '2026-05-01');

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2026,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactionsNavigation.context.year', 2026)
            ->where('transactionsNavigation.context.month', null)
            ->where('transactionsNavigation.summary.records_count', 2)
            ->where('transactionsNavigation.summary.coverage_months_count', 2)
            ->where('transactionsNavigation.summary.coverage_total_months', 3)
            ->where('transactionsNavigation.summary.last_recorded_at', '2026-03-10')
            ->where('transactionsNavigation.summary.period_end_at', '2026-03-19'));
});

function seedTransactionsFixture(User $user): void
{
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2025,
        'is_closed' => false,
    ]);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => 2025,
        'base_currency' => 'EUR',
    ]);

    $accountType = AccountType::query()->create([
        'code' => 'checking-transactions',
        'name' => 'Checking transactions',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto widget',
        'currency' => 'EUR',
        'opening_balance' => 1000,
        'current_balance' => 1300,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese correnti',
        'slug' => 'spese-correnti-transazioni',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    createTransactionForNavigation($user, $account, $category, 120, '2025-03-02');
    createTransactionForNavigation($user, $account, $category, 45, '2025-03-18');
    createTransactionForNavigation($user, $account, $category, 80, '2025-05-07');
}

function createTransactionForNavigation(
    User $user,
    Account $account,
    Category $category,
    float $amount,
    string $date,
): void {
    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => $date,
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => $amount,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Transaction navigation fixture',
    ]);
}
