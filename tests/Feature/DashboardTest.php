<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\AccountBalanceSnapshotSourceTypeEnum;
use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\BudgetTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\MembershipSourceEnum;
use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\ScheduledEntryStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\AccountMembership;
use App\Models\AccountOpeningBalance;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\ScheduledEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Accounts\AccessibleAccountsQuery;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\Str;
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
            ->where('dashboard.pending_actions.total_count', 0)
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

test('dashboard includes owned and shared accessible accounts in the default account filter', function () {
    [$user, $sharedAccount] = seedAccessibleDashboardFilterFixture();

    $this->actingAs($user)
        ->get(route('dashboard', ['year' => 2025, 'month' => 3]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.filters.account_scope', 'all')
            ->where('dashboard.filters.account_uuid', null)
            ->where('dashboard.filters.show_account_scope_filter', true)
            ->where('dashboard.overview.income_total_raw', fn ($value) => (float) $value === 1500.0)
            ->where('dashboard.overview.expense_total_raw', fn ($value) => (float) $value === 300.0)
            ->where('dashboard.overview.net_total_raw', fn ($value) => (float) $value === 1200.0)
            ->where('dashboard.overview.active_accounts_count', 2)
            ->where('dashboard.accounts_summary', fn ($accounts) => collect($accounts)->pluck('account_name')->all() === [
                'Conto Personale',
                'Conto Revolut',
            ])
            ->where('dashboard.filters.account_options', fn ($options) => collect($options)
                ->contains(fn ($option) => $option['value'] === $sharedAccount->uuid
                    && $option['is_shared'] === true
                    && $option['is_owned'] === false)));
});

test('dashboard can filter only owned accounts', function () {
    [$user] = seedAccessibleDashboardFilterFixture();

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2025,
            'month' => 3,
            'account_scope' => 'owned',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.filters.account_scope', 'owned')
            ->where('dashboard.filters.account_uuid', null)
            ->where('dashboard.overview.income_total_raw', fn ($value) => (float) $value === 900.0)
            ->where('dashboard.overview.expense_total_raw', fn ($value) => (float) $value === 100.0)
            ->where('dashboard.overview.active_accounts_count', 1)
            ->where('dashboard.accounts_summary', fn ($accounts) => collect($accounts)
                ->pluck('account_name')
                ->all() === ['Conto Personale']));
});

test('dashboard can filter only shared accounts', function () {
    [$user] = seedAccessibleDashboardFilterFixture();

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2025,
            'month' => 3,
            'account_scope' => 'shared',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.filters.account_scope', 'shared')
            ->where('dashboard.filters.account_uuid', null)
            ->where('dashboard.overview.income_total_raw', fn ($value) => (float) $value === 600.0)
            ->where('dashboard.overview.expense_total_raw', fn ($value) => (float) $value === 200.0)
            ->where('dashboard.overview.active_accounts_count', 1)
            ->where('dashboard.accounts_summary', fn ($accounts) => collect($accounts)
                ->pluck('account_name')
                ->all() === ['Conto Revolut']));
});

test('dashboard can focus on a single shared account', function () {
    [$user, $sharedAccount] = seedAccessibleDashboardFilterFixture();

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2025,
            'month' => 3,
            'account_scope' => 'all',
            'account_uuid' => $sharedAccount->uuid,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.filters.account_scope', 'all')
            ->where('dashboard.filters.account_uuid', $sharedAccount->uuid)
            ->where('dashboard.overview.income_total_raw', fn ($value) => (float) $value === 600.0)
            ->where('dashboard.overview.expense_total_raw', fn ($value) => (float) $value === 200.0)
            ->where('dashboard.accounts_summary', fn ($accounts) => collect($accounts)
                ->pluck('account_name')
                ->all() === ['Conto Revolut']));
});

