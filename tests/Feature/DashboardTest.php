<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\AccountBalanceSnapshotSourceTypeEnum;
use App\Enums\BudgetTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\AccountOpeningBalance;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Accounts\AccessibleAccountsQuery;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
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
            ->where('dashboard.overview.income_total', formatMoney(2000))
            ->where('dashboard.overview.income_total_raw', fn ($value) => (float) $value === 2000.0)
            ->where('dashboard.overview.expense_total_raw', fn ($value) => (float) $value === 600.0)
            ->where('dashboard.overview.net_total_raw', fn ($value) => (float) $value === 1400.0)
            ->where('dashboard.overview.budget_total_raw', fn ($value) => (float) $value === 900.0)
            ->where('dashboard.overview.current_balance_total_raw', fn ($value) => (float) $value === 2600.0)
            ->where('dashboard.overview.previous_balance_total_raw', fn ($value) => (float) $value === 1200.0)
            ->where('dashboard.notifications.review_needed_count', 1)
            ->has('dashboard.monthly_trend', 3)
            ->has('dashboard.expense_by_category', 2)
            ->has('dashboard.parent_category_budget_status', 2)
            ->where('dashboard.parent_category_budget_status', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['category_name'] === 'Quotidiano'
                    && $item['budget_total'] === formatMoney(700.0)
                    && (float) $item['actual_total_raw'] === 450.0
                    && (float) $item['delta_raw'] === 250.0)),
        );

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'active_year' => 2025,
    ]);
});

test('dashboard accessible account filters do not compile to postgres distinct on with incompatible ordering', function () {
    $user = User::factory()->create();

    seedDashboardFixture($user);

    $connection = DB::connection();
    $originalGrammar = $connection->getQueryGrammar();
    $connection->setQueryGrammar(new PostgresGrammar($connection));

    try {
        $sql = app(AccessibleAccountsQuery::class)
            ->query($user)
            ->orderByDesc(DB::raw('is_owned'))
            ->orderBy('accounts.name')
            ->toSql();

        expect(strtolower($sql))->not->toContain('distinct on');
    } finally {
        $connection->setQueryGrammar($originalGrammar);
    }
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
            ->where('dashboard.overview.income_total_raw', fn ($value) => (float) $value === 2500.0)
            ->where('dashboard.overview.expense_total_raw', fn ($value) => (float) $value === 700.0)
            ->where('dashboard.overview.net_total_raw', fn ($value) => (float) $value === 1800.0),
        );

    expect(session('dashboard_month'))->toBeNull();
});

test('dashboard resolves available years from user years even without transactions for that year', function () {
    $user = User::factory()->create();

    seedDashboardFixture($user);

    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2027,
        'is_closed' => false,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.filters.year', 2027)
            ->where('dashboard.filters.available_years', fn ($years) => collect($years)
                ->pluck('value')
                ->contains(2027)));
});

test('dashboard ignores records linked to tracked items owned by another user', function () {
    $user = User::factory()->create();
    $foreignUser = User::factory()->create();

    $account = seedDashboardFixture($user);

    $foreignTrackedItem = TrackedItem::query()->create([
        'user_id' => $foreignUser->id,
        'name' => 'Foreign asset',
        'slug' => 'foreign-asset',
        'type' => 'car',
        'is_active' => true,
    ]);

    $expenseCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('slug', 'spesa-casa')
        ->firstOrFail();

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'tracked_item_id' => $foreignTrackedItem->id,
        'transaction_date' => '2025-03-22',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 999,
        'currency' => 'EUR',
        'description' => 'Invalid tracked item',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $expenseCategory->id,
        'tracked_item_id' => $foreignTrackedItem->id,
        'year' => 2025,
        'month' => 3,
        'amount' => 500,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard', [
        'year' => 2025,
        'month' => 3,
    ]));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.overview.expense_total', formatMoney(600.0))
            ->where('dashboard.overview.expense_total_raw', fn ($value) => (float) $value === 600.0)
            ->where('dashboard.overview.budget_total_raw', fn ($value) => (float) $value === 900.0)
            ->where('dashboard.overview.transactions_count', 3));
});

