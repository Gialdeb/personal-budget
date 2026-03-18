<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\BudgetTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard with inertia props', function () {
    $user = User::factory()->create();

    seedDashboardFixture($user);

    $this->actingAs($user);

    $response = $this->get(route('dashboard', [
        'year' => 2025,
        'month' => 3,
    ]));

    $response
        ->assertSuccessful()
        ->assertSessionHas('dashboard_year', 2025)
        ->assertSessionHas('dashboard_month', 3)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.filters.year', 2025)
            ->where('dashboard.filters.month', 3)
            ->where('dashboard.filters.available_years', fn ($years) => collect($years)
                ->pluck('value')
                ->contains(2025))
            ->where('dashboard.overview.income_total', 2000)
            ->where('dashboard.overview.expense_total', 600)
            ->where('dashboard.overview.net_total', 1400)
            ->where('dashboard.overview.budget_total', 900)
            ->where('dashboard.overview.current_balance_total', 2600)
            ->where('dashboard.overview.previous_balance_total', 1200)
            ->where('dashboard.notifications.review_needed_count', 1)
            ->has('dashboard.monthly_trend', 3)
            ->has('dashboard.expense_by_category', 2),
        );

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'active_year' => 2025,
    ]);
});

test('visiting the dashboard with only a year query clears the month filter', function () {
    $user = User::factory()->create();

    seedDashboardFixture($user);

    $this->actingAs($user)
        ->withSession([
            'dashboard_year' => 2025,
            'dashboard_month' => 3,
        ]);

    $response = $this->get(route('dashboard', [
        'year' => 2025,
    ]));

    $response
        ->assertSuccessful()
        ->assertSessionHas('dashboard_year', 2025)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.filters.year', 2025)
            ->where('dashboard.filters.month', null)
            ->where('dashboard.overview.income_total', 2500)
            ->where('dashboard.overview.expense_total', 700)
            ->where('dashboard.overview.net_total', 1800),
        );

    expect(session('dashboard_month'))->toBeNull();
});

function seedDashboardFixture(User $user): void
{
    $accountType = AccountType::query()->create([
        'code' => 'checking',
        'name' => 'Checking',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto Principale',
        'currency' => 'EUR',
        'opening_balance' => 1000,
        'current_balance' => 2300,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $incomeCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    $groceriesCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spesa casa',
        'slug' => 'spesa-casa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    $utilitiesCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Bollette',
        'slug' => 'bollette',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::BILL->value,
        'is_active' => true,
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $groceriesCategory->id,
        'year' => 2025,
        'month' => 3,
        'amount' => 700,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $utilitiesCategory->id,
        'year' => 2025,
        'month' => 3,
        'amount' => 200,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    createTransaction(
        user: $user,
        account: $account,
        category: $groceriesCategory,
        direction: TransactionDirectionEnum::EXPENSE->value,
        amount: 100,
        date: '2025-02-28',
        status: TransactionStatusEnum::CONFIRMED->value,
    );

    createTransaction(
        user: $user,
        account: $account,
        category: $incomeCategory,
        direction: TransactionDirectionEnum::INCOME->value,
        amount: 2000,
        date: '2025-03-05',
        status: TransactionStatusEnum::CONFIRMED->value,
    );

    createTransaction(
        user: $user,
        account: $account,
        category: $groceriesCategory,
        direction: TransactionDirectionEnum::EXPENSE->value,
        amount: 450,
        date: '2025-03-10',
        status: TransactionStatusEnum::CONFIRMED->value,
    );

    createTransaction(
        user: $user,
        account: $account,
        category: $utilitiesCategory,
        direction: TransactionDirectionEnum::EXPENSE->value,
        amount: 150,
        date: '2025-03-18',
        status: TransactionStatusEnum::REVIEW_NEEDED->value,
    );

    createTransaction(
        user: $user,
        account: $account,
        category: $incomeCategory,
        direction: TransactionDirectionEnum::INCOME->value,
        amount: 500,
        date: '2025-04-02',
        status: TransactionStatusEnum::CONFIRMED->value,
    );

    createTransaction(
        user: $user,
        account: $account,
        category: $incomeCategory,
        direction: TransactionDirectionEnum::INCOME->value,
        amount: 300,
        date: '2024-11-10',
        status: TransactionStatusEnum::CONFIRMED->value,
    );
}

function createTransaction(
    User $user,
    Account $account,
    Category $category,
    string $direction,
    float $amount,
    string $date,
    string $status,
): void {
    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => $date,
        'direction' => $direction,
        'amount' => $amount,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => $status,
        'description' => 'Dashboard test transaction',
    ]);
}