test('dashboard shows the shared account actual against the single personal reference budget for the invitee', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $account = createTestAccount($owner, ['name' => 'Conto Shared Budget']);

    UserYear::query()->create(['user_id' => $invitee->id, 'year' => 2026, 'is_closed' => false]);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $invitee->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::INVITATION,
        'joined_at' => now(),
    ]);

    $expenseRoot = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Spese',
        'slug' => 'dashboard-owner-expense-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $insurance = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'dashboard-owner-assicurazione',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $sharedExpenseRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Spese',
        'slug' => 'dashboard-shared-expense-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $sharedInsurance = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $sharedExpenseRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'dashboard-shared-assicurazione',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Budget::query()->create([
        'user_id' => $owner->id,
        'category_id' => $insurance->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 700,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'category_id' => $sharedInsurance->id,
        'transaction_date' => '2026-02-15',
        'value_date' => '2026-02-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 670,
        'currency' => 'EUR',
        'description' => 'Assicurazione febbraio',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'kind' => TransactionKindEnum::MANUAL->value,
    ]);

    $this->actingAs($invitee)
        ->get(route('dashboard', [
            'year' => 2026,
            'month' => 2,
            'account_scope' => 'all',
            'account_uuid' => $account->uuid,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.budget_vs_actual', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['category_name'] === 'Assicurazione'
                    && (float) $item['budget_total_raw'] === 700.0
                    && (float) $item['actual_total_raw'] === 670.0
                    && (float) $item['delta_raw'] === 30.0))
        );
});

test('dashboard merges semantic root groups across owned actuals and shared actuals without duplicating the budget model', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    UserYear::query()->create(['user_id' => $user->id, 'year' => 2026, 'is_closed' => false]);

    $ownedAccount = createTestAccount($user, ['name' => 'Conto Personale']);
    $sharedAccount = createTestAccount($owner, ['name' => 'Conto Shared']);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'user_id' => $user->id,
        'household_id' => $sharedAccount->household_id,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::INVITATION,
        'joined_at' => now(),
    ]);

    $personalRoot = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese',
        'slug' => 'dashboard-owned-root-spese',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $personalLeaf = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $personalRoot->id,
        'name' => 'Spesa alimentare',
        'slug' => 'dashboard-owned-spesa-alimentare',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $sharedReferenceRoot = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Spese',
        'slug' => 'dashboard-shared-reference-root-spese',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $sharedReferenceLeaf = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $sharedReferenceRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'dashboard-shared-reference-assicurazione-aggregate',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $sharedRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'name' => 'Spese',
        'slug' => 'dashboard-shared-root-spese',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $sharedLeaf = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'parent_id' => $sharedRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'dashboard-shared-assicurazione-aggregate',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $personalLeaf->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 100,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    Budget::query()->create([
        'user_id' => $owner->id,
        'category_id' => $sharedReferenceLeaf->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 700,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $ownedAccount->id,
        'category_id' => $personalLeaf->id,
        'transaction_date' => '2026-02-10',
        'value_date' => '2026-02-10',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 60,
        'currency' => 'EUR',
        'description' => 'Spesa personale',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'kind' => TransactionKindEnum::MANUAL->value,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $sharedLeaf->id,
        'transaction_date' => '2026-02-15',
        'value_date' => '2026-02-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 670,
        'currency' => 'EUR',
        'description' => 'Spesa shared',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'kind' => TransactionKindEnum::MANUAL->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2026,
            'month' => 2,
            'account_scope' => 'all',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.parent_category_budget_status', fn ($items) => collect($items)
                ->where('category_name', 'Spese')
                ->count() === 1)
            ->where('dashboard.parent_category_budget_status', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['category_name'] === 'Spese'
                    && (float) $item['budget_total_raw'] === 800.0
                    && (float) $item['actual_total_raw'] === 730.0
                    && (float) $item['delta_raw'] === 70.0))
        );
});

