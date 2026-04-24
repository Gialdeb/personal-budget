<?php

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('reports index renders the new report shell inside the app layout', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $user->assignRole('user');

    createTestAccount($user);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => (int) now(config('app.timezone'))->year],
    );

    $this->actingAs($user)
        ->get(route('reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->has('reportContext')
            ->has('reportSections', 3)
            ->where('reportSections.0.key', 'kpis')
            ->where('transactionsNavigation.context.year', (int) now(config('app.timezone'))->year));
});

test('reports overview page exposes real kpis and trend data for the selected period', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $user->assignRole('user');

    $account = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    $incomeCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Entrate report',
        'slug' => 'entrate-report',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    $expenseCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese report',
        'slug' => 'spese-report',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $incomeCategory->id,
        'transaction_date' => '2026-01-10',
        'value_date' => '2026-01-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 1200,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Stipendio gennaio',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-02-05',
        'value_date' => '2026-02-05',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 300,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Affitto febbraio',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-03-14',
        'value_date' => '2026-03-14',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 150,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Spesa marzo',
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $this->actingAs($user)
        ->get(route('reports.kpis', [
            'year' => 2026,
            'period' => 'last_3_months',
            'month' => 3,
            'account_uuid' => $account->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Overview')
            ->has('reportContext')
            ->has('reportSections', 3)
            ->where('activeReportSection.key', 'kpis')
            ->where('reportOverview.filters.year', 2026)
            ->where('reportOverview.filters.period', 'last_3_months')
            ->where('reportOverview.filters.month', 3)
            ->where('reportOverview.filters.account_uuid', $account->uuid)
            ->has('reportOverview.filters.available_years')
            ->has('reportOverview.filters.period_options', 5)
            ->where('reportOverview.filters.period_options.4.label', 'Da inizio anno (YTD)')
            ->has('reportOverview.filters.account_options', 1)
            ->where('reportOverview.kpis.income_total_raw', fn ($value) => (float) $value === 1200.0)
            ->where('reportOverview.kpis.income_total_comparison.delta_raw', fn ($value) => (float) $value === 1200.0)
            ->where('reportOverview.kpis.expense_total_raw', fn ($value) => (float) $value === 450.0)
            ->where('reportOverview.kpis.expense_total_comparison.delta_raw', fn ($value) => (float) $value === 450.0)
            ->where('reportOverview.kpis.net_total_raw', fn ($value) => (float) $value === 750.0)
            ->where('reportOverview.kpis.net_total_comparison.direction', 'up')
            ->where('reportOverview.kpis.transactions_count', 3)
            ->where('reportOverview.kpis.transactions_count_comparison.delta_raw', 3)
            ->where('reportOverview.kpis.average_net_raw', fn ($value) => (float) $value === 250.0)
            ->where('reportOverview.kpis.best_period_label', fn ($value) => is_string($value) && $value !== '')
            ->where('reportOverview.kpis.worst_period_label', fn ($value) => is_string($value) && $value !== '')
            ->where('reportOverview.meta.granularity', 'month')
            ->where('reportOverview.meta.previous_period_label', 'Ultimi 3 mesi fino a dicembre 2025')
            ->where('reportOverview.meta.scope_label', 'Test account')
            ->has('reportOverview.trend.labels', 3)
            ->where('reportOverview.trend.income_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [1200.0, 0.0, 0.0])
            ->where('reportOverview.trend.expense_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [0.0, 300.0, 150.0])
            ->where('reportOverview.trend.net_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [1200.0, -300.0, -150.0])
            ->has('reportOverview.comparison.labels', 3)
            ->has('reportOverview.buckets', 3)
            ->where('reportOverview.buckets.0.label', fn ($value) => is_string($value) && $value !== '')
            ->where('reportOverview.buckets.2.net_total_raw', fn ($value) => (float) $value === -150.0));
});

test('reports overview filters keep period math and resolved counts aligned with chart payloads', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    $primaryAccount = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'name' => 'Primary account',
    ]);
    $secondaryAccount = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'name' => 'Secondary account',
    ]);

    $incomeCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Entrate report avanzate',
        'slug' => 'entrate-report-avanzate',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    $expenseCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese report avanzate',
        'slug' => 'spese-report-avanzate',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $primaryAccount->id,
        'category_id' => $incomeCategory->id,
        'transaction_date' => '2026-01-10',
        'value_date' => '2026-01-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 1000,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Entrata gennaio',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $primaryAccount->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-02-12',
        'value_date' => '2026-02-12',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 200,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Spesa febbraio',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $secondaryAccount->id,
        'category_id' => $incomeCategory->id,
        'transaction_date' => '2026-03-04',
        'value_date' => '2026-03-04',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 500,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Entrata marzo',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $primaryAccount->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-03-18',
        'value_date' => '2026-03-18',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 125,
        'currency' => 'USD',
        'currency_code' => 'USD',
        'base_currency_code' => 'USD',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Spesa marzo senza conversione',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $primaryAccount->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-04-01',
        'value_date' => '2026-04-01',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 50,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::DRAFT->value,
        'description' => 'Spesa bozza esclusa',
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $this->actingAs($user)
        ->get(route('reports.kpis', [
            'year' => 2026,
            'period' => 'ytd',
            'month' => 3,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportOverview.filters.period', 'ytd')
            ->where('reportOverview.filters.month', 3)
            ->where('reportOverview.kpis.income_total_raw', fn ($value) => (float) $value === 1500.0)
            ->where('reportOverview.kpis.expense_total_raw', fn ($value) => (float) $value === 200.0)
            ->where('reportOverview.kpis.net_total_raw', fn ($value) => (float) $value === 1300.0)
            ->where('reportOverview.kpis.net_total_comparison.delta_raw', fn ($value) => (float) $value === 1300.0)
            ->where('reportOverview.kpis.transactions_count', 3)
            ->where('reportOverview.meta.unresolved_transactions_count', 1)
            ->has('reportOverview.trend.labels', 3)
            ->where('reportOverview.trend.income_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [1000.0, 0.0, 500.0])
            ->where('reportOverview.trend.expense_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [0.0, 200.0, 0.0]));

    $this->actingAs($user)
        ->get(route('reports.kpis', [
            'year' => 2026,
            'period' => 'last_3_months',
            'month' => 3,
            'account_uuid' => $primaryAccount->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportOverview.filters.account_uuid', $primaryAccount->uuid)
            ->where('reportOverview.kpis.income_total_raw', fn ($value) => (float) $value === 1000.0)
            ->where('reportOverview.kpis.expense_total_raw', fn ($value) => (float) $value === 200.0)
            ->where('reportOverview.kpis.net_total_raw', fn ($value) => (float) $value === 800.0)
            ->where('reportOverview.kpis.transactions_count', 2)
            ->where('reportOverview.meta.unresolved_transactions_count', 1)
            ->where('reportOverview.trend.income_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [1000.0, 0.0, 0.0])
            ->where('reportOverview.trend.expense_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [0.0, 200.0, 0.0]));

    $this->actingAs($user)
        ->get(route('reports.kpis', [
            'year' => 2026,
            'period' => 'annual',
            'month' => 3,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportOverview.filters.period', 'annual')
            ->where('reportOverview.filters.month', null)
            ->where('reportOverview.filters.show_month_filter', false));
});

test('reports categories page exposes hierarchical category analytics and honors focus filters', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    $account = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'name' => 'Conto UniCredit',
    ]);

    $incomeRoot = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Entrate',
        'slug' => 'entrate-root',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    $salaryCategory = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $incomeRoot->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio-root',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    $expenseRoot = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese',
        'slug' => 'spese-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    $foodCategory = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Spesa alimentare',
        'slug' => 'spesa-alimentare-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    $supermarketCategory = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $foodCategory->id,
        'name' => 'Supermercato',
        'slug' => 'supermercato-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    $billRoot = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Bollette',
        'slug' => 'bollette-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::BILL->value,
        'is_active' => true,
    ]);

    $energyCategory = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $billRoot->id,
        'name' => 'Energia',
        'slug' => 'energia-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::BILL->value,
        'is_active' => true,
    ]);

    $savingRoot = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Risparmi',
        'slug' => 'risparmi-root',
        'direction_type' => CategoryDirectionTypeEnum::MIXED->value,
        'group_type' => CategoryGroupTypeEnum::SAVING->value,
        'is_active' => true,
    ]);

    $fundCategory = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $savingRoot->id,
        'name' => 'Fondo emergenza',
        'slug' => 'fondo-emergenza-root',
        'direction_type' => CategoryDirectionTypeEnum::MIXED->value,
        'group_type' => CategoryGroupTypeEnum::SAVING->value,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $salaryCategory->id,
        'transaction_date' => '2026-01-10',
        'value_date' => '2026-01-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 2000,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Stipendio gennaio',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $supermarketCategory->id,
        'transaction_date' => '2026-01-14',
        'value_date' => '2026-01-14',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 300,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Spesa settimanale',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $energyCategory->id,
        'transaction_date' => '2026-02-08',
        'value_date' => '2026-02-08',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 120,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Energia febbraio',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $fundCategory->id,
        'transaction_date' => '2026-03-05',
        'value_date' => '2026-03-05',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 400,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Versamento fondo',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseRoot->id,
        'transaction_date' => '2026-03-20',
        'value_date' => '2026-03-20',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value,
        'amount' => 90,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Saldo carta marzo',
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $this->actingAs($user)
        ->get(route('reports.categories', [
            'year' => 2026,
            'period' => 'annual',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Categories')
            ->where('activeReportSection.key', 'categories')
            ->where('reportCategories.filters.focus', 'all')
            ->where('reportCategories.filters.exclude_internal', true)
            ->where('reportCategories.filters.month', null)
            ->where('reportCategories.summary.total_selected_raw', fn ($value) => (float) $value === 2820.0)
            ->where('reportCategories.summary.main_category_label', 'Entrate')
            ->where('reportCategories.summary.main_category_share_label', '70.9%')
            ->where('reportCategories.summary.active_categories_count', 4)
            ->where('reportCategories.summary.top_subcategory_label', 'Stipendio')
            ->has('reportCategories.composition.sunburst_nodes', 4)
            ->where('reportCategories.top_categories.0.label', 'Entrate')
            ->where('reportCategories.top_categories.0.total_raw', fn ($value) => (float) $value === 2000.0)
            ->where('reportCategories.top_categories.1.label', 'Risparmi')
            ->where('reportCategories.top_categories.2.label', 'Spese')
            ->where('reportCategories.recent_transactions.0.description', 'Versamento fondo')
            ->where('reportCategories.recent_transactions.1.description', 'Energia febbraio')
            ->where('reportCategories.recent_transactions.2.description', 'Spesa settimanale')
            ->where('reportCategories.trend.series.0.name', 'Spese')
            ->where('reportCategories.trend.series.0.values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [300.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0])
            ->where('reportCategories.trend.series.1.name', 'Bollette'));

    $this->actingAs($user)
        ->get(route('reports.categories', [
            'year' => 2026,
            'period' => 'annual',
            'focus' => 'expense',
            'exclude_internal' => false,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportCategories.filters.focus', 'expense')
            ->where('reportCategories.filters.exclude_internal', false)
            ->where('reportCategories.summary.total_selected_raw', fn ($value) => (float) $value === 510.0)
            ->where('reportCategories.top_categories.0.label', 'Spese')
            ->where('reportCategories.top_categories.0.total_raw', fn ($value) => (float) $value === 390.0)
            ->where('reportCategories.top_categories.1.label', 'Bollette')
            ->where('reportCategories.recent_transactions.0.description', 'Saldo carta marzo'));
});

test('reports accounts page exposes account vision analytics and comparison payload', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    $primaryAccount = createTestAccount($user, [
        'name' => 'Conto UniCredit',
        'opening_balance' => 1000,
        'current_balance' => 1850,
        'opening_balance_date' => '2026-01-01',
    ]);
    $secondaryAccount = createTestAccount($user, [
        'name' => 'Conto BPM',
        'opening_balance' => 500,
        'current_balance' => 800,
        'opening_balance_date' => '2026-01-01',
    ]);

    $incomeCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio-account-report',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    $expenseCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Casa e bollette',
        'slug' => 'casa-bollette-account-report',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $primaryAccount->id,
        'category_id' => $incomeCategory->id,
        'transaction_date' => '2026-01-10',
        'value_date' => '2026-01-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 1000,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Stipendio gennaio',
        'balance_after' => 2000,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $primaryAccount->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-01-15',
        'value_date' => '2026-01-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 100,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Bollette gennaio',
        'balance_after' => 1900,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $primaryAccount->id,
        'category_id' => $incomeCategory->id,
        'transaction_date' => '2026-02-05',
        'value_date' => '2026-02-05',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 2000,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Stipendio febbraio',
        'balance_after' => 3000,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $primaryAccount->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-02-12',
        'value_date' => '2026-02-12',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 450,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Affitto febbraio',
        'balance_after' => 2550,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $secondaryAccount->id,
        'category_id' => $incomeCategory->id,
        'transaction_date' => '2026-02-15',
        'value_date' => '2026-02-15',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 300,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Rimborso febbraio',
        'balance_after' => 800,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026, 'active_month' => 2],
    );

    $this->actingAs($user)
        ->get(route('reports.accounts', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 2,
            'account_uuid' => $primaryAccount->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Accounts')
            ->where('activeReportSection.key', 'accounts')
            ->where('reportAccounts.filters.year', 2026)
            ->where('reportAccounts.filters.period', 'monthly')
            ->where('reportAccounts.filters.month', 2)
            ->where('reportAccounts.filters.account_uuid', $primaryAccount->uuid)
            ->has('reportAccounts.accounts', 2)
            ->where('reportAccounts.summary.selected_account_name', 'Conto UniCredit')
            ->where('reportAccounts.summary.active_accounts_count', 2)
            ->where('reportAccounts.kpis.income.value_raw', fn ($value) => (float) $value === 2000.0)
            ->where('reportAccounts.kpis.income.comparison_available', true)
            ->where('reportAccounts.kpis.income.delta_percentage_label', '+100.0%')
            ->where('reportAccounts.kpis.expense.value_raw', fn ($value) => (float) $value === 450.0)
            ->where('reportAccounts.kpis.expense.comparison_available', true)
            ->where('reportAccounts.kpis.net.value_raw', fn ($value) => (float) $value === 1550.0)
            ->where('reportAccounts.kpis.best_period.summary', fn ($value) => is_string($value) && str_contains($value, 'feb'))
            ->where('reportAccounts.kpis.best_period.worst_label', fn ($value) => is_string($value) && $value !== '')
            ->has('reportAccounts.balance_trend.series', 2)
            ->has('reportAccounts.cash_flow.labels')
            ->where('reportAccounts.cash_flow.has_data', true)
            ->where('reportAccounts.cash_flow.income_values', fn ($values) => round((float) collect($values)->sum(), 2) === 2000.0)
            ->where('reportAccounts.cash_flow.expense_values', fn ($values) => round((float) collect($values)->sum(), 2) === 450.0)
            ->where('reportAccounts.top_categories.0.label', 'Casa e bollette')
            ->has('reportAccounts.recent_transactions', 2)
            ->has('reportAccounts.comparison_rows', 2));

    $this->actingAs($user)
        ->get(route('reports.accounts', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportAccounts.filters.account_uuid', null)
            ->where('reportAccounts.meta.scope_label', 'Tutti i conti')
            ->where('reportAccounts.kpis.income.value_raw', fn ($value) => (float) $value === 2300.0)
            ->where('reportAccounts.kpis.expense.value_raw', fn ($value) => (float) $value === 450.0)
            ->where('reportAccounts.cash_flow.has_data', true)
            ->where('reportAccounts.cash_flow.income_values', fn ($values) => round((float) collect($values)->sum(), 2) === 2300.0)
            ->where('reportAccounts.cash_flow.expense_values', fn ($values) => round((float) collect($values)->sum(), 2) === 450.0));

    $this->actingAs($user)
        ->get(route('reports.accounts', [
            'year' => 2026,
            'period' => 'last_3_months',
            'month' => 3,
            'account_uuid' => $primaryAccount->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportAccounts.filters.period', 'last_3_months')
            ->has('reportAccounts.cash_flow.labels', 3)
            ->where('reportAccounts.cash_flow.has_data', true)
            ->where('reportAccounts.cash_flow.income_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [1000.0, 2000.0, 0.0])
            ->where('reportAccounts.cash_flow.expense_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [100.0, 450.0, 0.0]));
});

test('report pages keep empty states readable when no data matches the selected filters', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $this->actingAs($user)
        ->get(route('reports.kpis', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 4,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportOverview.kpis.transactions_count', 0)
            ->where('reportOverview.kpis.net_total_comparison.direction', 'neutral')
            ->where('reportOverview.buckets', fn ($buckets) => collect($buckets)->every(fn ($bucket) => (float) $bucket['net_total_raw'] === 0.0)));

    $this->actingAs($user)
        ->get(route('reports.categories', [
            'year' => 2026,
            'period' => 'annual',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportCategories.summary.total_selected_raw', fn ($value) => (float) $value === 0.0)
            ->where('reportCategories.summary.main_category_label', null)
            ->where('reportCategories.summary.top_subcategory_label', null)
            ->has('reportCategories.composition.sunburst_nodes', 0)
            ->has('reportCategories.top_categories', 0)
            ->has('reportCategories.recent_transactions', 0));

    $this->actingAs($user)
        ->get(route('reports.accounts', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 4,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportAccounts.cash_flow.has_data', false)
            ->where('reportAccounts.kpis.income.comparison_available', false)
            ->where('reportAccounts.kpis.income.delta_percentage_label', '+0.0%')
            ->where('reportAccounts.kpis.best_period.summary', null)
            ->where('reportAccounts.cash_flow.income_values', fn ($values) => collect($values)->every(fn ($value) => (float) $value === 0.0))
            ->where('reportAccounts.cash_flow.expense_values', fn ($values) => collect($values)->every(fn ($value) => (float) $value === 0.0))
            ->has('reportAccounts.cash_flow.labels'));
});

test('reports overview stays aligned with the ledger for generated income transfers and previous-period comparison', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    $salaryAccount = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'name' => 'Salary account',
    ]);
    $savingsAccount = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'name' => 'Savings account',
    ]);

    $incomeCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio-report-affidabilita',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    $expenseCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese vive',
        'slug' => 'spese-vive-report-affidabilita',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $salaryAccount->id,
        'category_id' => $incomeCategory->id,
        'transaction_date' => '2026-01-10',
        'value_date' => '2026-01-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::SCHEDULED->value,
        'amount' => 1800,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Stipendio gennaio ricorrente',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $salaryAccount->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-01-12',
        'value_date' => '2026-01-12',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 250,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Spesa gennaio',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $salaryAccount->id,
        'category_id' => $incomeCategory->id,
        'transaction_date' => '2026-02-05',
        'value_date' => '2026-02-05',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::SCHEDULED->value,
        'amount' => 2000,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Stipendio febbraio ricorrente',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $salaryAccount->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-02-08',
        'value_date' => '2026-02-08',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 300,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Spese febbraio',
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $salaryAccount->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-02-10',
        'value_date' => '2026-02-10',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 500,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Trasferimento verso risparmi',
        'is_transfer' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $savingsAccount->id,
        'category_id' => $incomeCategory->id,
        'transaction_date' => '2026-02-10',
        'value_date' => '2026-02-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 500,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Trasferimento da conto stipendio',
        'is_transfer' => true,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $ledgerResponse = $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 2,
        ]));

    $ledgerResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.totals.actual_income_raw', fn ($value) => (float) $value === 2000.0)
            ->where('monthlySheet.totals.actual_expense_raw', fn ($value) => (float) $value === 300.0)
            ->where('monthlySheet.totals.net_actual_raw', fn ($value) => (float) $value === 1700.0)
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Stipendio febbraio ricorrente')));

    $this->actingAs($user)
        ->get(route('reports.kpis', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportOverview.kpis.income_total_raw', fn ($value) => (float) $value === 2000.0)
            ->where('reportOverview.kpis.expense_total_raw', fn ($value) => (float) $value === 300.0)
            ->where('reportOverview.kpis.net_total_raw', fn ($value) => (float) $value === 1700.0)
            ->where('reportOverview.kpis.transactions_count', 2)
            ->where('reportOverview.kpis.income_total_comparison.previous_raw', fn ($value) => (float) $value === 1800.0)
            ->where('reportOverview.kpis.income_total_comparison.delta_raw', fn ($value) => (float) $value === 200.0)
            ->where('reportOverview.kpis.expense_total_comparison.previous_raw', fn ($value) => (float) $value === 250.0)
            ->where('reportOverview.kpis.net_total_comparison.previous_raw', fn ($value) => (float) $value === 1550.0));

    $this->actingAs($user)
        ->get(route('reports.accounts', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportAccounts.filters.account_uuid', null)
            ->where('reportAccounts.cash_flow.has_data', true)
            ->where('reportAccounts.cash_flow.income_values', fn ($values) => round((float) collect($values)->sum(), 2) === 2000.0)
            ->where('reportAccounts.cash_flow.expense_values', fn ($values) => round((float) collect($values)->sum(), 2) === 300.0)
            ->where('reportAccounts.kpis.net.value_raw', fn ($value) => (float) $value === 1700.0));

    $this->actingAs($user)
        ->get(route('reports.kpis', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 2,
            'account_uuid' => $salaryAccount->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportOverview.filters.account_uuid', $salaryAccount->uuid)
            ->where('reportOverview.kpis.income_total_raw', fn ($value) => (float) $value === 2000.0)
            ->where('reportOverview.kpis.expense_total_raw', fn ($value) => (float) $value === 300.0)
            ->where('reportOverview.kpis.transactions_count', 2));

    $this->actingAs($user)
        ->get(route('reports.kpis', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 2,
            'account_uuid' => $savingsAccount->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportOverview.kpis.income_total_raw', fn ($value) => (float) $value === 0.0)
            ->where('reportOverview.kpis.expense_total_raw', fn ($value) => (float) $value === 0.0)
            ->where('reportOverview.kpis.transactions_count', 0));
});
