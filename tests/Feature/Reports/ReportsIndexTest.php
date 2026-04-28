<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\BudgetTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\AccountMembership;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Transactions\OperationalLedgerAnalyticsService;
use Carbon\CarbonImmutable;
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
            ->has('reportSections', 4)
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
            ->has('reportSections', 4)
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

test('reports overview includes shared account data for invited users like the account owner', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $owner->assignRole('user');

    $invited = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $invited->assignRole('user');

    $sharedAccount = createTestAccount($owner, [
        'name' => 'Conto condiviso KPI',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    AccountMembership::query()->create([
        'account_id' => $sharedAccount->id,
        'user_id' => $invited->id,
        'role' => AccountMembershipRoleEnum::VIEWER->value,
        'status' => AccountMembershipStatusEnum::ACTIVE->value,
        'granted_by_user_id' => $owner->id,
    ]);

    $incomeCategory = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Entrate condivise KPI',
        'slug' => 'entrate-condivise-kpi',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    $expenseCategory = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Spese condivise KPI',
        'slug' => 'spese-condivise-kpi',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $incomeCategory->id,
        'transaction_date' => '2026-01-10',
        'value_date' => '2026-01-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 1200,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'base_currency_code' => 'EUR',
        'converted_base_amount' => 1200,
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Entrata su conto condiviso',
        'is_transfer' => false,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $expenseCategory->id,
        'transaction_date' => '2026-01-12',
        'value_date' => '2026-01-12',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 350,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'base_currency_code' => 'EUR',
        'converted_base_amount' => 350,
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Spesa su conto condiviso',
        'is_transfer' => false,
    ]);

    foreach ([$owner, $invited] as $user) {
        UserSetting::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['active_year' => 2026],
        );
    }

    $ownerResponse = $this->actingAs($owner)
        ->get(route('reports.kpis', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 1,
            'account_uuid' => $sharedAccount->uuid,
        ]));

    $ownerResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportOverview.kpis.income_total_raw', fn ($value) => (float) $value === 1200.0)
            ->where('reportOverview.kpis.expense_total_raw', fn ($value) => (float) $value === 350.0)
            ->where('reportOverview.kpis.net_total_raw', fn ($value) => (float) $value === 850.0)
            ->where('reportOverview.kpis.transactions_count', 2)
            ->where('reportOverview.comparison.income_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->sum() === 1200.0)
            ->where('reportOverview.comparison.expense_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->sum() === 350.0));

    $this->actingAs($invited)
        ->get(route('reports.kpis', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 1,
            'account_uuid' => $sharedAccount->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportOverview.filters.account_uuid', $sharedAccount->uuid)
            ->where('reportOverview.filters.account_options', fn ($options) => collect($options)
                ->contains(fn ($option) => $option['value'] === $sharedAccount->uuid
                    && $option['is_shared'] === true
                    && $option['can_view'] === true))
            ->where('reportOverview.meta.scope_label', 'Conto condiviso KPI')
            ->where('reportOverview.kpis.income_total_raw', fn ($value) => (float) $value === 1200.0)
            ->where('reportOverview.kpis.expense_total_raw', fn ($value) => (float) $value === 350.0)
            ->where('reportOverview.kpis.net_total_raw', fn ($value) => (float) $value === 850.0)
            ->where('reportOverview.kpis.transactions_count', 2)
            ->where('reportOverview.comparison.income_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->sum() === 1200.0)
            ->where('reportOverview.comparison.expense_values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->sum() === 350.0));
});

test('category analysis page renders with category filters and ledger-based totals', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    $account = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    $food = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spesa alimentare',
        'slug' => 'spesa-alimentare',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'icon' => 'carrot',
        'color' => '#ef4444',
        'is_active' => true,
    ]);
    $supermarket = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $food->id,
        'name' => 'Supermercato',
        'slug' => 'supermercato',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'icon' => 'shopping-cart',
        'color' => '#f97316',
        'is_active' => true,
    ]);
    $market = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $food->id,
        'name' => 'Mercato',
        'slug' => 'mercato',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
    Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spesa alimentare',
        'slug' => 'spesa-alimentare-duplicate-option',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'icon' => 'carrot',
        'color' => '#ef4444',
        'is_active' => true,
    ]);
    $rent = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Affitto',
        'slug' => 'affitto',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    createCategoryAnalysisTransaction($user, $account, $supermarket, [
        'transaction_date' => '2026-01-12',
        'value_date' => '2026-01-12',
        'amount' => 100,
        'converted_base_amount' => 123.45,
    ]);
    createCategoryAnalysisTransaction($user, $account, $market, [
        'transaction_date' => '2026-02-08',
        'value_date' => '2026-02-08',
        'amount' => 200,
    ]);
    createCategoryAnalysisTransaction($user, $account, $rent, [
        'transaction_date' => '2026-02-10',
        'value_date' => '2026-02-10',
        'amount' => 900,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $this->actingAs($user)
        ->get(route('reports.category-analysis', [
            'year' => 2026,
            'period' => 'annual',
            'category_uuid' => $food->uuid,
            'account_uuid' => $account->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/CategoryAnalysis')
            ->where('activeReportSection.key', 'category_analysis')
            ->where('reportCategoryAnalysis.filters.year', 2026)
            ->where('reportCategoryAnalysis.filters.period', 'annual')
            ->where('reportCategoryAnalysis.filters.category_uuid', $food->uuid)
            ->where('reportCategoryAnalysis.filters.subcategory_uuid', null)
            ->where('reportCategoryAnalysis.filters.account_uuid', $account->uuid)
            ->where('reportCategoryAnalysis.filters.category_tree_options', fn ($options) => collect($options)->contains(fn ($option) => $option['value'] === $food->uuid && $option['icon'] === 'carrot' && $option['color'] === '#ef4444')
                && collect($options)->contains(fn ($option) => $option['value'] === $supermarket->uuid && $option['ancestor_uuids'] === [$food->uuid] && $option['icon'] === 'shopping-cart')
                && collect($options)->pluck('full_path')->filter(fn ($label) => $label === 'Spesa alimentare')->count() === 1)
            ->where('reportCategoryAnalysis.filters.subcategory_options_by_category.'.$food->uuid, fn ($options) => collect($options)->pluck('value')->contains($supermarket->uuid))
            ->where('reportCategoryAnalysis.meta.category_label', 'Spesa alimentare')
            ->where('reportCategoryAnalysis.meta.analysis_scope_label', 'Spesa alimentare con tutte le sottocategorie coerenti')
            ->where('reportCategoryAnalysis.meta.budget.supported', false)
            ->where('reportCategoryAnalysis.summary.total_spent_raw', fn ($value) => (float) $value === 323.45)
            ->where('reportCategoryAnalysis.comparisons.previous_year.available', false)
            ->where('reportCategoryAnalysis.year_comparison.supported', false)
            ->where('reportCategoryAnalysis.cumulative.supported', false)
            ->where('reportCategoryAnalysis.subcategory_timeline.supported', true)
            ->where('reportCategoryAnalysis.meta.insight.tone', 'info')
            ->where('reportCategoryAnalysis.trend.series.0.values', fn ($values) => round((float) collect($values)->sum(), 2) === 323.45)
            ->has('reportCategoryAnalysis.subcategory_breakdown.nodes', 2)
            ->where('reportCategoryAnalysis.monthly_rows.0.dominant_subcategory_label', 'Supermercato')
            ->where('reportCategoryAnalysis.monthly_rows.0.spent_raw', fn ($value) => (float) $value === 123.45));
});

test('category analysis annual main expense category matches ledger account-scoped descendants and account totals', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    $unicredit = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'name' => 'Conto UniCredit',
    ]);
    $secondaryAccount = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'name' => 'Conto secondario',
    ]);

    $expenses = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese',
        'slug' => 'spese-ledger-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
    $unicreditExpenses = Category::query()->create([
        'user_id' => $user->id,
        'account_id' => $unicredit->id,
        'name' => 'Spese',
        'slug' => 'shared-unicredit-root-expense',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_system' => true,
    ]);
    $insurance = Category::query()->create([
        'user_id' => $user->id,
        'account_id' => $unicredit->id,
        'parent_id' => $unicreditExpenses->id,
        'name' => 'Assicurazione',
        'slug' => 'assicurazione-ledger-child',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => false,
    ]);
    $informatics = Category::query()->create([
        'user_id' => $user->id,
        'account_id' => $unicredit->id,
        'parent_id' => $unicreditExpenses->id,
        'name' => 'Informatica',
        'slug' => 'informatica-ledger-child',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => false,
    ]);
    $otherRoot = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Extra perimetro',
        'slug' => 'extra-perimetro-ledger-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    createCategoryAnalysisTransaction($user, $unicredit, $insurance, [
        'transaction_date' => '2026-04-05',
        'value_date' => '2026-04-05',
        'amount' => 670,
    ]);
    createCategoryAnalysisTransaction($user, $unicredit, $informatics, [
        'transaction_date' => '2026-04-12',
        'value_date' => '2026-04-12',
        'amount' => 133,
    ]);
    createCategoryAnalysisTransaction($user, $secondaryAccount, $insurance, [
        'transaction_date' => '2026-04-18',
        'value_date' => '2026-04-18',
        'amount' => 25,
    ]);
    createCategoryAnalysisTransaction($user, $unicredit, $otherRoot, [
        'transaction_date' => '2026-04-20',
        'value_date' => '2026-04-20',
        'amount' => 999,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026, 'active_month' => 4],
    );

    $ledgerService = app(OperationalLedgerAnalyticsService::class);
    $ledgerTransactions = $ledgerService->transactionsForPeriod(
        $user,
        CarbonImmutable::create(2026, 4, 1, 0, 0, 0, config('app.timezone'))->startOfDay(),
        CarbonImmutable::create(2026, 4, 30, 0, 0, 0, config('app.timezone'))->endOfDay(),
        null,
    );
    $ledgerDescendantTotal = $ledgerTransactions
        ->whereIn('category_id', [$insurance->id, $informatics->id])
        ->sum(fn (Transaction $transaction): float => (float) $ledgerService->resolveAggregateAmountForTransaction($transaction, 'EUR'));

    expect(round((float) $ledgerDescendantTotal, 2))->toBe(828.0);

    $response = $this->actingAs($user)
        ->get(route('reports.category-analysis', [
            'year' => 2026,
            'period' => 'annual',
            'category_uuid' => $expenses->uuid,
        ]))
        ->assertOk();

    $analysis = $response->inertiaProps('reportCategoryAnalysis');
    $subcategoryNodes = collect($analysis['subcategory_breakdown']['nodes'])->keyBy('label');
    $accountNodes = collect($analysis['account_breakdown']['nodes'])->keyBy('account_name');

    expect((float) $analysis['summary']['total_spent_raw'])->toBe(828.0)
        ->and(round((float) collect($analysis['trend']['series'][0]['values'])->sum(), 2))->toBe(828.0)
        ->and(round((float) collect($analysis['monthly_rows'])->sum('spent_raw'), 2))->toBe(828.0)
        ->and((float) $subcategoryNodes->get('Assicurazione')['value'])->toBe(695.0)
        ->and((float) $subcategoryNodes->get('Informatica')['value'])->toBe(133.0)
        ->and((float) $accountNodes->get('Conto UniCredit')['total_raw'])->toBe(803.0)
        ->and((float) $accountNodes->get('Conto secondario')['total_raw'])->toBe(25.0);

    $unicreditResponse = $this->actingAs($user)
        ->get(route('reports.category-analysis', [
            'year' => 2026,
            'period' => 'annual',
            'category_uuid' => $expenses->uuid,
            'account_uuid' => $unicredit->uuid,
        ]))
        ->assertOk();

    $unicreditAnalysis = $unicreditResponse->inertiaProps('reportCategoryAnalysis');
    $unicreditAccountNodes = collect($unicreditAnalysis['account_breakdown']['nodes'])->keyBy('account_name');

    expect((float) $unicreditAnalysis['summary']['total_spent_raw'])->toBe(803.0)
        ->and(round((float) collect($unicreditAnalysis['monthly_rows'])->sum('spent_raw'), 2))->toBe(803.0)
        ->and((float) $unicreditAccountNodes->get('Conto UniCredit')['total_raw'])->toBe(803.0)
        ->and($unicreditAccountNodes->has('Conto secondario'))->toBeFalse();
});