test('dashboard does not alter root totals when the filter is narrowed to a single shared or owned account', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    UserYear::query()->create(['user_id' => $user->id, 'year' => 2026, 'is_closed' => false]);

    $ownedAccount = createTestAccount($user, ['name' => 'Conto Personale']);
    $sharedAccount = createTestAccount($owner, ['name' => 'Conto Shared']);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'user_id' => $user->id,
        'household_id' => $sharedAccount->household_id,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::INVITATION,
        'joined_at' => now(),
    ]);

    $personalRoot = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese',
        'slug' => 'dashboard-owned-root-spese-single',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);
    $personalLeaf = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $personalRoot->id,
        'name' => 'Spesa alimentare',
        'slug' => 'dashboard-owned-spesa-alimentare-single',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $sharedReferenceRoot = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Spese',
        'slug' => 'dashboard-shared-reference-root-spese-single',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);
    $sharedReferenceLeaf = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $sharedReferenceRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'dashboard-shared-reference-assicurazione-single',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);
    $sharedRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'name' => 'Spese',
        'slug' => 'dashboard-shared-root-spese-single',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);
    $sharedLeaf = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'parent_id' => $sharedRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'dashboard-shared-assicurazione-single',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $personalLeaf->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 100,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);
    Budget::query()->create([
        'user_id' => $owner->id,
        'category_id' => $sharedReferenceLeaf->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 700,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $ownedAccount->id,
        'category_id' => $personalLeaf->id,
        'transaction_date' => '2026-02-10',
        'value_date' => '2026-02-10',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 60,
        'currency' => 'EUR',
        'description' => 'Spesa personale singolo conto',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'kind' => TransactionKindEnum::MANUAL->value,
    ]);
    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $sharedLeaf->id,
        'transaction_date' => '2026-02-15',
        'value_date' => '2026-02-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 670,
        'currency' => 'EUR',
        'description' => 'Spesa shared singolo conto',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'kind' => TransactionKindEnum::MANUAL->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2026,
            'month' => 2,
            'account_scope' => 'all',
            'account_uuid' => $sharedAccount->uuid,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.overview.expense_total_raw', fn ($value) => (float) $value === 670.0)
            ->where('dashboard.budget_vs_actual', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['category_name'] === 'Assicurazione'
                    && (float) $item['budget_total_raw'] === 700.0
                    && (float) $item['actual_total_raw'] === 670.0
                    && (float) $item['delta_raw'] === 30.0))
        );

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2026,
            'month' => 2,
            'account_scope' => 'all',
            'account_uuid' => $ownedAccount->uuid,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.parent_category_budget_status', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['category_name'] === 'Spese'
                    && (float) $item['budget_total_raw'] === 100.0
                    && (float) $item['actual_total_raw'] === 60.0
                    && (float) $item['delta_raw'] === 40.0))
        );
});