test('dashboard suggests creating the next management year near year end', function () {
    $this->travelTo(now()->setDate(2025, 11, 15));

    $user = User::factory()->create();

    seedDashboardFixture($user);
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2025,
        'is_closed' => false,
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard', [
        'year' => 2025,
    ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.year_suggestion.next_year', 2026)
            ->where('dashboard.year_suggestion.current_year', 2025));
});

test('dashboard suggests opening the current calendar year when it is missing', function () {
    $this->travelTo(now()->setDate(2026, 3, 10));

    $user = User::factory()->create();

    seedDashboardFixture($user);
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2025,
        'is_closed' => false,
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard', [
        'year' => 2025,
    ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.year_suggestion.next_year', 2026));
});

test('dashboard prefers the active year over a stale session year', function () {
    $user = User::factory()->create();

    seedDashboardFixture($user);
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2024,
        'is_closed' => false,
    ]);
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2025,
        'is_closed' => false,
    ]);

    $userSettings = $user->settings()->firstOrNew();
    $userSettings->forceFill([
        'active_year' => 2024,
        'base_currency' => 'EUR',
    ])->save();

    $this->actingAs($user)
        ->withSession([
            'dashboard_year' => 2025,
        ])
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.filters.year', 2024));
});

test('dashboard falls back to evolved account balances when legacy balance columns are empty', function () {
    $user = User::factory()->create();

    $account = seedDashboardFixture($user);

    $account->update([
        'opening_balance' => null,
        'current_balance' => null,
    ]);

    AccountOpeningBalance::query()->create([
        'account_id' => $account->id,
        'balance_date' => '2024-01-01',
        'amount' => 1000,
    ]);

    AccountBalanceSnapshot::query()->create([
        'account_id' => $account->id,
        'snapshot_date' => '2025-03-31',
        'balance' => 2600,
        'source_type' => AccountBalanceSnapshotSourceTypeEnum::SYSTEM->value,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard', [
        'year' => 2025,
        'month' => 3,
    ]));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.overview.current_balance_total_raw', fn ($value) => (float) $value === 2600.0)
            ->where('dashboard.overview.previous_balance_total_raw', fn ($value) => (float) $value === 1200.0)
            ->where('dashboard.accounts_summary.0.opening_balance_raw', fn ($value) => (float) $value === 1000.0)
            ->where('dashboard.accounts_summary.0.current_balance_raw', fn ($value) => (float) $value === 2600.0));
});

test('dashboard excludes opening balance transactions from operational totals while keeping account balances aligned', function () {
    $user = User::factory()->create();
    $account = seedDashboardFixture($user);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2025-03-01',
        'value_date' => '2025-03-01',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => 800,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'balance_after' => 800,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.overview.income_total_raw', fn ($value) => (float) $value === 2000.0)
            ->where('dashboard.accounts_summary.0.opening_balance_raw', fn ($value) => (float) $value === 800.0)
            ->where('dashboard.accounts_summary.0.current_balance_raw', fn ($value) => (float) $value === 2400.0));
});

function seedDashboardFixture(User $user): Account
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

    $dailyCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Quotidiano',
        'slug' => 'quotidiano',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $groceriesCategory = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $dailyCategory->id,
        'name' => 'Spesa casa',
        'slug' => 'spesa-casa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    $homeCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Casa',
        'slug' => 'casa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::BILL->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $utilitiesCategory = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $homeCategory->id,
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

    return $account;
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

function formatMoney(float $amount, string $currency = 'EUR'): string
{
    $formatter = new NumberFormatter('it_IT', NumberFormatter::CURRENCY);

    return $formatter->formatCurrency($amount, $currency);
}