test('category analysis uses each user personal budget on the same shared account dataset', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $owner->assignRole('user');
    $invitee = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $invitee->assignRole('user');

    $account = createTestAccount($owner, [
        'name' => 'Conto condiviso',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);
    AccountMembership::query()->create([
        'account_id' => $account->id,
        'user_id' => $invitee->id,
        'role' => AccountMembershipRoleEnum::VIEWER->value,
        'status' => AccountMembershipStatusEnum::ACTIVE->value,
        'granted_by_user_id' => $owner->id,
        'joined_at' => now(),
    ]);

    $expenses = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Spese',
        'slug' => 'spese-shared-budget-visible',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
    $insurance = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $expenses->id,
        'name' => 'Assicurazione',
        'slug' => 'assicurazione-shared-budget-visible',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
    $inviteeExpenses = Category::query()->create([
        'user_id' => $invitee->id,
        'name' => 'Spese',
        'slug' => 'spese-shared-budget-invitee',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
    $inviteeInsurance = Category::query()->create([
        'user_id' => $invitee->id,
        'parent_id' => $inviteeExpenses->id,
        'name' => 'Assicurazione',
        'slug' => 'assicurazione-shared-budget-invitee',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
    $sharedExpenses = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Spese',
        'slug' => 'shared-budget-visible-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_system' => true,
    ]);
    $sharedInsurance = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $sharedExpenses->id,
        'name' => 'Assicurazione',
        'slug' => 'shared-budget-visible-insurance',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    createCategoryAnalysisTransaction($owner, $account, $sharedInsurance, [
        'transaction_date' => '2026-02-15',
        'value_date' => '2026-02-15',
        'amount' => 670,
    ]);
    Budget::query()->create([
        'user_id' => $owner->id,
        'category_id' => $insurance->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 840,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);
    Budget::query()->create([
        'user_id' => $invitee->id,
        'category_id' => $inviteeInsurance->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 700,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $invitee->id],
        ['active_year' => 2026, 'active_month' => 2],
    );
    UserSetting::query()->updateOrCreate(
        ['user_id' => $owner->id],
        ['active_year' => 2026, 'active_month' => 2],
    );

    $this->actingAs($owner)
        ->get(route('reports.category-analysis', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 2,
            'category_uuid' => $expenses->uuid,
            'account_uuid' => $account->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportCategoryAnalysis.summary.total_spent_raw', fn ($value) => (float) $value === 670.0)
            ->where('reportCategoryAnalysis.meta.budget.supported', true)
            ->where('reportCategoryAnalysis.meta.budget.reason', null)
            ->where('reportCategoryAnalysis.meta.budget.total_raw', fn ($value) => (float) $value === 840.0)
            ->where('reportCategoryAnalysis.meta.budget.variance_raw', fn ($value) => (float) $value === -170.0)
            ->where('reportCategoryAnalysis.monthly_rows.14.spent_raw', fn ($value) => (float) $value === 670.0)
            ->where('reportCategoryAnalysis.monthly_rows.14.budget_raw', fn ($value) => (float) $value === 30.0)
            ->where('reportCategoryAnalysis.trend.series.1.key', 'budget')
            ->where('reportCategoryAnalysis.cumulative.series.1.values', fn ($values) => round((float) collect($values)->sum(), 2) > 0));

    $this->actingAs($invitee)
        ->get(route('reports.category-analysis', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 2,
            'category_uuid' => $expenses->uuid,
            'account_uuid' => $account->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportCategoryAnalysis.summary.total_spent_raw', fn ($value) => (float) $value === 670.0)
            ->where('reportCategoryAnalysis.meta.budget.supported', true)
            ->where('reportCategoryAnalysis.meta.budget.reason', null)
            ->where('reportCategoryAnalysis.meta.budget.total_raw', fn ($value) => (float) $value === 700.0)
            ->where('reportCategoryAnalysis.meta.budget.variance_raw', fn ($value) => (float) $value === -30.0)
            ->where('reportCategoryAnalysis.monthly_rows.14.spent_raw', fn ($value) => (float) $value === 670.0)
            ->where('reportCategoryAnalysis.monthly_rows.14.budget_raw', fn ($value) => (float) $value === 25.0)
            ->where('reportCategoryAnalysis.trend.series.1.key', 'budget'));
});

test('category analysis does not use owner personal budget when current user is invited', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $owner->assignRole('user');
    $invitee = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $invitee->assignRole('user');

    $account = createTestAccount($owner, [
        'name' => 'Conto condiviso privato',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);
    AccountMembership::query()->create([
        'account_id' => $account->id,
        'user_id' => $invitee->id,
        'role' => AccountMembershipRoleEnum::VIEWER->value,
        'status' => AccountMembershipStatusEnum::ACTIVE->value,
        'granted_by_user_id' => $owner->id,
        'joined_at' => now(),
    ]);

    $expenses = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Spese',
        'slug' => 'spese-shared-budget-hidden',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
    $insurance = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $expenses->id,
        'name' => 'Assicurazione',
        'slug' => 'assicurazione-shared-budget-hidden',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
    $sharedExpenses = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Spese',
        'slug' => 'shared-budget-hidden-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_system' => true,
    ]);
    $sharedInsurance = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $sharedExpenses->id,
        'name' => 'Assicurazione',
        'slug' => 'shared-budget-hidden-insurance',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    createCategoryAnalysisTransaction($owner, $account, $sharedInsurance, [
        'transaction_date' => '2026-02-15',
        'value_date' => '2026-02-15',
        'amount' => 670,
    ]);
    Budget::query()->create([
        'user_id' => $owner->id,
        'scope_id' => null,
        'category_id' => $insurance->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 900,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $invitee->id],
        ['active_year' => 2026, 'active_month' => 2],
    );

    $this->actingAs($invitee)
        ->get(route('reports.category-analysis', [
            'year' => 2026,
            'period' => 'monthly',
            'month' => 2,
            'category_uuid' => $expenses->uuid,
            'account_uuid' => $account->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportCategoryAnalysis.summary.total_spent_raw', fn ($value) => (float) $value === 670.0)
            ->where('reportCategoryAnalysis.meta.budget.supported', false)
            ->where('reportCategoryAnalysis.meta.budget.reason', 'missing_budget')
            ->where('reportCategoryAnalysis.meta.budget.total_raw', fn ($value) => (float) $value === 0.0)
            ->where('reportCategoryAnalysis.meta.budget_scope_description', 'Nessun budget confrontabile trovato per categoria, periodo e perimetro attivo.'));
});

test('category analysis filters by subcategory and exposes previous period and year comparisons', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    $account = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    $food = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spesa alimentare',
        'slug' => 'spesa-alimentare-compare',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
    $supermarket = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $food->id,
        'name' => 'Supermercato',
        'slug' => 'supermercato-compare',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);
    $market = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $food->id,
        'name' => 'Mercato',
        'slug' => 'mercato-compare',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    createCategoryAnalysisTransaction($user, $account, $supermarket, [
        'transaction_date' => '2026-01-12',
        'value_date' => '2026-01-12',
        'amount' => 100,
    ]);
    createCategoryAnalysisTransaction($user, $account, $supermarket, [
        'transaction_date' => '2026-02-08',
        'value_date' => '2026-02-08',
        'amount' => 200,
    ]);
    createCategoryAnalysisTransaction($user, $account, $market, [
        'transaction_date' => '2026-01-20',
        'value_date' => '2026-01-20',
        'amount' => 500,
    ]);
    createCategoryAnalysisTransaction($user, $account, $supermarket, [
        'transaction_date' => '2025-11-11',
        'value_date' => '2025-11-11',
        'amount' => 50,
    ]);
    createCategoryAnalysisTransaction($user, $account, $supermarket, [
        'transaction_date' => '2025-01-18',
        'value_date' => '2025-01-18',
        'amount' => 80,
    ]);
    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $supermarket->id,
        'year' => 2026,
        'month' => 1,
        'amount' => 90,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);
    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $supermarket->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 210,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);
    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $supermarket->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 60,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026, 'active_month' => 3],
    );

    $this->actingAs($user)
        ->get(route('reports.category-analysis', [
            'year' => 2026,
            'period' => 'last_3_months',
            'month' => 3,
            'category_uuid' => $food->uuid,
            'subcategory_uuid' => $supermarket->uuid,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/CategoryAnalysis')
            ->where('reportCategoryAnalysis.filters.category_uuid', $food->uuid)
            ->where('reportCategoryAnalysis.filters.subcategory_uuid', $supermarket->uuid)
            ->where('reportCategoryAnalysis.meta.subcategory_label', 'Supermercato')
            ->where('reportCategoryAnalysis.summary.total_spent_raw', fn ($value) => (float) $value === 300.0)
            ->where('reportCategoryAnalysis.comparisons.previous_period.previous_raw', fn ($value) => (float) $value === 50.0)
            ->where('reportCategoryAnalysis.comparisons.previous_period.delta_raw', fn ($value) => (float) $value === 250.0)
            ->where('reportCategoryAnalysis.comparisons.previous_year.previous_raw', fn ($value) => (float) $value === 80.0)
            ->where('reportCategoryAnalysis.comparisons.previous_year.delta_raw', fn ($value) => (float) $value === 220.0)
            ->where('reportCategoryAnalysis.meta.budget.supported', true)
            ->where('reportCategoryAnalysis.meta.budget.aggregated', false)
            ->where('reportCategoryAnalysis.meta.budget.total_raw', fn ($value) => (float) $value === 360.0)
            ->where('reportCategoryAnalysis.meta.budget.variance_raw', fn ($value) => (float) $value === -60.0)
            ->where('reportCategoryAnalysis.meta.analysis_scope_label', 'Supermercato e sue eventuali categorie discendenti')
            ->where('reportCategoryAnalysis.year_comparison.supported', true)
            ->where('reportCategoryAnalysis.year_comparison.series.0.values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [100.0, 200.0, 0.0])
            ->where('reportCategoryAnalysis.year_comparison.series.1.values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [80.0, 0.0, 0.0])
            ->where('reportCategoryAnalysis.cumulative.supported', true)
            ->where('reportCategoryAnalysis.cumulative.series.0.values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [100.0, 300.0, 300.0])
            ->where('reportCategoryAnalysis.cumulative.series.1.values', fn ($values) => collect($values)->map(fn ($value) => (float) $value)->all() === [90.0, 300.0, 360.0])
            ->where('reportCategoryAnalysis.subcategory_timeline.supported', true)
            ->where('reportCategoryAnalysis.trend.series.1.key', 'budget')
            ->has('reportCategoryAnalysis.monthly_rows', 3)
            ->where('reportCategoryAnalysis.monthly_rows.0.budget_raw', fn ($value) => (float) $value === 90.0)
            ->where('reportCategoryAnalysis.monthly_rows.0.budget_delta_raw', fn ($value) => (float) $value === 10.0)
            ->where('reportCategoryAnalysis.monthly_rows.0.previous_year_raw', fn ($value) => (float) $value === 80.0)
            ->where('reportCategoryAnalysis.monthly_rows.0.dominant_subcategory_label', 'Supermercato')
            ->where('reportCategoryAnalysis.monthly_rows.0.delta_previous_year_raw', fn ($value) => (float) $value === 20.0)
            ->where('reportCategoryAnalysis.monthly_rows.1.delta_previous_year_raw', fn ($value) => (float) $value === 200.0));

    $response = $this->actingAs($user)
        ->get(route('reports.category-analysis.export', [
            'year' => 2026,
            'period' => 'last_3_months',
            'month' => 3,
            'category_uuid' => $food->uuid,
            'subcategory_uuid' => $supermarket->uuid,
        ]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('analisi-categoria-supermercato');

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());

    expect($zip->getFromName('xl/workbook.xml'))
        ->toContain('Summary')
        ->toContain('Monthly detail')
        ->toContain('Subcategories')
        ->toContain('Trend');

    expect($zip->getFromName('xl/worksheets/sheet1.xml'))
        ->toContain('Supermercato')
        ->toContain('300');

    expect($zip->getFromName('xl/worksheets/sheet2.xml'))
        ->toContain('200')
        ->toContain('210');

    $zip->close();

    $pdfResponse = $this->actingAs($user)
        ->get(route('reports.category-analysis.export-pdf', [
            'year' => 2026,
            'period' => 'last_3_months',
            'month' => 3,
            'category_uuid' => $food->uuid,
            'subcategory_uuid' => $supermarket->uuid,
        ]));

    $pdfResponse
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($pdfResponse->headers->get('Content-Disposition'))
        ->toContain('analisi-categoria-supermercato');
});

test('category analysis renders a useful empty state when the selected category has no spend', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    $food = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spesa alimentare',
        'slug' => 'spesa-alimentare-empty',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $food->id,
        'year' => 2026,
        'month' => 1,
        'amount' => 150,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $response = $this->actingAs($user)
        ->get(route('reports.category-analysis', [
            'year' => 2026,
            'period' => 'annual',
            'category_uuid' => $food->uuid,
        ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/CategoryAnalysis')
            ->where('reportCategoryAnalysis.meta.has_actual_spend', false)
            ->where('reportCategoryAnalysis.meta.empty_state_title', 'Nessuna spesa nel perimetro selezionato')
            ->where('reportCategoryAnalysis.meta.budget.supported', true)
            ->where('reportCategoryAnalysis.summary.total_spent_raw', fn ($value) => (float) $value === 0.0)
            ->where('reportCategoryAnalysis.summary.best_period_value', null)
            ->where('reportCategoryAnalysis.summary.worst_period_value', null)
            ->where('reportCategoryAnalysis.year_comparison.supported', false)
            ->where('reportCategoryAnalysis.subcategory_timeline.supported', false)
            ->where('reportCategoryAnalysis.cumulative.supported', true));

    expect(containsRawTranslationKey($response->inertiaProps('reportCategoryAnalysis')))->toBeFalse();
});

test('category analysis renders correctly with reset filters and no raw translation keys', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
    ]);
    $user->assignRole('user');

    $account = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    $food = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spesa alimentare',
        'slug' => 'spesa-alimentare-reset',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    createCategoryAnalysisTransaction($user, $account, $food, [
        'transaction_date' => '2026-04-12',
        'value_date' => '2026-04-12',
        'amount' => 75,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $response = $this->actingAs($user)
        ->get(route('reports.category-analysis', [
            'year' => 2026,
            'period' => 'annual',
        ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/CategoryAnalysis')
            ->where('reportCategoryAnalysis.filters.category_uuid', $food->uuid)
            ->where('reportCategoryAnalysis.filters.subcategory_uuid', null)
            ->where('reportCategoryAnalysis.meta.has_actual_spend', true)
            ->where('reportCategoryAnalysis.summary.total_spent_raw', fn ($value) => (float) $value === 75.0)
            ->where('reportCategoryAnalysis.meta.scope_summary', fn ($value) => is_string($value) && ! str_contains($value, 'reports.')));

    expect(containsRawTranslationKey($response->inertiaProps('reportCategoryAnalysis')))->toBeFalse();
});

test('category analysis pdf filename includes timestamp and localizes italian content', function () {
    $this->travelTo(now(config('app.timezone'))->setDate(2026, 4, 27)->setTime(12, 7));

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
        'locale' => 'it',
    ]);
    $user->assignRole('user');

    $account = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    $food = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spesa alimentare',
        'slug' => 'spesa-alimentare-pdf-it',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    createCategoryAnalysisTransaction($user, $account, $food, [
        'transaction_date' => '2026-01-12',
        'value_date' => '2026-01-12',
        'amount' => 100,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $response = $this->actingAs($user)
        ->get(route('reports.category-analysis.export-pdf', [
            'year' => 2026,
            'period' => 'annual',
            'category_uuid' => $food->uuid,
        ]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('analisi-categoria-spesa-alimentare-2026-20260427-120700.pdf');

    $pdf = file_get_contents($response->baseResponse->getFile()->getPathname());

    expect($pdf)
        ->toContain('Analisi per categoria')
        ->toContain('Breakdown sottocategorie')
        ->toContain('Base di lettura')
        ->not->toContain('reports.categoryAnalysis');
});

test('category analysis pdf localizes english content and hides raw translation keys', function () {
    $this->travelTo(now(config('app.timezone'))->setDate(2026, 4, 27)->setTime(12, 7));

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'base_currency_code' => 'EUR',
        'locale' => 'en',
    ]);
    $user->assignRole('user');

    $account = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    $food = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Groceries',
        'slug' => 'groceries-pdf-en',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    createCategoryAnalysisTransaction($user, $account, $food, [
        'transaction_date' => '2026-01-12',
        'value_date' => '2026-01-12',
        'amount' => 100,
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $response = $this->actingAs($user)
        ->get(route('reports.category-analysis.export-pdf', [
            'year' => 2026,
            'period' => 'annual',
            'category_uuid' => $food->uuid,
        ]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('analisi-categoria-groceries-2026-20260427-120700.pdf');

    $pdf = file_get_contents($response->baseResponse->getFile()->getPathname());

    expect($pdf)
        ->toContain('Category analysis')
        ->toContain('Subcategory breakdown')
        ->toContain('Reading basis')
        ->not->toContain('reports.categoryAnalysis');
});

test('category analysis report section labels are localized', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locale' => 'en',
    ]);
    $user->assignRole('user');

    createTestAccount($user);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => 2026],
    );

    $this->actingAs($user)
        ->get(route('reports.category-analysis', [
            'year' => 2026,
            'period' => 'annual',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeReportSection.title', 'Category analysis')
            ->where('reportSections.2.summary', 'KPIs, trends, and time comparisons for a specific category or subcategory.'));
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

function createCategoryAnalysisTransaction(User $user, $account, Category $category, array $attributes = []): Transaction
{
    return Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-01-01',
        'value_date' => '2026-01-01',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 100,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'base_currency_code' => 'EUR',
        'converted_base_amount' => $attributes['converted_base_amount'] ?? ($attributes['amount'] ?? 100),
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Movimento analisi categoria',
        'is_transfer' => false,
        ...$attributes,
    ]);
}

function containsRawTranslationKey(mixed $value): bool
{
    if (is_string($value)) {
        return str_contains($value, 'reports.categoryAnalysis');
    }

    if (is_array($value)) {
        foreach ($value as $item) {
            if (containsRawTranslationKey($item)) {
                return true;
            }
        }
    }

    return false;
}

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