test('dashboard keeps account filters backward compatible for users with only owned accounts', function () {
    $user = User::factory()->create();

    seedDashboardFixture($user);

    $this->actingAs($user)
        ->get(route('dashboard', ['year' => 2025, 'month' => 3]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.filters.account_scope', 'all')
            ->where('dashboard.filters.account_uuid', null)
            ->where('dashboard.filters.show_account_scope_filter', false)
            ->where('dashboard.filters.account_scope_options', fn ($options) => collect($options)
                ->pluck('value')
                ->all() === ['all', 'owned', 'shared'])
            ->where('dashboard.filters.account_options', fn ($options) => count($options) === 1));
});

test('financial agenda uses future recurring and scheduled items in the current account scope', function () {
    $this->travelTo(now()->setDate(2026, 3, 27));

    $user = User::factory()->create();
    $account = seedDashboardFixture($user);
    $expenseCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('slug', 'spesa-casa')
        ->firstOrFail();

    $merchant = Merchant::query()->create([
        'user_id' => $user->id,
        'name' => 'Enel Energia',
        'normalized_name' => 'enel energia',
        'default_category_id' => $expenseCategory->id,
        'is_active' => true,
    ]);

    $trackedItem = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Eurospin',
        'slug' => 'eurospin-dashboard',
        'type' => 'reference',
        'is_active' => true,
    ]);

    $recurringEntry = RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'tracked_item_id' => $trackedItem->id,
        'title' => '',
        'description' => '',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => 89.90,
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_interval' => 1,
        'start_date' => '2026-03-29',
        'next_occurrence_date' => '2026-03-29',
        'end_mode' => RecurringEndModeEnum::NEVER->value,
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
    ]);

    RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $recurringEntry->id,
        'sequence_number' => 1,
        'expected_date' => '2026-03-29',
        'due_date' => '2026-03-29',
        'expected_amount' => 89.90,
        'status' => RecurringOccurrenceStatusEnum::PENDING->value,
    ]);

    ScheduledEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'merchant_id' => $merchant->id,
        'title' => '',
        'description' => '',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => 120.00,
        'currency' => 'EUR',
        'scheduled_date' => '2026-03-28',
        'status' => ScheduledEntryStatusEnum::PLANNED->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['year' => 2026, 'month' => 3]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.recurring_summary.planned_count', 1)
            ->where('dashboard.notifications.due_scheduled_count', 2)
            ->where('dashboard.pending_actions.total_count', 2)
            ->where('dashboard.pending_actions.items', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['title'] === 'Enel Energia'
                    && $item['status_key'] === 'upcoming'
                    && str_contains($item['action_url'], '/recurring-entries')))
            ->where('dashboard.pending_actions.items', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['title'] === 'Eurospin'
                    && $item['status_key'] === 'upcoming'
                    && str_contains($item['action_url'], '/recurring-entries/')))
            ->where('dashboard.scheduled_summary.upcoming', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['display_label'] === 'Enel Energia'
                    && $item['entry_kind'] === 'scheduled'
                    && (float) $item['expected_amount_raw'] === 120.0))
            ->where('dashboard.scheduled_summary.upcoming', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['display_label'] === 'Eurospin'
                    && $item['entry_kind'] === 'recurring'
                    && (float) $item['expected_amount_raw'] === 89.9)));
});

test('financial agenda top payees use meaningful fallbacks instead of missing merchant labels', function () {
    $user = User::factory()->create();
    $account = seedDashboardFixture($user);
    $expenseCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('slug', 'spesa-casa')
        ->firstOrFail();

    $trackedItem = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Farmacia Centrale',
        'slug' => 'farmacia-centrale-dashboard',
        'type' => 'reference',
        'is_active' => true,
    ]);

    createTransaction(
        user: $user,
        account: $account,
        category: $expenseCategory,
        direction: TransactionDirectionEnum::EXPENSE->value,
        amount: 55,
        date: '2025-03-22',
        status: TransactionStatusEnum::CONFIRMED->value,
        trackedItem: $trackedItem,
        description: '',
    );

    $this->actingAs($user)
        ->get(route('dashboard', ['year' => 2025, 'month' => 3]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.merchant_breakdown', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['display_label'] === 'Farmacia Centrale'))
            ->where('dashboard.merchant_breakdown', fn ($items) => collect($items)
                ->doesntContain(fn ($item) => $item['display_label'] === 'Senza merchant')));
});

test('financial agenda respects the dashboard account scope for future items', function () {
    $this->travelTo(now()->setDate(2026, 3, 27));

    [$user, $sharedAccount, $ownedAccount] = seedAccessibleDashboardFilterFixture();

    $expenseCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spesa condivisa',
        'slug' => 'spesa-condivisa-dashboard',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    ScheduledEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $ownedAccount->id,
        'category_id' => $expenseCategory->id,
        'title' => 'Rata personale',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => 40,
        'currency' => 'EUR',
        'scheduled_date' => '2026-03-29',
        'status' => ScheduledEntryStatusEnum::PLANNED->value,
    ]);

    ScheduledEntry::query()->create([
        'user_id' => $sharedAccount->user_id,
        'account_id' => $sharedAccount->id,
        'category_id' => $expenseCategory->id,
        'title' => 'Rata condivisa',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => 80,
        'currency' => 'EUR',
        'scheduled_date' => '2026-03-30',
        'status' => ScheduledEntryStatusEnum::PLANNED->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2026,
            'month' => 3,
            'account_scope' => 'shared',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.pending_actions.items', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['title'] === 'Rata condivisa'))
            ->where('dashboard.pending_actions.items', fn ($items) => collect($items)
                ->doesntContain(fn ($item) => $item['title'] === 'Rata personale'))
            ->where('dashboard.scheduled_summary.upcoming', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['display_label'] === 'Rata condivisa'))
            ->where('dashboard.scheduled_summary.upcoming', fn ($items) => collect($items)
                ->doesntContain(fn ($item) => $item['display_label'] === 'Rata personale')));
});

test('pending actions respect the selected month filter', function () {
    $this->travelTo(now()->setDate(2026, 3, 27));

    $user = User::factory()->create();
    $account = seedDashboardFixture($user);
    $expenseCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('slug', 'spesa-casa')
        ->firstOrFail();

    ScheduledEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'title' => 'Scadenza aprile',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => 30,
        'currency' => 'EUR',
        'scheduled_date' => '2026-04-10',
        'status' => ScheduledEntryStatusEnum::PLANNED->value,
    ]);

    ScheduledEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'title' => 'Scadenza maggio',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => 45,
        'currency' => 'EUR',
        'scheduled_date' => '2026-05-10',
        'status' => ScheduledEntryStatusEnum::PLANNED->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['year' => 2026, 'month' => 4]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.pending_actions.items', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['title'] === 'Scadenza aprile'))
            ->where('dashboard.pending_actions.items', fn ($items) => collect($items)
                ->doesntContain(fn ($item) => $item['title'] === 'Scadenza maggio')));
});

test('dashboard normalizes shared scope to owned when the user has no shared accessible accounts', function () {
    $user = User::factory()->create();

    seedDashboardFixture($user);

    $this->actingAs($user)
        ->get(route('dashboard', [
            'year' => 2025,
            'month' => 3,
            'account_scope' => 'shared',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.filters.account_scope', 'owned')
            ->where('dashboard.filters.show_account_scope_filter', false)
            ->where('dashboard.overview.active_accounts_count', 1));
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

function seedAccessibleDashboardFilterFixture(): array
{
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $accountType = AccountType::firstOrCreate(
        ['code' => 'checking'],
        ['name' => 'Checking', 'balance_nature' => AccountBalanceNatureEnum::ASSET->value],
    );

    $ownedAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto Personale',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => 300,
        'current_balance' => 1100,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $sharedAccount = Account::query()->create([
        'user_id' => $owner->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto Revolut',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => 200,
        'current_balance' => 600,
        'is_manual' => true,
        'is_active' => true,
    ]);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'user_id' => $user->id,
        'household_id' => $sharedAccount->household_id,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::INVITATION,
        'joined_at' => now(),
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $ownedAccount->id,
        'transaction_date' => '2025-03-04',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 900,
        'currency' => 'EUR',
        'description' => 'Owned income',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'value_date' => '2025-03-04',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $ownedAccount->id,
        'transaction_date' => '2025-03-10',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 100,
        'currency' => 'EUR',
        'description' => 'Owned expense',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'value_date' => '2025-03-10',
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'transaction_date' => '2025-03-08',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 600,
        'currency' => 'EUR',
        'description' => 'Shared income',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'value_date' => '2025-03-08',
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'transaction_date' => '2025-03-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 200,
        'currency' => 'EUR',
        'description' => 'Shared expense',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'value_date' => '2025-03-15',
    ]);

    return [$user, $sharedAccount, $ownedAccount];
}

function createTransaction(
    User $user,
    Account $account,
    Category $category,
    string $direction,
    float $amount,
    string $date,
    string $status,
    ?TrackedItem $trackedItem = null,
    string $description = 'Dashboard test transaction',
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
        'description' => $description,
        'tracked_item_id' => $trackedItem?->id,
    ]);
}

function formatMoney(float $amount, string $currency = 'EUR'): string
{
    $formatter = new NumberFormatter('it_IT', NumberFormatter::CURRENCY);

    return $formatter->formatCurrency($amount, $currency);
}
