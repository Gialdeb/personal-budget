<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\MembershipSourceEnum;
use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Scope;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
use App\Services\Categories\CategoryFoundationService;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use App\Services\Recurring\TransactionRefundService;
use App\Services\Sharing\AccountMembershipLifecycleService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
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

test('transactions month page renders monthly sheet data for the operational layout', function () {
    $user = User::factory()->create();

    [$account, $category, $trackedItem] = seedTransactionsFixture($user);
    $category->update([
        'icon' => 'shopping-cart',
        'color' => '#2563eb',
    ]);
    $account->update(['is_default' => true]);

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
            ->where('monthlySheet.period.year', 2025)
            ->where('monthlySheet.period.month', 3)
            ->where('monthlySheet.meta.transactions_count', 2)
            ->where('monthlySheet.meta.last_balance_raw', 835)
            ->where('monthlySheet.editor.can_edit', true)
            ->where('monthlySheet.editor.default_account_uuid', $account->uuid)
            ->where('monthlySheet.filters.group_options', fn ($groups) => collect($groups)
                ->contains(fn ($group) => $group['value'] === 'expense'))
            ->where('monthlySheet.filters.category_options', fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['label'] === 'Spese correnti' && Str::isUuid($category['uuid'])))
            ->where('monthlySheet.filters.account_options', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['label'] === 'Conto widget'
                    && Str::isUuid($account['uuid'])
                    && $account['account_type_code'] === 'checking-transactions'))
            ->where('monthlySheet.editor.accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['label'] === 'Conto widget'
                    && $account['account_type_code'] === 'checking-transactions'))
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($item) => $item['value'] === $category->uuid
                    && $item['icon'] === 'shopping-cart'
                    && $item['color'] === '#2563eb'
                    && $item['full_path'] === 'Spese correnti'
                    && $item['is_selectable'] === true))
            ->where('monthlySheet.editor.group_options', fn ($groups) => collect($groups)
                ->contains(fn ($group) => $group['value'] === 'expense'))
            ->where('monthlySheet.editor.type_options', fn ($types) => collect($types)
                ->contains(fn ($type) => $type['value'] === 'transfer'
                    && $type['label'] === 'Trasferimento'))
            ->where('monthlySheet.editor.type_options', fn ($types) => collect($types)
                ->contains(fn ($type) => $type['value'] === TransactionKindEnum::BALANCE_ADJUSTMENT->value
                    && $type['label'] === 'Rettifica saldo'
                    && ($type['create_only'] ?? false) === true))
            ->where('monthlySheet.editor.type_options', fn ($types) => collect($types)
                ->contains(fn ($type) => $type['value'] === 'income'))
            ->where('monthlySheet.editor.type_options', fn ($types) => collect($types)
                ->contains(fn ($type) => $type['value'] === 'expense'))
            ->where('monthlySheet.editor.type_options', fn ($types) => collect($types)
                ->contains(fn ($type) => $type['value'] === 'bill'))
            ->where('monthlySheet.editor.type_options', fn ($types) => collect($types)
                ->contains(fn ($type) => $type['value'] === 'debt'))
            ->where('monthlySheet.editor.type_options', fn ($types) => collect($types)
                ->contains(fn ($type) => $type['value'] === 'saving'))
            ->where('monthlySheet.editor.type_options', fn ($types) => collect($types)
                ->doesntContain(fn ($type) => in_array($type['value'], ['tax', 'investment', TransactionKindEnum::OPENING_BALANCE->value], true)))
            ->where('monthlySheet.editor.tracked_items', fn ($trackedItems) => collect($trackedItems)
                ->contains(fn ($trackedItem) => $trackedItem['label'] === 'Auto familiare'
                    && Str::isUuid($trackedItem['uuid'])
                    && $trackedItem['group_keys'] === [CategoryGroupTypeEnum::EXPENSE->value]
                    && $trackedItem['category_uuids'] === [$category->uuid]))
            ->missing('monthlySheet.transactions.0.id')
            ->missing('monthlySheet.filters.category_options.0.id')
            ->missing('monthlySheet.editor.accounts.0.id')
            ->where('monthlySheet.overview.groups', fn ($groups) => collect($groups)
                ->contains(fn ($group) => $group['key'] === 'expense'
                    && $group['label'] === 'Spese'))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Transaction navigation fixture'
                    && Str::isUuid($transaction['uuid'])
                    && $transaction['category_label'] === 'Spese correnti'
                    && $transaction['category_uuid'] === $category->uuid
                    && $transaction['account_label'] === 'Conto widget'
                    && $transaction['tracked_item_label'] === $trackedItem->name))
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
                    && $month['has_data'] === false))
        );

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'active_year' => 2025,
    ]);
});

test('category rename is reflected in the monthly ledger from the linked category', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
        'locale' => 'it',
    ]);

    $account = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance_date' => '2026-01-01',
    ]);

    $parentCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Expenses',
        'slug' => 'expenses-root',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $category = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $parentCategory->id,
        'name' => 'Assicurazione',
        'slug' => 'auto-assicurazione',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    userTransaction($user, $account, [
        'category_id' => $category->id,
        'transaction_date' => '2026-03-12',
        'value_date' => '2026-03-12',
        'description' => 'Movimento con categoria rinominata',
        'amount' => 42,
    ]);

    $this
        ->actingAs($user)
        ->patch(route('categories.update', $category), [
            'name' => 'Insurance',
            'slug' => 'auto-assicurazione',
            'parent_id' => $parentCategory->id,
            'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
            'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    $category->refresh();
    $renamedCategoryUuid = $category->uuid;

    expect($category->name_is_custom)->toBeTrue()
        ->and($category->displayName('it'))->toBe('Insurance')
        ->and($category->displayName('en'))->toBe('Insurance');

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 3,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Movimento con categoria rinominata'
                    && $transaction['category_label'] === 'Insurance'
                    && $transaction['category_path'] === 'Expenses > Insurance'))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->doesntContain(fn ($transaction) => ($transaction['category_label'] ?? null) === 'Assicurazione'
                    || str_contains((string) ($transaction['category_path'] ?? ''), 'Assicurazione')))
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($option) => $option['value'] === $renamedCategoryUuid
                    && $option['label'] === 'Expenses > Insurance'
                    && $option['full_path'] === 'Expenses > Insurance'))
            ->where('monthlySheet.editor.category_overview_items', fn ($categories) => collect($categories)
                ->contains(fn ($option) => $option['uuid'] === $renamedCategoryUuid
                    && $option['label'] === 'Expenses > Insurance')));
});

test('automatic opening balance texts follow the active locale', function (string $locale, string $expectedLabel, string $unexpectedLabel) {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
        'locale' => $locale,
    ]);

    createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => 250,
        'opening_balance_date' => '2026-01-01',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => ($transaction['is_opening_balance'] ?? false) === true
                    && $transaction['category_label'] === $expectedLabel))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->doesntContain(fn ($transaction) => ($transaction['category_label'] ?? null) === $unexpectedLabel)));
})->with([
    'italian' => ['it', 'Apertura contabile 2026', 'Opening balance 2026'],
    'english' => ['en', 'Opening balance 2026', 'Apertura contabile 2026'],
]);

test('foundation category defaults are localized without overriding custom category names', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
        'locale' => 'en',
    ]);

    $account = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance_date' => '2026-01-01',
    ]);

    $foodCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Alimentari',
        'slug' => 'alimentari',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $customCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Bottega sotto casa',
        'slug' => 'bottega-sotto-casa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    userTransaction($user, $account, [
        'category_id' => $foodCategory->id,
        'transaction_date' => '2026-03-01',
        'value_date' => '2026-03-01',
        'description' => 'Canonical category row',
        'amount' => 10,
    ]);

    userTransaction($user, $account, [
        'category_id' => $customCategory->id,
        'transaction_date' => '2026-03-02',
        'value_date' => '2026-03-02',
        'description' => 'Custom category row',
        'amount' => 20,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 3,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Canonical category row'
                    && $transaction['category_label'] === 'Groceries'))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Custom category row'
                    && $transaction['category_label'] === 'Bottega sotto casa')));
});

test('category display matrix is consistent in ledger and editor category options', function (string $locale, string $expectedFoundationName) {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
        'locale' => $locale,
    ]);

    $account = createTestAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance_date' => '2026-01-01',
    ]);

    $foundationCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Alimentari',
        'name_is_custom' => false,
        'slug' => 'alimentari',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $renamedFoundationCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Insurance',
        'name_is_custom' => true,
        'slug' => 'auto-assicurazione',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $customCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Bottega sotto casa',
        'name_is_custom' => true,
        'slug' => 'bottega-sotto-casa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    userTransaction($user, $account, [
        'category_id' => $foundationCategory->id,
        'transaction_date' => '2026-03-01',
        'value_date' => '2026-03-01',
        'description' => 'Foundation category matrix row',
        'amount' => 10,
    ]);

    userTransaction($user, $account, [
        'category_id' => $renamedFoundationCategory->id,
        'transaction_date' => '2026-03-02',
        'value_date' => '2026-03-02',
        'description' => 'Renamed foundation category matrix row',
        'amount' => 20,
    ]);

    userTransaction($user, $account, [
        'category_id' => $customCategory->id,
        'transaction_date' => '2026-03-03',
        'value_date' => '2026-03-03',
        'description' => 'Custom category matrix row',
        'amount' => 30,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 3,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Foundation category matrix row'
                    && $transaction['category_label'] === $expectedFoundationName)
                && collect($transactions)->contains(fn ($transaction) => $transaction['description'] === 'Renamed foundation category matrix row'
                    && $transaction['category_label'] === 'Insurance')
                && collect($transactions)->contains(fn ($transaction) => $transaction['description'] === 'Custom category matrix row'
                    && $transaction['category_label'] === 'Bottega sotto casa'))
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($option) => $option['value'] === $foundationCategory->uuid
                    && $option['label'] === $expectedFoundationName
                    && $option['full_path'] === $expectedFoundationName)
                && collect($categories)->contains(fn ($option) => $option['value'] === $renamedFoundationCategory->uuid
                    && $option['label'] === 'Insurance'
                    && $option['full_path'] === 'Insurance')
                && collect($categories)->contains(fn ($option) => $option['value'] === $customCategory->uuid
                    && $option['label'] === 'Bottega sotto casa'
                    && $option['full_path'] === 'Bottega sotto casa')));
})->with([
    'italian locale' => ['it', 'Alimentari'],
    'english locale' => ['en', 'Groceries'],
]);

test('transactions month page aggregates mixed-currency totals using converted base amounts', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    [$account, $category] = seedTransactionsFixture($user);

    $foreignAccountType = AccountType::query()->firstOrCreate([
        'code' => 'checking-transactions-gbp',
    ], [
        'name' => 'Checking transactions GBP',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $foreignAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $foreignAccountType->id,
        'name' => 'Conto GBP',
        'currency' => 'GBP',
        'currency_code' => 'GBP',
        'opening_balance' => 0,
        'current_balance' => -10,
        'is_manual' => true,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $foreignAccount->id,
        'category_id' => $category->id,
        'transaction_date' => '2025-03-20',
        'value_date' => '2025-03-20',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 10,
        'currency' => 'GBP',
        'currency_code' => 'GBP',
        'base_currency_code' => 'EUR',
        'exchange_rate' => '1.20000000',
        'exchange_rate_date' => '2025-03-20',
        'converted_base_amount' => 12,
        'exchange_rate_source' => 'frankfurter',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Foreign monthly sheet expense',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.totals.actual_expense_raw', fn ($value) => (float) $value === 177.0)
            ->where('monthlySheet.totals.net_actual_raw', fn ($value) => (float) $value === -177.0)
            ->where('monthlySheet.meta.last_balance_raw', fn ($value) => (float) $value === 823.0));
});

test('transactions month page excludes unsafe legacy foreign records from global aggregate totals', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    [$account, $category] = seedTransactionsFixture($user);

    $foreignAccountType = AccountType::query()->firstOrCreate([
        'code' => 'checking-transactions-gbp-legacy',
    ], [
        'name' => 'Checking transactions GBP legacy',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $foreignAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $foreignAccountType->id,
        'name' => 'Conto legacy GBP',
        'currency' => 'GBP',
        'currency_code' => 'GBP',
        'opening_balance' => 0,
        'current_balance' => -10,
        'is_manual' => true,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $foreignAccount->id,
        'category_id' => $category->id,
        'transaction_date' => '2025-03-20',
        'value_date' => '2025-03-20',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 10,
        'currency' => 'GBP',
        'currency_code' => 'GBP',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Unsafe legacy monthly sheet expense',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.totals.actual_expense_raw', fn ($value) => (float) $value === 165.0)
            ->where('monthlySheet.meta.last_balance_raw', fn ($value) => (float) $value === 835.0));
});

test('transactions month page exposes recurring markers and planned recurring occurrences for the active month', function () {
    $user = User::factory()->create();

    [$account, $category, $trackedItem] = seedTransactionsFixture($user);

    [$entry, $occurrence] = createRecurringPreviewFixture($user, $account, $category, $trackedItem, [
        'start_date' => '2025-03-20',
    ]);

    $scheduledTransaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'tracked_item_id' => $trackedItem->id,
        'transaction_date' => '2025-03-20',
        'value_date' => '2025-03-20',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::SCHEDULED->value,
        'amount' => 75,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Recurring scheduled charge',
        'balance_after' => 760,
        'recurring_entry_occurrence_id' => $occurrence->id,
    ]);

    $occurrence->update([
        'converted_transaction_id' => $scheduledTransaction->id,
        'status' => RecurringOccurrenceStatusEnum::COMPLETED->value,
    ]);

    [, $plannedOccurrence] = createRecurringPreviewFixture($user, $account, $category, $trackedItem, [
        'title' => 'Gym membership',
        'description' => 'Gym membership',
        'start_date' => '2025-03-25',
        'expected_amount' => 39,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.meta.planned_occurrences_count', 1)
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['uuid'] === $scheduledTransaction->uuid
                    && $transaction['is_recurring_transaction'] === true
                    && $transaction['is_projected_recurring'] === false
                    && $transaction['recurring_entry_uuid'] === $entry->uuid
                    && $transaction['recurring_occurrence_uuid'] === $occurrence->uuid))
            ->where('monthlySheet.planned_occurrences', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['uuid'] === 'planned-'.$plannedOccurrence->uuid
                    && $transaction['is_projected_recurring'] === true
                    && $transaction['is_recurring_transaction'] === false
                    && $transaction['recurring_entry_uuid'] === $plannedOccurrence->recurringEntry->uuid
                    && $transaction['date'] === '2025-03-25'
                    && (float) $transaction['amount_raw'] === -39.0))
        );
});

test('transactions month page includes owned and active shared account records without leaking inaccessible ones', function () {
    $user = User::factory()->create();
    $sharedOwner = User::factory()->create();
    $revokedOwner = User::factory()->create();

    seedTransactionsFixture($user);

    [$sharedAccount, $sharedCategory] = seedTransactionsFixture($sharedOwner);
    [$revokedAccount, $revokedCategory] = seedTransactionsFixture($revokedOwner);

    Transaction::query()->create([
        'user_id' => $sharedOwner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $sharedCategory->id,
        'transaction_date' => '2025-03-14',
        'value_date' => '2025-03-14',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 20,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Shared visible transaction',
        'balance_after' => 980,
        'created_by_user_id' => $sharedOwner->id,
        'updated_by_user_id' => $sharedOwner->id,
    ]);

    Transaction::query()->create([
        'user_id' => $revokedOwner->id,
        'account_id' => $revokedAccount->id,
        'category_id' => $revokedCategory->id,
        'transaction_date' => '2025-03-14',
        'value_date' => '2025-03-14',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 33,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Shared hidden transaction',
        'balance_after' => 967,
        'created_by_user_id' => $revokedOwner->id,
        'updated_by_user_id' => $revokedOwner->id,
    ]);

    shareAccountWithUser($sharedAccount, $user, AccountMembershipRoleEnum::VIEWER);
    shareAccountWithUser($revokedAccount, $user, AccountMembershipRoleEnum::VIEWER, AccountMembershipStatusEnum::REVOKED);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactionsPage.records_count', 5)
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Shared visible transaction'
                    && $transaction['account_label'] === 'Conto widget'))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->doesntContain(fn ($transaction) => $transaction['description'] === 'Shared hidden transaction'))
            ->where('monthlySheet.filters.account_options', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['label'] === 'Conto widget'
                    && $account['is_shared'] === true
                    && $account['membership_role'] === AccountMembershipRoleEnum::VIEWER->value
                    && $account['can_edit'] === false))
            ->where('transactionsNavigation.summary.records_count', 5));
});

test('shared account transfer transactions render without requiring transfer categories in the shared taxonomy', function () {
    $user = User::factory()->create();
    $sharedOwner = User::factory()->create();

    seedTransactionsFixture($user);

    [$sharedAccount, , , , $transferCategory] = seedTransactionsFixture($sharedOwner);

    $sourceTransfer = Transaction::query()->create([
        'user_id' => $sharedOwner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $transferCategory->id,
        'created_by_user_id' => $sharedOwner->id,
        'updated_by_user_id' => $sharedOwner->id,
        'transaction_date' => '2025-03-24',
        'value_date' => '2025-03-24',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 150,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'base_currency_code' => 'EUR',
        'exchange_rate' => 1,
        'exchange_rate_date' => '2025-03-24',
        'converted_base_amount' => 150,
        'exchange_rate_source' => 'same_currency',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Shared giroconto source',
        'balance_after' => 850,
        'is_transfer' => true,
    ]);

    $destinationTransfer = Transaction::query()->create([
        'user_id' => $sharedOwner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $transferCategory->id,
        'created_by_user_id' => $sharedOwner->id,
        'updated_by_user_id' => $sharedOwner->id,
        'transaction_date' => '2025-03-24',
        'value_date' => '2025-03-24',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 150,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'base_currency_code' => 'EUR',
        'exchange_rate' => 1,
        'exchange_rate_date' => '2025-03-24',
        'converted_base_amount' => 150,
        'exchange_rate_source' => 'same_currency',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Shared giroconto destination',
        'balance_after' => 1000,
        'is_transfer' => true,
        'related_transaction_id' => $sourceTransfer->id,
    ]);

    $sourceTransfer->forceFill([
        'related_transaction_id' => $destinationTransfer->id,
    ])->save();

    shareAccountWithUser($sharedAccount, $user, AccountMembershipRoleEnum::VIEWER);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Shared giroconto source'))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Shared giroconto destination')));

    expect(Category::query()
        ->sharedForAccount($sharedAccount->id)
        ->where('group_type', CategoryGroupTypeEnum::TRANSFER->value)
        ->exists())->toBeFalse();
});

test('shared account monthly totals stay consistent between owner and invited user', function () {
    $owner = User::factory()->create();
    $invited = User::factory()->create();

    ensureTransactionsContext($owner, 2026);
    ensureTransactionsContext($invited, 2026);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'shared-summary-checking',
    ], [
        'name' => 'Shared summary checking',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $sharedAccount = Account::query()->create([
        'user_id' => $owner->id,
        'account_type_id' => $accountType->id,
        'name' => 'Revolut',
        'currency' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 1220.10,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $expenseCategory = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Spese condivise',
        'slug' => 'spese-condivise-'.$owner->id,
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    $incomeCategory = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Entrate condivise',
        'slug' => 'entrate-condivise-'.$owner->id,
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $expenseCategory->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'transaction_date' => '2026-03-02',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 29.90,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Uscita condivisa',
        'balance_after' => -29.90,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $incomeCategory->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'transaction_date' => '2026-03-12',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 1250,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Entrata condivisa',
        'balance_after' => 1220.10,
    ]);

    shareAccountWithUser($sharedAccount, $invited, AccountMembershipRoleEnum::VIEWER);

    $assertMonthlyTotals = function (User $user): void {
        $this->actingAs($user)
            ->get(route('transactions.show', [
                'year' => 2026,
                'month' => 3,
            ]))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('transactions/Show')
                ->where('monthlySheet.totals.actual_income_raw', fn ($value) => (float) $value === 1250.0)
                ->where('monthlySheet.totals.actual_expense_raw', fn ($value) => (float) $value === 29.9)
                ->where('monthlySheet.totals.net_actual_raw', fn ($value) => (float) $value === 1220.1)
                ->where('monthlySheet.meta.last_balance_raw', fn ($value) => (float) $value === 1220.1)
                ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                    ->contains(fn ($card) => $card['key'] === 'income' && (float) $card['actual_raw'] === 1250.0))
                ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                    ->contains(fn ($card) => $card['key'] === 'expense' && (float) $card['actual_raw'] === 29.9))
                ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                    ->contains(fn ($card) => $card['key'] === 'net' && (float) $card['actual_raw'] === 1220.1))
            );
    };

    $assertMonthlyTotals($owner);
    $assertMonthlyTotals($invited);
});

test('all accounts totals keep uncategorized shared expenses in the monthly summary', function () {
    $owner = User::factory()->create();
    $invited = User::factory()->create();

    ensureTransactionsContext($owner, 2026);
    ensureTransactionsContext($invited, 2026);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'shared-summary-uncategorized',
    ], [
        'name' => 'Shared summary uncategorized',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $sharedAccount = Account::query()->create([
        'user_id' => $owner->id,
        'account_type_id' => $accountType->id,
        'name' => 'Revolut',
        'currency' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 1220.10,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $incomeCategory = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Entrate condivise',
        'slug' => 'entrate-condivise-uncategorized-'.$owner->id,
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => null,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'transaction_date' => '2026-03-02',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 29.90,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Uscita condivisa senza categoria',
        'balance_after' => -29.90,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $incomeCategory->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'transaction_date' => '2026-03-12',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 1250,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Entrata condivisa',
        'balance_after' => 1220.10,
    ]);

    shareAccountWithUser($sharedAccount, $invited, AccountMembershipRoleEnum::VIEWER);

    $this->actingAs($invited)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Show')
            ->where('monthlySheet.totals.actual_income_raw', fn ($value) => (float) $value === 1250.0)
            ->where('monthlySheet.totals.actual_expense_raw', fn ($value) => (float) $value === 29.9)
            ->where('monthlySheet.totals.net_actual_raw', fn ($value) => (float) $value === 1220.1)
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'expense' && (float) $card['actual_raw'] === 29.9))
        );
});

test('all accounts totals merge owned and shared account movements without losing shared expenses', function () {
    $owner = User::factory()->create();
    $invited = User::factory()->create();

    ensureTransactionsContext($owner, 2026);

    [$ownedAccount, $ownedCategory] = seedTransactionsFixture($invited, 2026);

    Transaction::query()->where('account_id', $ownedAccount->id)->delete();

    Transaction::query()->create([
        'user_id' => $invited->id,
        'account_id' => $ownedAccount->id,
        'category_id' => $ownedCategory->id,
        'created_by_user_id' => $invited->id,
        'updated_by_user_id' => $invited->id,
        'transaction_date' => '2026-03-20',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 10.00,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Uscita owned',
        'balance_after' => 990.00,
    ]);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'shared-summary-mixed',
    ], [
        'name' => 'Shared summary mixed',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $sharedAccount = Account::query()->create([
        'user_id' => $owner->id,
        'account_type_id' => $accountType->id,
        'name' => 'Revolut',
        'currency' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 1220.10,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $incomeCategory = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Entrate condivise',
        'slug' => 'entrate-condivise-mixed-'.$owner->id,
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => null,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'transaction_date' => '2026-03-02',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 29.90,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Uscita condivisa senza categoria',
        'balance_after' => -29.90,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $incomeCategory->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'transaction_date' => '2026-03-12',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 1250,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Entrata condivisa',
        'balance_after' => 1220.10,
    ]);

    shareAccountWithUser($sharedAccount, $invited, AccountMembershipRoleEnum::VIEWER);

    $this->actingAs($invited)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Show')
            ->where('monthlySheet.totals.actual_income_raw', fn ($value) => (float) $value === 1250.0)
            ->where('monthlySheet.totals.actual_expense_raw', fn ($value) => (float) $value === 39.9)
            ->where('monthlySheet.totals.net_actual_raw', fn ($value) => (float) $value === 1210.1)
            ->where('monthlySheet.meta.transactions_count', 3)
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'expense' && (float) $card['actual_raw'] === 39.9))
        );
});

test('transactions can be created from the monthly sheet', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account, $category, $trackedItem] = seedTransactionsFixture($user);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 22,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $account->uuid,
            'category_uuid' => $category->uuid,
            'tracked_item_uuid' => $trackedItem->uuid,
            'amount' => 32.4,
            'description' => 'Nuova spesa operativa',
            'notes' => 'Creata dal foglio mensile',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2025-03-22 00:00:00',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 32.4,
        'description' => 'Nuova spesa operativa',
        'tracked_item_id' => $trackedItem->id,
        'kind' => TransactionKindEnum::MANUAL->value,
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
    ]);
});

test('viewer memberships can read shared transactions but cannot create update or delete them', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $viewer = User::factory()->create();
    $owner = User::factory()->create();

    ensureTransactionsContext($viewer);
    [$account, $category, $trackedItem] = seedTransactionsFixture($owner);
    shareAccountWithUser($account, $viewer, AccountMembershipRoleEnum::VIEWER);

    $transaction = Transaction::query()
        ->where('account_id', $account->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $this->actingAs($viewer)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.editor.can_edit', false)
            ->where('monthlySheet.editor.accounts', fn ($accounts) => collect($accounts)
                ->doesntContain(fn ($item) => $item['uuid'] === $account->uuid))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($item) => $item['uuid'] === $transaction->uuid
                    && $item['can_edit'] === false
                    && $item['can_delete'] === false)));

    $this->actingAs($viewer)
        ->from(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 20,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $account->uuid,
            'category_uuid' => $category->uuid,
            'tracked_item_uuid' => $trackedItem->uuid,
            'amount' => 12,
            'description' => 'Viewer blocked transaction',
        ])
        ->assertSessionHasErrors('account_uuid');

    $this->actingAs($viewer)
        ->from(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_day' => 19,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'tracked_item_id' => $trackedItem->id,
            'amount' => 49,
            'description' => 'Viewer should not update',
        ])
        ->assertSessionHasErrors('account_id');

    $this->actingAs($viewer)
        ->from(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->delete(route('transactions.destroy', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]))
        ->assertSessionHasErrors('transaction');

    $this->assertDatabaseMissing('transactions', [
        'account_id' => $account->id,
        'description' => 'Viewer blocked transaction',
    ]);
});

test('editor memberships can create update and delete shared transactions while preserving owner dataset and audit metadata', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $editor = User::factory()->create();
    $owner = User::factory()->create([
        'name' => 'Owner Shared',
        'email' => 'owner-shared@example.test',
    ]);

    ensureTransactionsContext($editor);
    [$account, $category, $trackedItem] = seedTransactionsFixture($owner);
    shareAccountWithUser($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $existingTransaction = Transaction::query()
        ->where('account_id', $account->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $response = $this->actingAs($editor)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 21,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $account->uuid,
            'category_uuid' => $category->uuid,
            'amount' => 28.5,
            'description' => 'Shared editor transaction',
            'notes' => 'Created by editor',
        ])
        ->assertStatus(302);

    $createdTransaction = Transaction::query()
        ->where('account_id', $account->id)
        ->where('description', 'Shared editor transaction')
        ->firstOrFail();

    expect($createdTransaction->user_id)->toBe($owner->id)
        ->and($createdTransaction->created_by_user_id)->toBe($editor->id)
        ->and($createdTransaction->updated_by_user_id)->toBe($editor->id);

    $this->actingAs($editor)
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $existingTransaction->uuid,
        ]), [
            'transaction_day' => 18,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 51,
            'description' => 'Shared editor updated',
            'notes' => 'Edited by editor',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $existingTransaction->refresh();

    expect($existingTransaction->user_id)->toBe($owner->id)
        ->and($existingTransaction->updated_by_user_id)->toBe($editor->id)
        ->and($existingTransaction->description)->toBe('Shared editor updated');

    $this->actingAs($editor)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.editor.can_edit', true)
            ->where('monthlySheet.editor.accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($item) => $item['uuid'] === $account->uuid
                    && $item['is_shared'] === true
                    && $item['membership_role'] === AccountMembershipRoleEnum::EDITOR->value
                    && $item['can_edit'] === true))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['uuid'] === $existingTransaction->uuid
                    && $transaction['can_edit'] === true
                    && $transaction['created_by']['uuid'] === $owner->uuid
                    && $transaction['updated_by']['uuid'] === $editor->uuid
                    && is_string($transaction['last_modified_at']))));

    $this->actingAs($editor)
        ->delete(route('transactions.destroy', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $createdTransaction->uuid,
        ]))
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    expect(Transaction::withTrashed()->findOrFail($createdTransaction->id)->trashed())->toBeTrue();
});

test('balance adjustment preview and store compute a negative adjustment from the account theoretical balance', function () {
    $user = User::factory()->create();

    ensureTransactionsContext($user, 2025);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'balance-adjustment-preview',
    ], [
        'name' => 'Balance adjustment preview',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto rettifica',
        'currency' => 'EUR',
        'opening_balance' => 300.00,
        'current_balance' => 275.00,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => null,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'transaction_date' => '2025-03-10',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 25.00,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Movimento base',
        'balance_after' => 275.00,
    ]);

    $this->actingAs($user)
        ->postJson(route('transactions.balance-adjustment-preview', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 15,
            'account_uuid' => $account->uuid,
            'desired_balance' => 40.00,
        ])
        ->assertSuccessful()
        ->assertJson([
            'theoretical_balance_raw' => 275.0,
            'desired_balance_raw' => 40.0,
            'adjustment_amount_raw' => -235.0,
            'direction' => TransactionDirectionEnum::EXPENSE->value,
        ]);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 15,
            'type_key' => StoreTransactionRequest::BALANCE_ADJUSTMENT_TYPE_KEY,
            'account_uuid' => $account->uuid,
            'desired_balance' => 40.00,
            'description' => 'Rettifica marzo',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $adjustment = Transaction::query()
        ->where('account_id', $account->id)
        ->where('kind', TransactionKindEnum::BALANCE_ADJUSTMENT->value)
        ->firstOrFail();

    $account->refresh();

    expect($adjustment->direction)->toBe(TransactionDirectionEnum::EXPENSE)
        ->and((float) $adjustment->amount)->toBe(235.0)
        ->and((float) $adjustment->balance_after)->toBe(40.0)
        ->and($adjustment->source_type)->toBe(TransactionSourceTypeEnum::ADJUSTMENT)
        ->and($account->current_balance)->toBe('40.00');
});

test('balance adjustment can create a positive adjustment and realign the account balance', function () {
    $user = User::factory()->create();

    ensureTransactionsContext($user, 2025);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'balance-adjustment-positive',
    ], [
        'name' => 'Balance adjustment positive',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto positivo',
        'currency' => 'EUR',
        'opening_balance' => 100.00,
        'current_balance' => 100.00,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 12,
            'type_key' => StoreTransactionRequest::BALANCE_ADJUSTMENT_TYPE_KEY,
            'account_uuid' => $account->uuid,
            'desired_balance' => 160.00,
            'description' => 'Rettifica positiva',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $adjustment = Transaction::query()
        ->where('account_id', $account->id)
        ->where('kind', TransactionKindEnum::BALANCE_ADJUSTMENT->value)
        ->firstOrFail();

    $account->refresh();

    expect($adjustment->direction)->toBe(TransactionDirectionEnum::INCOME)
        ->and((float) $adjustment->amount)->toBe(60.0)
        ->and((float) $adjustment->balance_after)->toBe(160.0)
        ->and($account->current_balance)->toBe('160.00');
});

test('shared balance adjustment respects viewer and editor permissions', function () {
    $viewer = User::factory()->create();
    $editor = User::factory()->create();
    $owner = User::factory()->create();

    ensureTransactionsContext($viewer, 2025);
    ensureTransactionsContext($editor, 2025);
    [$account] = seedTransactionsFixture($owner, 2025);
    shareAccountWithUser($account, $viewer, AccountMembershipRoleEnum::VIEWER);
    shareAccountWithUser($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($viewer)
        ->from(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 22,
            'type_key' => StoreTransactionRequest::BALANCE_ADJUSTMENT_TYPE_KEY,
            'account_uuid' => $account->uuid,
            'desired_balance' => 500.00,
            'description' => 'Viewer blocked adjustment',
        ])
        ->assertSessionHasErrors('account_uuid');

    $this->actingAs($editor)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 22,
            'type_key' => StoreTransactionRequest::BALANCE_ADJUSTMENT_TYPE_KEY,
            'account_uuid' => $account->uuid,
            'desired_balance' => 500.00,
            'description' => 'Editor adjustment',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $adjustment = Transaction::query()
        ->where('account_id', $account->id)
        ->where('kind', TransactionKindEnum::BALANCE_ADJUSTMENT->value)
        ->firstOrFail();

    expect($adjustment->created_by_user_id)->toBe($editor->id)
        ->and($adjustment->user_id)->toBe($owner->id);
});

test('shared account transaction payload uses the canonical shared catalog and materializes missing editor categories into it', function () {
    $editor = User::factory()->create();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    ensureTransactionsContext($editor);
    ensureTransactionsContext($viewer);

    [$sharedAccount] = seedTransactionsFixture($owner);

    $ownerCategory = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Amazon',
        'slug' => 'amazon-owner-shared',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $editorCategory = Category::query()->create([
        'user_id' => $editor->id,
        'name' => 'Barilla',
        'slug' => 'barilla-editor-shared',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $viewerCategory = Category::query()->create([
        'user_id' => $viewer->id,
        'name' => 'Viewer escluso',
        'slug' => 'viewer-escluso-shared',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    shareAccountWithUser($sharedAccount, $editor, AccountMembershipRoleEnum::EDITOR);
    shareAccountWithUser($sharedAccount, $viewer, AccountMembershipRoleEnum::VIEWER);

    $this->actingAs($editor)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.editor.accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['uuid'] === $sharedAccount->uuid
                    && $account['owner_user_id'] === $owner->id
                    && $account['is_shared'] === true
                    && $account['category_contributor_user_ids'] === [$owner->id, $editor->id]))
            ->where("monthlySheet.editor.categories.{$sharedAccount->uuid}", fn ($categories) => collect($categories)
                ->doesntContain(fn ($category) => $category['uuid'] === $ownerCategory->uuid))
            ->where("monthlySheet.editor.categories.{$sharedAccount->uuid}", fn ($categories) => collect($categories)
                ->doesntContain(fn ($category) => $category['uuid'] === $editorCategory->uuid))
            ->where("monthlySheet.editor.categories.{$sharedAccount->uuid}", fn ($categories) => collect($categories)
                ->doesntContain(fn ($category) => $category['uuid'] === $viewerCategory->uuid)));

    $this->actingAs($editor)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 22,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $sharedAccount->uuid,
            'category_uuid' => $editorCategory->uuid,
            'amount' => 19.5,
            'description' => 'Categoria editor su conto condiviso',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $transaction = Transaction::query()
        ->where('account_id', $sharedAccount->id)
        ->where('description', 'Categoria editor su conto condiviso')
        ->firstOrFail();

    expect((int) $transaction->user_id)->toBe($owner->id)
        ->and((int) $transaction->category->account_id)->toBe($sharedAccount->id)
        ->and($transaction->category->name)->toBe('Barilla')
        ->and((int) $transaction->category_id)->not->toBe($editorCategory->id);

    $this->actingAs($editor)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where("monthlySheet.editor.categories.{$sharedAccount->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['label'] === 'Spese > Barilla'))
            ->where("monthlySheet.editor.categories.{$sharedAccount->uuid}", fn ($categories) => collect($categories)
                ->filter(fn ($category) => $category['label'] === 'Spese > Barilla')
                ->count() === 1));
});

test('shared account category preview uses owner budget data for owner categories in transaction forms', function () {
    $editor = User::factory()->create();
    $owner = User::factory()->create();

    app(CategoryFoundationService::class)->ensureForUser($owner);
    ensureTransactionsContext($editor);

    [$sharedAccount] = seedTransactionsFixture($owner);

    $expenseRoot = Category::query()
        ->where('user_id', $owner->id)
        ->where('foundation_key', 'expense')
        ->whereNull('account_id')
        ->firstOrFail();

    $ownerCategory = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Amazon Preview',
        'slug' => 'amazon-preview-owner-shared',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Budget::query()->create([
        'user_id' => $owner->id,
        'category_id' => $ownerCategory->id,
        'year' => 2025,
        'month' => 3,
        'amount' => 240,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $ownerCategory->id,
        'transaction_date' => '2025-03-12',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 75,
        'currency' => 'EUR',
        'description' => 'Amazon preview shared budget',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'value_date' => '2025-03-12',
    ]);

    shareAccountWithUser($sharedAccount, $editor, AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editor)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.editor.category_overview_items', fn ($items) => collect($items)
                ->contains(fn ($item) => str_contains((string) $item['label'], 'Amazon Preview')
                    && (float) $item['budget_raw'] === 240.0)));
});

test('shared account transaction payload exposes account scoped tracked items while excluding contributor personal catalogs', function () {
    $editor = User::factory()->create();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    ensureTransactionsContext($editor);

    [$sharedAccount, $ownerCategory] = seedTransactionsFixture($owner);

    $ownerScope = Scope::query()->create([
        'user_id' => $owner->id,
        'name' => 'Scope owner',
        'type' => null,
        'color' => '#2563eb',
        'is_active' => true,
    ]);

    $editorScope = Scope::query()->create([
        'user_id' => $editor->id,
        'name' => 'Scope editor',
        'type' => null,
        'color' => '#16a34a',
        'is_active' => true,
    ]);

    $viewerScope = Scope::query()->create([
        'user_id' => $viewer->id,
        'name' => 'Scope viewer escluso',
        'type' => null,
        'color' => '#dc2626',
        'is_active' => true,
    ]);

    $ownerTrackedItem = TrackedItem::query()->create([
        'user_id' => $owner->id,
        'name' => 'Tracked owner personale',
        'slug' => 'tracked-owner-personale-shared',
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
            'transaction_category_uuids' => [$ownerCategory->uuid],
        ],
    ]);
    $ownerTrackedItem->compatibleCategories()->sync([$ownerCategory->id]);

    $editorTrackedItem = TrackedItem::query()->create([
        'user_id' => $editor->id,
        'name' => 'Tracked editor personale',
        'slug' => 'tracked-editor-personale-shared',
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
            'transaction_category_uuids' => [$ownerCategory->uuid],
        ],
    ]);
    $editorTrackedItem->compatibleCategories()->sync([$ownerCategory->id]);

    $viewerTrackedItem = TrackedItem::query()->create([
        'user_id' => $viewer->id,
        'name' => 'Tracked viewer escluso',
        'slug' => 'tracked-viewer-excluded-shared',
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
            'transaction_category_uuids' => [$ownerCategory->uuid],
        ],
    ]);
    $viewerTrackedItem->compatibleCategories()->sync([$ownerCategory->id]);

    $sharedTrackedItem = TrackedItem::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'name' => 'Tracked shared account',
        'slug' => 'tracked-shared-account',
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
            'transaction_category_uuids' => [$ownerCategory->uuid],
        ],
    ]);
    $sharedTrackedItem->compatibleCategories()->sync([$ownerCategory->id]);

    shareAccountWithUser($sharedAccount, $editor, AccountMembershipRoleEnum::EDITOR);
    shareAccountWithUser($sharedAccount, $viewer, AccountMembershipRoleEnum::VIEWER);

    $this->actingAs($editor)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.editor.accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['uuid'] === $sharedAccount->uuid
                    && $account['scope_contributor_user_ids'] === [$owner->id, $editor->id]
                    && $account['tracked_item_contributor_user_ids'] === [$owner->id, $editor->id]))
            ->where('monthlySheet.editor.scopes', fn ($scopes) => collect($scopes)
                ->contains(fn ($scope) => $scope['uuid'] === $ownerScope->uuid
                    && $scope['owner_user_id'] === $owner->id))
            ->where('monthlySheet.editor.scopes', fn ($scopes) => collect($scopes)
                ->contains(fn ($scope) => $scope['uuid'] === $editorScope->uuid
                    && $scope['owner_user_id'] === $editor->id))
            ->where('monthlySheet.editor.scopes', fn ($scopes) => collect($scopes)
                ->doesntContain(fn ($scope) => $scope['uuid'] === $viewerScope->uuid))
            ->where('monthlySheet.editor.tracked_items', fn ($trackedItems) => collect($trackedItems)
                ->contains(fn ($trackedItem) => $trackedItem['uuid'] === $sharedTrackedItem->uuid
                    && $trackedItem['owner_user_id'] === $owner->id))
            ->where('monthlySheet.editor.tracked_items', fn ($trackedItems) => collect($trackedItems)
                ->doesntContain(fn ($trackedItem) => $trackedItem['uuid'] === $ownerTrackedItem->uuid))
            ->where('monthlySheet.editor.tracked_items', fn ($trackedItems) => collect($trackedItems)
                ->doesntContain(fn ($trackedItem) => $trackedItem['uuid'] === $editorTrackedItem->uuid))
            ->where('monthlySheet.editor.tracked_items', fn ($trackedItems) => collect($trackedItems)
                ->doesntContain(fn ($trackedItem) => $trackedItem['uuid'] === $viewerTrackedItem->uuid)));
});

test('shared account editor can create and update transactions with account scoped tracked items', function () {
    $editor = User::factory()->create();
    $owner = User::factory()->create();

    ensureTransactionsContext($editor);

    [$sharedAccount, $ownerCategory] = seedTransactionsFixture($owner);

    $editorCategory = Category::query()->create([
        'user_id' => $editor->id,
        'name' => 'Categoria editor scope tracked',
        'slug' => 'categoria-editor-scope-tracked',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $ownerScope = Scope::query()->create([
        'user_id' => $owner->id,
        'name' => 'Scope owner create',
        'type' => null,
        'color' => '#2563eb',
        'is_active' => true,
    ]);

    $editorScope = Scope::query()->create([
        'user_id' => $editor->id,
        'name' => 'Scope editor update',
        'type' => null,
        'color' => '#16a34a',
        'is_active' => true,
    ]);

    $sharedTrackedItemCreate = TrackedItem::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'name' => 'Tracked shared create',
        'slug' => 'tracked-shared-create',
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
            'transaction_category_uuids' => [$ownerCategory->uuid],
        ],
    ]);
    $sharedTrackedItemCreate->compatibleCategories()->sync([$ownerCategory->id]);

    $sharedTrackedItemUpdate = TrackedItem::query()->create([
        'user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'name' => 'Tracked shared update',
        'slug' => 'tracked-shared-update',
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
            'transaction_category_uuids' => [$ownerCategory->uuid],
        ],
    ]);
    $sharedTrackedItemUpdate->compatibleCategories()->sync([$ownerCategory->id]);

    shareAccountWithUser($sharedAccount, $editor, AccountMembershipRoleEnum::EDITOR);

    $response = $this->actingAs($editor)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 23,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $sharedAccount->uuid,
            'category_uuid' => $ownerCategory->uuid,
            'scope_uuid' => $ownerScope->uuid,
            'tracked_item_uuid' => $sharedTrackedItemCreate->uuid,
            'amount' => 42.5,
            'description' => 'Create shared owner scope and tracked item',
        ]);

    $response->assertSessionHasNoErrors();

    $transaction = Transaction::query()
        ->where('account_id', $sharedAccount->id)
        ->where('description', 'Create shared owner scope and tracked item')
        ->firstOrFail();

    expect((int) $transaction->scope_id)->toBe($ownerScope->id)
        ->and((int) $transaction->tracked_item_id)->toBe($sharedTrackedItemCreate->id)
        ->and((int) $transaction->user_id)->toBe($owner->id);

    $this->actingAs($editor)
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_day' => 24,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $sharedAccount->uuid,
            'category_uuid' => $ownerCategory->uuid,
            'scope_uuid' => $editorScope->uuid,
            'tracked_item_uuid' => $sharedTrackedItemUpdate->uuid,
            'amount' => 51.0,
            'description' => 'Update shared editor scope and tracked item',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $transaction->refresh();

    expect((int) $transaction->scope_id)->toBe($editorScope->id)
        ->and((int) $transaction->tracked_item_id)->toBe($sharedTrackedItemUpdate->id)
        ->and((int) $transaction->category->account_id)->toBe($sharedAccount->id)
        ->and($transaction->category->name)->toBe('Spese correnti')
        ->and((int) $transaction->category_id)->not->toBe($ownerCategory->id)
        ->and($transaction->description)->toBe('Update shared editor scope and tracked item');
});

test('shared account transactions reject contributor personal tracked items that are not in the account catalog', function () {
    $editor = User::factory()->create();
    $owner = User::factory()->create();

    ensureTransactionsContext($editor);

    [$sharedAccount, $ownerCategory] = seedTransactionsFixture($owner);

    $editorTrackedItem = TrackedItem::query()->create([
        'user_id' => $editor->id,
        'name' => 'Tracked editor solo personale',
        'slug' => 'tracked-editor-solo-personale',
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
            'transaction_category_uuids' => [$ownerCategory->uuid],
        ],
    ]);
    $editorTrackedItem->compatibleCategories()->sync([$ownerCategory->id]);

    shareAccountWithUser($sharedAccount, $editor, AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editor)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 26,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $sharedAccount->uuid,
            'category_uuid' => $ownerCategory->uuid,
            'tracked_item_uuid' => $editorTrackedItem->uuid,
            'amount' => 38.0,
            'description' => 'Tracked personale non ammesso nel conto shared',
        ])
        ->assertSessionHasErrors('tracked_item_uuid');
});

test('shared account payload keeps legacy personal tracked items already used by transactions', function () {
    $editor = User::factory()->create();
    $owner = User::factory()->create();

    ensureTransactionsContext($editor);

    [$sharedAccount, $ownerCategory] = seedTransactionsFixture($owner);

    $legacyTrackedItem = TrackedItem::query()->create([
        'user_id' => $owner->id,
        'name' => 'Tracked legacy personale',
        'slug' => 'tracked-legacy-personale',
        'type' => null,
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
            'transaction_category_uuids' => [$ownerCategory->uuid],
        ],
    ]);
    $legacyTrackedItem->compatibleCategories()->sync([$ownerCategory->id]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'account_id' => $sharedAccount->id,
        'category_id' => $ownerCategory->id,
        'tracked_item_id' => $legacyTrackedItem->id,
        'transaction_date' => '2025-03-19',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 27,
        'currency' => 'EUR',
        'description' => 'Legacy tracked item shared transaction',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'value_date' => '2025-03-19',
    ]);

    shareAccountWithUser($sharedAccount, $editor, AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editor)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.editor.tracked_items', fn ($trackedItems) => collect($trackedItems)
                ->contains(fn ($trackedItem) => $trackedItem['uuid'] === $legacyTrackedItem->uuid)));
});

test('shared transactions use the account scoped tracked item after tracked item materialization', function () {
    $editor = User::factory()->create();
    $owner = User::factory()->create();

    ensureTransactionsContext($editor);

    [$sharedAccount, $ownerCategory] = seedTransactionsFixture($owner);

    $editorCategory = Category::query()->create([
        'user_id' => $editor->id,
        'name' => 'Spese correnti',
        'slug' => 'spese-correnti-editor-tracked-bridge',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $editorTrackedItem = TrackedItem::query()->create([
        'user_id' => $editor->id,
        'name' => 'Eurospin',
        'slug' => 'eurospin-shared-bridge-tracked',
        'type' => 'negozio',
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
            'transaction_category_uuids' => [$editorCategory->uuid],
        ],
    ]);
    $editorTrackedItem->compatibleCategories()->sync([$editorCategory->id]);

    shareAccountWithUser($sharedAccount, $editor, AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editor)
        ->post(route('tracked-items.materialize', $sharedAccount), [
            'source_tracked_item_uuid' => $editorTrackedItem->uuid,
        ])
        ->assertRedirect(route('tracked-items.edit'));

    $sharedTrackedItem = TrackedItem::query()
        ->where('account_id', $sharedAccount->id)
        ->where('slug', 'eurospin-shared-bridge-tracked')
        ->firstOrFail();

    $this->actingAs($editor)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 27,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $sharedAccount->uuid,
            'category_uuid' => $ownerCategory->uuid,
            'tracked_item_uuid' => $sharedTrackedItem->uuid,
            'amount' => 19.9,
            'description' => 'Tracked item materialized transaction',
        ])
        ->assertSessionHasNoErrors();

    $transaction = Transaction::query()
        ->where('account_id', $sharedAccount->id)
        ->where('description', 'Tracked item materialized transaction')
        ->firstOrFail();

    expect((int) $transaction->tracked_item_id)->toBe($sharedTrackedItem->id)
        ->and($transaction->trackedItem?->account_id)->toBe($sharedAccount->id)
        ->and((int) $transaction->tracked_item_id)->not->toBe($editorTrackedItem->id);
});

test('shared accounts bootstrap and use a canonical account category taxonomy for reports and selects', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();

    app(CategoryFoundationService::class)->ensureForUser($owner);
    app(CategoryFoundationService::class)->ensureForUser($editor);

    ensureTransactionsContext($editor, 2025);

    [$account] = seedTransactionsFixture($owner);

    $incomeRoot = Category::query()
        ->where('user_id', $owner->id)
        ->where('foundation_key', 'income')
        ->whereNull('account_id')
        ->firstOrFail();

    $ownerSalary = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $incomeRoot->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio-owner-shared',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $ownerAmazon = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $ownerSalary->id,
        'name' => 'Amazon Spa',
        'slug' => 'amazon-spa-owner-shared',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $editorIncomeRoot = Category::query()->create([
        'user_id' => $editor->id,
        'name' => 'Entrate editor',
        'slug' => 'entrate-editor-shared',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $editorSalary = Category::query()->create([
        'user_id' => $editor->id,
        'parent_id' => $editorIncomeRoot->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio-editor-shared',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $editorBarilla = Category::query()->create([
        'user_id' => $editor->id,
        'parent_id' => $editorSalary->id,
        'name' => 'Barilla Spa',
        'slug' => 'barilla-spa-editor-shared',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'account_id' => $account->id,
        'category_id' => $ownerAmazon->id,
        'transaction_date' => '2025-05-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 1250,
        'currency' => 'EUR',
        'description' => 'Amazon salary',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'value_date' => '2025-05-10',
    ]);

    shareAccountWithUser($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editor)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 5,
        ]), [
            'transaction_day' => 11,
            'type_key' => CategoryGroupTypeEnum::INCOME->value,
            'account_uuid' => $account->uuid,
            'category_uuid' => $editorBarilla->uuid,
            'amount' => 980,
            'description' => 'Barilla salary',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($editor)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 5,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['label'] === 'Entrate > Stipendio > Amazon Spa'))
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['label'] === 'Entrate > Stipendio > Barilla Spa'))
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->filter(fn ($category) => $category['label'] === 'Entrate')
                ->count() === 1)
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Amazon salary'
                    && $transaction['created_by']['uuid'] === $owner->uuid))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Barilla salary'
                    && $transaction['created_by']['uuid'] === $editor->uuid)));

    expect(Category::query()->where('account_id', $account->id)->where('name', 'Entrate')->count())->toBe(1)
        ->and(Category::query()->where('account_id', $account->id)->where('name', 'Stipendio')->count())->toBe(1)
        ->and(Category::query()->where('account_id', $account->id)->where('name', 'Amazon Spa')->count())->toBe(1)
        ->and(Category::query()->where('account_id', $account->id)->where('name', 'Barilla Spa')->count())->toBe(1)
        ->and(Transaction::query()->where('account_id', $account->id)->where('description', 'Amazon salary')->firstOrFail()->category->account_id)->toBe($account->id)
        ->and(Transaction::query()->where('account_id', $account->id)->where('description', 'Barilla salary')->firstOrFail()->category->account_id)->toBe($account->id);
});

test('shared taxonomy canonicalization ignores incoherent personal child metadata and prunes unused spurious branches', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();

    app(CategoryFoundationService::class)->ensureForUser($owner);
    app(CategoryFoundationService::class)->ensureForUser($editor);

    ensureTransactionsContext($editor, 2025);

    [$account] = seedTransactionsFixture($owner);

    $incomeRoot = Category::query()
        ->where('user_id', $owner->id)
        ->where('foundation_key', 'income')
        ->whereNull('account_id')
        ->firstOrFail();

    $incoherentSalary = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $incomeRoot->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio-incoerente-shared',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $amazon = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $incoherentSalary->id,
        'name' => 'Amazon Spa',
        'slug' => 'amazon-spa-incoerente-shared',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'account_id' => $account->id,
        'category_id' => $amazon->id,
        'transaction_date' => '2025-05-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 1250,
        'currency' => 'EUR',
        'description' => 'Amazon salary canonical',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'value_date' => '2025-05-10',
    ]);

    shareAccountWithUser($account, $editor, AccountMembershipRoleEnum::EDITOR);

    app(SharedAccountCategoryTaxonomyService::class)->ensureForAccount($account);

    $spuriousSharedParent = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => Category::query()
            ->where('account_id', $account->id)
            ->whereNull('parent_id')
            ->where('group_type', CategoryGroupTypeEnum::EXPENSE->value)
            ->value('id'),
        'name' => 'Stipendio',
        'slug' => 'shared-spese-stipendio-spurio',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $spuriousSharedParent->id,
        'name' => 'Legacy spurio',
        'slug' => 'shared-spese-stipendio-legacy-spurio',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($editor)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 5,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['label'] === 'Entrate > Stipendio > Amazon Spa'))
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->doesntContain(fn ($category) => $category['label'] === 'Spese > Stipendio'))
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->doesntContain(fn ($category) => $category['label'] === 'Spese > Stipendio > Legacy spurio')));

    expect(Category::query()->where('account_id', $account->id)->where('name', 'Stipendio')->where('group_type', CategoryGroupTypeEnum::INCOME->value)->count())->toBe(1)
        ->and(Category::query()->where('account_id', $account->id)->where('name', 'Stipendio')->where('group_type', CategoryGroupTypeEnum::EXPENSE->value)->count())->toBe(0)
        ->and(Category::query()->where('account_id', $account->id)->where('slug', 'shared-spese-stipendio-spurio')->exists())->toBeFalse()
        ->and(Transaction::query()->where('description', 'Amazon salary canonical')->firstOrFail()->category->group_type->value)->toBe(CategoryGroupTypeEnum::INCOME->value);
});

test('shared account catalog survives share removal and is reused on re-share without duplicates', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $newEditor = User::factory()->create();

    app(CategoryFoundationService::class)->ensureForUser($owner);
    app(CategoryFoundationService::class)->ensureForUser($editor);
    app(CategoryFoundationService::class)->ensureForUser($newEditor);

    ensureTransactionsContext($editor, 2025);
    ensureTransactionsContext($newEditor, 2025);

    [$account] = seedTransactionsFixture($owner, 2025);

    $editorIncomeRoot = Category::query()->create([
        'user_id' => $editor->id,
        'name' => 'Entrate editor lifecycle',
        'slug' => 'entrate-editor-lifecycle',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $editorSalary = Category::query()->create([
        'user_id' => $editor->id,
        'parent_id' => $editorIncomeRoot->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio-editor-lifecycle',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $editorBarilla = Category::query()->create([
        'user_id' => $editor->id,
        'parent_id' => $editorSalary->id,
        'name' => 'Barilla Spa',
        'slug' => 'barilla-spa-editor-lifecycle',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $membership = shareAccountWithUser($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($editor)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 5,
        ]), [
            'transaction_day' => 12,
            'type_key' => CategoryGroupTypeEnum::INCOME->value,
            'account_uuid' => $account->uuid,
            'category_uuid' => $editorBarilla->uuid,
            'amount' => 980,
            'description' => 'Barilla lifecycle salary',
        ])
        ->assertSessionHasNoErrors();

    $sharedBarilla = Transaction::query()
        ->where('account_id', $account->id)
        ->where('description', 'Barilla lifecycle salary')
        ->firstOrFail()
        ->category()
        ->firstOrFail();

    expect((int) $sharedBarilla->account_id)->toBe($account->id)
        ->and($sharedBarilla->name)->toBe('Barilla Spa')
        ->and(app(SharedAccountCategoryTaxonomyService::class)->usesAccountScopedCatalog($account))->toBeTrue()
        ->and(Category::query()->ownedBy($owner->id)->where('name', 'Barilla Spa')->doesntExist())->toBeTrue()
        ->and(Category::query()->ownedBy($newEditor->id)->where('name', 'Barilla Spa')->doesntExist())->toBeTrue();

    app(AccountMembershipLifecycleService::class)->revoke(
        $membership->fresh(),
        $owner,
    );

    $account->refresh();

    expect(app(SharedAccountCategoryTaxonomyService::class)->isSharedAccount($account))->toBeFalse()
        ->and(app(SharedAccountCategoryTaxonomyService::class)->usesAccountScopedCatalog($account))->toBeTrue()
        ->and(Category::query()->where('account_id', $account->id)->where('name', 'Barilla Spa')->count())->toBe(1);

    $this->actingAs($owner)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 5,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['label'] === 'Entrate > Stipendio > Barilla Spa'))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Barilla lifecycle salary'
                    && $transaction['category_path'] === 'Entrate > Stipendio > Barilla Spa')));

    $this->actingAs($owner)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 5,
        ]), [
            'transaction_day' => 13,
            'type_key' => CategoryGroupTypeEnum::INCOME->value,
            'account_uuid' => $account->uuid,
            'category_uuid' => $sharedBarilla->uuid,
            'amount' => 1010,
            'description' => 'Owner after revoke uses shared catalog',
        ])
        ->assertSessionHasNoErrors();

    expect(Transaction::query()
        ->where('account_id', $account->id)
        ->where('description', 'Owner after revoke uses shared catalog')
        ->firstOrFail()
        ->category()
        ->firstOrFail()
        ->account_id)->toBe($account->id);

    shareAccountWithUser($account, $newEditor, AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($newEditor)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 5,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['label'] === 'Entrate > Stipendio > Barilla Spa'))
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->filter(fn ($category) => $category['label'] === 'Entrate > Stipendio > Barilla Spa')
                ->count() === 1));

    expect(Category::query()->where('account_id', $account->id)->where('name', 'Barilla Spa')->count())->toBe(1);
});

test('manual transaction form cannot create an opening balance kind', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    [$account, $category] = seedTransactionsFixture($user);

    $this->actingAs($user)
        ->from(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 22,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $account->uuid,
            'category_uuid' => $category->uuid,
            'amount' => 32.4,
            'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        ])
        ->assertSessionHasErrors('kind');

    $this->assertDatabaseMissing('transactions', [
        'user_id' => $user->id,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => 32.4,
    ]);
});

test('cash accounts cannot be driven below zero by new transactions', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

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

    $cashAccountType = AccountType::query()->create([
        'code' => 'cash_account',
        'name' => 'Contanti',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $cashAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $cashAccountType->id,
        'name' => 'Cassa contanti',
        'currency' => 'EUR',
        'opening_balance' => 50,
        'current_balance' => 50,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spesa cassa',
        'slug' => 'spesa-cassa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->from(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 12,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $cashAccount->id,
            'category_id' => $category->id,
            'amount' => 60,
            'description' => 'Spesa oltre cassa',
            'notes' => null,
        ])
        ->assertSessionHasErrors('amount')
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $this->assertDatabaseMissing('transactions', [
        'user_id' => $user->id,
        'description' => 'Spesa oltre cassa',
    ]);
});

test('transactions can be updated from the monthly sheet', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account, $category, $trackedItem] = seedTransactionsFixture($user);

    $transaction = Transaction::query()
        ->where('user_id', $user->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $this->actingAs($user)
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_day' => 19,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'tracked_item_id' => $trackedItem->id,
            'amount' => 99.9,
            'description' => 'Spesa aggiornata dal foglio',
            'notes' => 'Aggiornata',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'transaction_date' => '2025-03-19 00:00:00',
        'amount' => 99.9,
        'description' => 'Spesa aggiornata dal foglio',
        'notes' => 'Aggiornata',
        'tracked_item_id' => $trackedItem->id,
    ]);
});

test('expense transactions can be moved to another month without changing economic fields', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account, $category, $trackedItem] = seedTransactionsFixture($user);

    $transaction = Transaction::query()
        ->where('user_id', $user->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $originalCount = Transaction::query()->count();
    $originalAmount = (float) $transaction->amount;
    $originalDescription = $transaction->description;
    $originalTrackedItemId = $transaction->tracked_item_id;
    $originalAccountId = $transaction->account_id;
    $originalCategoryId = $transaction->category_id;

    $response = $this->actingAs($user)
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_day' => 7,
            'target_month' => 4,
            'type_key' => StoreTransactionRequest::MOVE_TYPE_KEY,
        ]);

    $response->assertSessionHasNoErrors();
    expect($response->status())->toBe(302);

    $transaction->refresh();

    expect(Transaction::query()->count())->toBe($originalCount)
        ->and($transaction->transaction_date?->toDateString())->toBe('2025-04-07')
        ->and((float) $transaction->amount)->toBe($originalAmount)
        ->and($transaction->description)->toBe($originalDescription)
        ->and($transaction->tracked_item_id)->toBe($originalTrackedItemId)
        ->and($transaction->account_id)->toBe($originalAccountId)
        ->and($transaction->category_id)->toBe($originalCategoryId);
});

test('move mode rejects the current transaction date', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    seedTransactionsFixture($user);

    $transaction = Transaction::query()
        ->where('user_id', $user->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $response = $this->actingAs($user)
        ->from(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_date' => '2025-03-18',
            'type_key' => StoreTransactionRequest::MOVE_TYPE_KEY,
        ]);

    $response
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSessionHasErrors([
            'transaction_date' => __('transactions.validation.move_same_date'),
        ]);

    expect($transaction->fresh()->transaction_date?->toDateString())->toBe('2025-03-18');
});

test('move mode rejects years that are not available for the user', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    seedTransactionsFixture($user);

    $transaction = Transaction::query()
        ->where('user_id', $user->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $response = $this->actingAs($user)
        ->from(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_date' => '2027-04-07',
            'type_key' => StoreTransactionRequest::MOVE_TYPE_KEY,
        ]);

    $response->assertSessionHasErrors('transaction_date');

    expect($transaction->fresh()->transaction_date?->toDateString())->toBe('2025-03-18');
});

test('move mode is allowed for manual income updates on another available year but rejected for create transfer and recurring updates', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account, $category, $trackedItem, $destinationAccount] = seedTransactionsFixture($user);
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    $incomeCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Stipendio base',
        'slug' => "stipendio-base-transazioni-{$user->id}",
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    $incomeTransaction = Transaction::query()->create([
        'user_id' => $user->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $incomeCategory->id,
        'tracked_item_id' => null,
        'transaction_date' => '2025-03-20',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 350,
        'currency' => 'EUR',
        'description' => 'Entrata da non spostare',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'value_date' => '2025-03-20',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 20,
            'target_month' => 4,
            'type_key' => StoreTransactionRequest::MOVE_TYPE_KEY,
        ])
        ->assertSessionHasErrors('type_key');

    $response = $this->actingAs($user)
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $incomeTransaction->uuid,
        ]), [
            'transaction_date' => '2026-04-08',
            'type_key' => StoreTransactionRequest::MOVE_TYPE_KEY,
        ]);

    $response->assertSessionHasNoErrors();
    expect($response->status())->toBe(302);

    expect($incomeTransaction->fresh()->transaction_date?->toDateString())->toBe('2026-04-08');

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 24,
            'type_key' => CategoryGroupTypeEnum::TRANSFER->value,
            'account_id' => $account->id,
            'destination_account_id' => $destinationAccount->id,
            'amount' => 150.75,
            'description' => 'Giroconto da non spostare',
        ]);

    $transferTransaction = Transaction::query()
        ->where('account_id', $account->id)
        ->where('description', 'Giroconto da non spostare')
        ->where('direction', TransactionDirectionEnum::EXPENSE->value)
        ->firstOrFail();

    $this->actingAs($user)
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transferTransaction->uuid,
        ]), [
            'transaction_day' => 9,
            'target_month' => 4,
            'type_key' => StoreTransactionRequest::MOVE_TYPE_KEY,
        ])
        ->assertSessionHasErrors('type_key');

    $scheduledTransaction = Transaction::query()->create([
        'user_id' => $user->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'tracked_item_id' => null,
        'transaction_date' => '2025-03-22',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::SCHEDULED->value,
        'amount' => 80,
        'currency' => 'EUR',
        'description' => 'Ricorrenza da non spostare',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'value_date' => '2025-03-22',
    ]);

    $this->actingAs($user)
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $scheduledTransaction->uuid,
        ]), [
            'transaction_date' => '2025-04-10',
            'type_key' => StoreTransactionRequest::MOVE_TYPE_KEY,
        ])
        ->assertSessionHasErrors('type_key');
});

test('manual transactions are soft deleted from the monthly sheet', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    seedTransactionsFixture($user);

    $transaction = Transaction::query()
        ->where('user_id', $user->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $this->actingAs($user)
        ->delete(route('transactions.destroy', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]))
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSessionHas('success', __('transactions.flash.deleted'));

    expect(Transaction::withTrashed()->findOrFail($transaction->id)->trashed())->toBeTrue();
});

test('manual transactions can be restored after soft delete', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    seedTransactionsFixture($user);

    $transaction = Transaction::query()
        ->where('user_id', $user->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $transaction->delete();

    $this->actingAs($user)
        ->patch(route('transactions.restore', [
            'year' => 2025,
            'month' => 3,
            'transactionUuid' => $transaction->uuid,
        ]))
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSessionHas('success', __('transactions.flash.restored'));

    expect(Transaction::query()->findOrFail($transaction->id)->trashed())->toBeFalse();
});

test('manual transactions can be permanently deleted after soft delete', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    seedTransactionsFixture($user);

    $transaction = Transaction::query()
        ->where('user_id', $user->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $transaction->delete();

    $this->actingAs($user)
        ->delete(route('transactions.force-destroy', [
            'year' => 2025,
            'month' => 3,
            'transactionUuid' => $transaction->uuid,
        ]))
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSessionHas('success', __('transactions.flash.force_deleted'));

    expect(Transaction::withTrashed()->where('uuid', $transaction->uuid)->exists())->toBeFalse();
});

test('scheduled transactions cannot be deleted and expose recurring management links in payload', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    [$account, $category, $trackedItem] = seedTransactionsFixture($user);
    [$entry, $occurrence] = createRecurringPreviewFixture($user, $account, $category, $trackedItem, [
        'start_date' => '2025-03-20',
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'tracked_item_id' => $trackedItem->id,
        'transaction_date' => '2025-03-20',
        'value_date' => '2025-03-20',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::SCHEDULED->value,
        'amount' => 75,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Scheduled rent',
        'balance_after' => 820,
        'recurring_entry_occurrence_id' => $occurrence->id,
    ]);

    $occurrence->update([
        'converted_transaction_id' => $transaction->id,
        'status' => RecurringOccurrenceStatusEnum::COMPLETED->value,
    ]);

    $this->actingAs($user)
        ->from(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->delete(route('transactions.destroy', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]))
        ->assertSessionHasErrors('transaction');

    expect($transaction->fresh()->trashed())->toBeFalse();

    $this->actingAs($user)
        ->get(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($item) => $item['uuid'] === $transaction->uuid
                    && $item['can_delete'] === false
                    && $item['can_edit'] === false
                    && $item['recurring_entry_show_url'] === route('recurring-entries.show', $entry->uuid)))
        );
});

test('opening balance transactions cannot be deleted', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    [$account] = seedTransactionsFixture($user, 2026);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2026-01-01',
        'value_date' => '2026-01-01',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => 250,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'balance_after' => 250,
    ]);

    $this->actingAs($user)
        ->from(route('transactions.show', ['year' => 2026, 'month' => 1]))
        ->delete(route('transactions.destroy', [
            'year' => 2026,
            'month' => 1,
            'transaction' => $transaction->uuid,
        ]))
        ->assertSessionHasErrors('transaction');

    expect($transaction->fresh()->trashed())->toBeFalse();
});

test('refund transactions cannot be deleted', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    [$account, $category] = seedTransactionsFixture($user);

    $original = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2025-03-20',
        'value_date' => '2025-03-20',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 20,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Original',
        'balance_after' => 800,
    ]);

    $refund = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2025-03-21',
        'value_date' => '2025-03-21',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::REFUND->value,
        'amount' => 20,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Refund',
        'balance_after' => 820,
        'refunded_transaction_id' => $original->id,
    ]);

    $this->actingAs($user)
        ->from(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->delete(route('transactions.destroy', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $refund->uuid,
        ]))
        ->assertSessionHasErrors('transaction');

    expect($refund->fresh()->trashed())->toBeFalse();
});

test('transactions payload exposes refund availability only for editable non-technical rows', function () {
    $user = User::factory()->create();
    [$account, $category] = seedTransactionsFixture($user, 2026);

    $editable = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-01-13',
        'value_date' => '2026-01-13',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 125,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Editable refund target',
        'balance_after' => 875,
    ]);

    $technical = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2026-01-01',
        'value_date' => '2026-01-01',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => 1000,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'balance_after' => 1000,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', ['year' => 2026, 'month' => 1]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['uuid'] === $editable->uuid
                    && $transaction['can_refund'] === true
                    && $transaction['is_refunded'] === false))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['uuid'] === $technical->uuid
                    && $transaction['can_refund'] === false))
        );
});

test('editable personal transactions can be refunded with an autonomous refund date', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    [$account, $category, $trackedItem] = seedTransactionsFixture($user);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'tracked_item_id' => $trackedItem->id,
        'transaction_date' => '2025-03-20',
        'value_date' => '2025-03-20',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 80,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Personal refund fixture',
        'balance_after' => 755,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.refund', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_date' => '2025-04-02',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 4,
        ]))
        ->assertSessionHas('success', __('transactions.flash.refund_created'));

    $refund = $transaction->fresh()->refundTransaction;

    expect($refund)->not->toBeNull()
        ->and($refund->transaction_date?->toDateString())->toBe('2025-04-02')
        ->and($refund->direction)->toBe(TransactionDirectionEnum::INCOME)
        ->and((float) $refund->amount)->toBe(80.0)
        ->and($refund->account_id)->toBe($account->id)
        ->and($refund->category_id)->toBe($category->id)
        ->and($refund->tracked_item_id)->toBe($trackedItem->id)
        ->and($refund->refunded_transaction_id)->toBe($transaction->id);
});

test('a refunded original transaction cannot be deleted while the linked refund exists and the refund can be undone', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    [$account, $category] = seedTransactionsFixture($user);

    $original = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2025-03-20',
        'value_date' => '2025-03-20',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 80,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Original refunded fixture',
        'balance_after' => 755,
    ]);

    $refund = app(TransactionRefundService::class)->refund($original, [
        'transaction_date' => '2025-03-22',
        'description' => 'Refund fixture',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->delete(route('transactions.destroy', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $original->uuid,
        ]))
        ->assertSessionHasErrors('transaction');

    expect($original->fresh()->trashed())->toBeFalse();

    $this->actingAs($user)
        ->get(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['uuid'] === $original->uuid
                    && $transaction['can_delete'] === false
                    && $transaction['is_refunded'] === true
                    && ($transaction['refund_transaction']['uuid'] ?? null) === $refund->uuid))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['uuid'] === $refund->uuid
                    && $transaction['type'] === __('transactions.enums.kind.refund')
                    && ($transaction['can_undo_refund'] ?? false) === true
                    && ($transaction['refunded_transaction']['uuid'] ?? null) === $original->uuid))
        );
});

test('refund transactions can be undone from the monthly register', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    [$account, $category] = seedTransactionsFixture($user);

    $original = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2025-03-20',
        'value_date' => '2025-03-20',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 80,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Undo refund original fixture',
        'balance_after' => 755,
    ]);

    $refund = app(TransactionRefundService::class)->refund($original, [
        'transaction_date' => '2025-03-22',
        'description' => 'Undo refund fixture',
    ]);

    $this->actingAs($user)
        ->delete(route('transactions.undo-refund', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $refund->uuid,
        ]))
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSessionHas('success', __('transactions.flash.refund_undone'));

    expect(Transaction::query()->whereKey($refund->id)->exists())->toBeFalse()
        ->and($original->fresh()->refundTransaction)->toBeNull();

    $this->actingAs($user)
        ->get(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['uuid'] === $original->uuid
                    && $transaction['can_delete'] === true
                    && $transaction['is_refunded'] === false
                    && ($transaction['refund_transaction'] ?? null) === null))
        );
});

test('monthly summary compensates expenses fully when an expense is fully refunded', function () {
    $user = User::factory()->create();
    [$account, $category] = seedTransactionsFixture($user, 2026);

    $expense = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-01-12',
        'value_date' => '2026-01-12',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 100,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Expense fully refunded',
        'balance_after' => 900,
    ]);

    app(TransactionRefundService::class)->refund($expense, [
        'transaction_date' => '2026-01-15',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', ['year' => 2026, 'month' => 1]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.totals.actual_income_raw', fn ($value) => (float) $value === 0.0)
            ->where('monthlySheet.totals.actual_expense_raw', fn ($value) => (float) $value === 0.0)
            ->where('monthlySheet.totals.net_actual_raw', fn ($value) => (float) $value === 0.0)
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'expense' && (float) $card['actual_raw'] === 0.0))
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'income' && (float) $card['actual_raw'] === 0.0))
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'net' && (float) $card['actual_raw'] === 0.0))
        );
});

test('monthly summary compensates expenses partially when an expense is partially refunded', function () {
    $user = User::factory()->create();
    [$account, $category] = seedTransactionsFixture($user, 2026);

    $expense = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-01-12',
        'value_date' => '2026-01-12',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 100,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Expense partially refunded',
        'balance_after' => 900,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-01-20',
        'value_date' => '2026-01-20',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::REFUND->value,
        'amount' => 40,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Partial refund',
        'balance_after' => 940,
        'refunded_transaction_id' => $expense->id,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', ['year' => 2026, 'month' => 1]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.totals.actual_income_raw', fn ($value) => (float) $value === 0.0)
            ->where('monthlySheet.totals.actual_expense_raw', fn ($value) => (float) $value === 60.0)
            ->where('monthlySheet.totals.net_actual_raw', fn ($value) => (float) $value === -60.0)
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'expense' && (float) $card['actual_raw'] === 60.0))
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'income' && (float) $card['actual_raw'] === 0.0))
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'net' && (float) $card['actual_raw'] === -60.0))
        );
});

test('monthly summary keeps standard income and expense totals unchanged without refunds', function () {
    $user = User::factory()->create();
    [$account, $category] = seedTransactionsFixture($user, 2026);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-01-10',
        'value_date' => '2026-01-10',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 75,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Regular income',
        'balance_after' => 75,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2026-01-11',
        'value_date' => '2026-01-11',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 20,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Regular expense',
        'balance_after' => 55,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', ['year' => 2026, 'month' => 1]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.totals.actual_income_raw', fn ($value) => (float) $value === 75.0)
            ->where('monthlySheet.totals.actual_expense_raw', fn ($value) => (float) $value === 20.0)
            ->where('monthlySheet.totals.net_actual_raw', fn ($value) => (float) $value === 55.0)
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'income' && (float) $card['actual_raw'] === 75.0))
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'expense' && (float) $card['actual_raw'] === 20.0))
            ->where('monthlySheet.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'net' && (float) $card['actual_raw'] === 55.0))
        );
});

test('cash account transactions can be refunded from the monthly register', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    [, $category] = seedTransactionsFixture($user);

    $cashType = AccountType::query()->firstOrCreate([
        'code' => 'cash_account',
    ], [
        'name' => 'Cassa contanti',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $cashAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $cashType->id,
        'name' => 'Cassa contanti',
        'currency' => 'EUR',
        'opening_balance' => 200,
        'current_balance' => 120,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $cashAccount->id,
        'category_id' => $category->id,
        'transaction_date' => '2025-03-12',
        'value_date' => '2025-03-12',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 25,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Cash refund fixture',
        'balance_after' => 95,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.refund', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_date' => '2025-03-15',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSessionHas('success', __('transactions.flash.refund_created'));

    $refund = $transaction->fresh()->refundTransaction;

    expect($refund)->not->toBeNull()
        ->and($refund->account_id)->toBe($cashAccount->id)
        ->and($refund->direction)->toBe(TransactionDirectionEnum::INCOME)
        ->and((float) $refund->amount)->toBe(25.0)
        ->and($refund->transaction_date?->toDateString())->toBe('2025-03-15');
});

test('shared account editors can refund shared transactions from the monthly register', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $owner = User::factory()->create();
    $editor = User::factory()->create();
    ensureTransactionsContext($editor);
    [$account, $category] = seedTransactionsFixture($owner);

    shareAccountWithUser($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $transaction = Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2025-03-26',
        'value_date' => '2025-03-26',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 41,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Shared refund fixture',
        'balance_after' => 714,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
    ]);

    $this->actingAs($editor)
        ->post(route('transactions.refund', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->uuid,
        ]), [
            'transaction_date' => '2025-03-28',
        ])
        ->assertStatus(302)
        ->assertSessionHas('success', __('transactions.flash.refund_created'));

    $refund = $transaction->fresh()->refundTransaction;

    expect($refund)->not->toBeNull()
        ->and($refund->account_id)->toBe($account->id)
        ->and($refund->direction)->toBe(TransactionDirectionEnum::INCOME)
        ->and((float) $refund->amount)->toBe(41.0)
        ->and($refund->transaction_date?->toDateString())->toBe('2025-03-28');
});

test('technical transactions cannot be refunded from the monthly register', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    [$account] = seedTransactionsFixture($user, 2026);

    $openingBalance = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2026-01-01',
        'value_date' => '2026-01-01',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => 250,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'balance_after' => 250,
    ]);

    $this->actingAs($user)
        ->from(route('transactions.show', ['year' => 2026, 'month' => 1]))
        ->post(route('transactions.refund', [
            'year' => 2026,
            'month' => 1,
            'transaction' => $openingBalance->uuid,
        ]), [
            'transaction_date' => '2026-01-05',
        ])
        ->assertSessionHasErrors('transaction');

    expect($openingBalance->fresh()->refundTransaction)->toBeNull();
});

test('transactions payload includes deleted rows separately without affecting active accounting totals', function () {
    $user = User::factory()->create();

    seedTransactionsFixture($user);

    $deletedTransaction = Transaction::query()
        ->where('user_id', $user->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $deletedTransaction->delete();

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.meta.transactions_count', 1)
            ->where('monthlySheet.meta.deleted_transactions_count', 1)
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->doesntContain(fn ($transaction) => $transaction['uuid'] === $deletedTransaction->uuid))
            ->where('monthlySheet.deleted_transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['uuid'] === $deletedTransaction->uuid
                    && $transaction['is_deleted'] === true
                    && $transaction['can_restore'] === true
                    && $transaction['can_delete'] === false
                    && $transaction['can_force_delete'] === true))
        );
});

test('january monthly sheet shows opening balance rows but excludes them from operational totals', function () {
    $user = User::factory()->create();
    [$account] = seedTransactionsFixture($user, 2026);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2026-01-01',
        'value_date' => '2026-01-01',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => 250,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'balance_after' => 250,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.meta.transactions_count', 2)
            ->where('monthlySheet.meta.last_balance_raw', 250)
            ->where('monthlySheet.totals.actual_income_raw', 0)
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['is_opening_balance'] === true
                    && $transaction['kind'] === TransactionKindEnum::OPENING_BALANCE->value
                    && $transaction['date'] === '2026-01-01'
                    && $transaction['date_label'] === '01 gen'
                    && $transaction['can_edit'] === false
                    && (float) $transaction['amount_raw'] === 250.0
                    && $transaction['account_label'] === 'Conto widget')));
});

test('all accounts monthly sheet sums opening balances across multiple accounts', function () {
    $user = User::factory()->create();
    ensureTransactionsContext($user, 2026);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'summary-opening-balance-checking',
    ], [
        'name' => 'Summary opening balance checking',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $cashAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Cassa contanti',
        'currency' => 'EUR',
        'opening_balance' => 70,
        'current_balance' => 70,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $mediobancaAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto MedioBanca Premier',
        'currency' => 'EUR',
        'opening_balance' => 4500.70,
        'current_balance' => 4500.70,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $unicreditAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'UniCredit',
        'currency' => 'EUR',
        'opening_balance' => 5000,
        'current_balance' => 5000,
        'is_manual' => true,
        'is_active' => true,
    ]);

    foreach ([
        [$cashAccount, 70.0],
        [$mediobancaAccount, 4500.70],
        [$unicreditAccount, 5000.0],
    ] as [$account, $balance]) {
        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_date' => '2026-01-01',
            'value_date' => '2026-01-01',
            'direction' => TransactionDirectionEnum::INCOME->value,
            'kind' => TransactionKindEnum::OPENING_BALANCE->value,
            'amount' => $balance,
            'currency' => 'EUR',
            'source_type' => TransactionSourceTypeEnum::GENERATED->value,
            'status' => TransactionStatusEnum::CONFIRMED->value,
            'balance_after' => $balance,
        ]);
    }

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.meta.transactions_count', 3)
            ->where('monthlySheet.totals.actual_income_raw', 0)
            ->where('monthlySheet.totals.actual_expense_raw', 0)
            ->where('monthlySheet.totals.net_actual_raw', 0)
            ->where('monthlySheet.meta.last_balance_raw', 9570.7));
});

test('all accounts monthly sheet carries ending balances into later months without new movements', function () {
    $user = User::factory()->create();
    ensureTransactionsContext($user, 2026);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'summary-carry-forward-checking',
    ], [
        'name' => 'Summary carry forward checking',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $accounts = [
        ['name' => 'Cassa contanti', 'balance' => 70.0],
        ['name' => 'Conto MedioBanca Premier', 'balance' => 4500.70],
        ['name' => 'UniCredit', 'balance' => 5000.0],
    ];

    foreach ($accounts as $accountData) {
        $account = Account::query()->create([
            'user_id' => $user->id,
            'account_type_id' => $accountType->id,
            'name' => $accountData['name'],
            'currency' => 'EUR',
            'opening_balance' => $accountData['balance'],
            'current_balance' => $accountData['balance'],
            'is_manual' => true,
            'is_active' => true,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_date' => '2026-01-01',
            'value_date' => '2026-01-01',
            'direction' => TransactionDirectionEnum::INCOME->value,
            'kind' => TransactionKindEnum::OPENING_BALANCE->value,
            'amount' => $accountData['balance'],
            'currency' => 'EUR',
            'source_type' => TransactionSourceTypeEnum::GENERATED->value,
            'status' => TransactionStatusEnum::CONFIRMED->value,
            'balance_after' => $accountData['balance'],
        ]);
    }

    foreach ([1, 2, 3] as $month) {
        $this->actingAs($user)
            ->get(route('transactions.show', [
                'year' => 2026,
                'month' => $month,
            ]))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->where('monthlySheet.totals.actual_income_raw', 0)
                ->where('monthlySheet.totals.actual_expense_raw', 0)
                ->where('monthlySheet.totals.net_actual_raw', 0)
                ->where('monthlySheet.meta.last_balance_raw', 9570.7)
                ->where('monthlySheet.meta.period_ending_balances', fn ($balances) => collect($balances)
                    ->contains(fn ($balance) => $balance['account_uuid'] !== null
                        && (float) $balance['balance_raw'] === 5000.0)));
    }
});

test('all accounts monthly sheet sums the latest balance of each included account', function () {
    $user = User::factory()->create();
    ensureTransactionsContext($user, 2026);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'summary-running-balance-checking',
    ], [
        'name' => 'Summary running balance checking',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $alphaAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Alpha',
        'currency' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 125,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $betaAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Beta',
        'currency' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 330,
        'is_manual' => true,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $alphaAccount->id,
        'transaction_date' => '2026-01-01',
        'value_date' => '2026-01-01',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => 100,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'balance_after' => 100,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $alphaAccount->id,
        'transaction_date' => '2026-01-20',
        'value_date' => '2026-01-20',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 25,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'balance_after' => 125,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $betaAccount->id,
        'transaction_date' => '2026-01-01',
        'value_date' => '2026-01-01',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => 300,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'balance_after' => 300,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $betaAccount->id,
        'transaction_date' => '2026-01-18',
        'value_date' => '2026-01-18',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 30,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'balance_after' => 330,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.meta.last_balance_raw', fn ($value) => (float) $value === 455.0)
            ->where('monthlySheet.totals.actual_income_raw', fn ($value) => (float) $value === 55.0)
            ->where('monthlySheet.totals.actual_expense_raw', fn ($value) => (float) $value === 0.0)
            ->where('monthlySheet.totals.net_actual_raw', fn ($value) => (float) $value === 55.0));
});

test('january monthly sheet does not derive an opening row before the real opening date in the same year', function () {
    $user = User::factory()->create();
    [$account] = seedTransactionsFixture($user, 2026);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2026-06-15',
        'value_date' => '2026-06-15',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'kind' => TransactionKindEnum::OPENING_BALANCE->value,
        'amount' => 250,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::GENERATED->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'balance_after' => 250,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2026,
            'month' => 1,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->doesntContain(fn ($transaction) => $transaction['is_opening_balance'] === true
                    && $transaction['account_label'] === 'Conto widget')));
});

test('transaction mutation routes do not resolve internal ids in public urls', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    seedTransactionsFixture($user);

    $transaction = Transaction::query()
        ->where('user_id', $user->id)
        ->whereDate('transaction_date', '2025-03-18')
        ->firstOrFail();

    $this->actingAs($user)
        ->delete(route('transactions.destroy', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $transaction->id,
        ]))
        ->assertNotFound();
});

test('closed years are read only for transaction mutations', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account, $category, $trackedItem] = seedTransactionsFixture($user);

    UserYear::query()
        ->where('user_id', $user->id)
        ->where('year', 2025)
        ->update(['is_closed' => true]);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 23,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'tracked_item_id' => $trackedItem->id,
            'amount' => 10,
        ])
        ->assertSessionHasErrors('transaction_date');
});

test('transactions reject dates outside the displayed month', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account, $category, $trackedItem] = seedTransactionsFixture($user);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 40,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'tracked_item_id' => $trackedItem->id,
            'amount' => 25,
            'description' => 'Fuori mese',
        ])
        ->assertSessionHasErrors('transaction_day');
});

test('transactions block likely duplicate inserts on the same accessible account', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    [$account, $category, $trackedItem] = seedTransactionsFixture($user);

    $this->actingAs($user)
        ->from(route('transactions.show', ['year' => 2025, 'month' => 3]))
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 18,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'tracked_item_id' => $trackedItem->id,
            'amount' => 45,
            'description' => '  Transaction   navigation fixture  ',
        ])
        ->assertSessionHasErrors('transaction');
});

test('transactions reject february 29 on non leap years', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account, $category, $trackedItem] = seedTransactionsFixture($user, 2025);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 2,
        ]), [
            'transaction_day' => 29,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'tracked_item_id' => $trackedItem->id,
            'amount' => 25,
            'description' => 'Febbraio non bisestile',
        ])
        ->assertSessionHasErrors('transaction_day');
});

test('transactions accept february 29 on leap years', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account, $category, $trackedItem] = seedTransactionsFixture($user, 2024);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2024,
            'month' => 2,
        ]), [
            'transaction_day' => 29,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'tracked_item_id' => $trackedItem->id,
            'amount' => 25,
            'description' => 'Febbraio bisestile',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2024,
            'month' => 2,
        ]));

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2024-02-29 00:00:00',
        'description' => 'Febbraio bisestile',
    ]);
});

test('saving categories with transfer direction remain valid in the monthly sheet', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account, , , , , $savingCategory] = seedTransactionsFixture($user);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 20,
            'type_key' => CategoryGroupTypeEnum::SAVING->value,
            'account_id' => $account->id,
            'category_id' => $savingCategory->id,
            'amount' => 120,
            'description' => 'Accantonamento mensile',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category_id' => $savingCategory->id,
        'transaction_date' => '2025-03-20 00:00:00',
        'description' => 'Accantonamento mensile',
    ]);
});

test('transactions reject tracked items owned by another user', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    [$account, $category] = seedTransactionsFixture($user);

    $foreignTrackedItem = TrackedItem::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Tracked item esterno',
        'slug' => 'tracked-item-esterno',
        'type' => 'asset',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 25,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'tracked_item_id' => $foreignTrackedItem->id,
            'amount' => 40,
        ])
        ->assertSessionHasErrors('tracked_item_id');
});

test('tracked items can be created quickly with transaction context metadata', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [, $category] = seedTransactionsFixture($user);

    $response = $this->actingAs($user)
        ->postJson(route('tracked-items.store'), [
            'name' => 'Cane domestico',
            'parent_id' => null,
            'type' => null,
            'is_active' => true,
            'settings' => [
                'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
                'transaction_category_ids' => [$category->id],
            ],
        ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('item.uuid', fn ($value) => is_string($value) && $value !== '')
        ->assertJsonMissingPath('item.id')
        ->assertJsonPath('item.label', 'Cane domestico')
        ->assertJsonPath('item.group_keys.0', CategoryGroupTypeEnum::EXPENSE->value)
        ->assertJsonPath('item.category_uuids.0', $category->uuid);

    $this->assertDatabaseHas('tracked_items', [
        'user_id' => $user->id,
        'name' => 'Cane domestico',
    ]);

    $trackedItem = TrackedItem::query()
        ->where('user_id', $user->id)
        ->where('name', 'Cane domestico')
        ->firstOrFail();

    expect($trackedItem->settings)->toMatchArray([
        'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
    ]);
    expect($trackedItem->compatibleCategories()->pluck('categories.id')->all())
        ->toBe([$category->id]);
});

test('a personal tracked item created from the transactions form stays personal and can be reused across compatible personal accounts', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$cashAccount, $category] = seedTransactionsFixture($user);
    $secondAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $cashAccount->account_type_id,
        'name' => 'UniCredit personale',
        'currency' => 'EUR',
        'opening_balance' => 500,
        'current_balance' => 500,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('transactions.tracked-items.store'), [
            'name' => 'Dodeca',
            'account_uuid' => $cashAccount->uuid,
            'category_uuid' => $category->uuid,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('item.label', 'Dodeca')
        ->assertJsonPath('item.group_keys.0', CategoryGroupTypeEnum::EXPENSE->value)
        ->assertJsonPath('item.category_uuids.0', $category->uuid);

    $trackedItemUuid = (string) $response->json('item.uuid');

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 28,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $secondAccount->uuid,
            'category_uuid' => $category->uuid,
            'tracked_item_uuid' => $trackedItemUuid,
            'amount' => 24.5,
            'description' => 'Dodeca on UniCredit',
        ])
        ->assertSessionHasNoErrors();

    $trackedItem = TrackedItem::query()
        ->where('uuid', $trackedItemUuid)
        ->firstOrFail();

    $transaction = Transaction::query()
        ->where('account_id', $secondAccount->id)
        ->where('description', 'Dodeca on UniCredit')
        ->firstOrFail();

    expect($trackedItem->account_id)->toBeNull()
        ->and((int) $trackedItem->user_id)->toBe($user->id)
        ->and((int) $transaction->tracked_item_id)->toBe($trackedItem->id);
});

test('shared account owner can create a tracked item from the transactions form and save it immediately', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $owner = User::factory()->create();
    $invitee = User::factory()->create();

    ensureTransactionsContext($invitee);

    [$sharedAccount, $ownerCategory] = seedTransactionsFixture($owner);

    shareAccountWithUser($sharedAccount, $invitee, AccountMembershipRoleEnum::EDITOR);

    $response = $this->actingAs($owner)
        ->postJson(route('transactions.tracked-items.store'), [
            'name' => 'Fastweb shared owner',
            'account_uuid' => $sharedAccount->uuid,
            'category_uuid' => $ownerCategory->uuid,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('item.label', 'Fastweb shared owner')
        ->assertJsonPath('item.uuid', fn ($value) => is_string($value) && $value !== '')
        ->assertJsonPath('item.category_uuids.0', fn ($value) => is_string($value) && $value !== '');

    $trackedItemUuid = (string) $response->json('item.uuid');

    $this->actingAs($owner)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 28,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $sharedAccount->uuid,
            'category_uuid' => $ownerCategory->uuid,
            'tracked_item_uuid' => $trackedItemUuid,
            'amount' => 29.9,
            'description' => 'Owner shared tracked item transaction',
        ])
        ->assertSessionHasNoErrors();

    $trackedItem = TrackedItem::query()
        ->where('uuid', $trackedItemUuid)
        ->firstOrFail();

    expect((int) $trackedItem->account_id)->toBe($sharedAccount->id)
        ->and((int) $trackedItem->user_id)->toBe($sharedAccount->user_id);
});

test('shared account invitee can create a tracked item from the transactions form and save it immediately', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $owner = User::factory()->create();
    $invitee = User::factory()->create();

    ensureTransactionsContext($invitee);

    [$sharedAccount, $ownerCategory] = seedTransactionsFixture($owner);

    shareAccountWithUser($sharedAccount, $invitee, AccountMembershipRoleEnum::EDITOR);

    $response = $this->actingAs($invitee)
        ->postJson(route('transactions.tracked-items.store'), [
            'name' => 'Fastweb shared invitee',
            'account_uuid' => $sharedAccount->uuid,
            'category_uuid' => $ownerCategory->uuid,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('item.label', 'Fastweb shared invitee')
        ->assertJsonPath('item.uuid', fn ($value) => is_string($value) && $value !== '')
        ->assertJsonPath('item.category_uuids.0', fn ($value) => is_string($value) && $value !== '');

    $trackedItemUuid = (string) $response->json('item.uuid');

    $this->actingAs($invitee)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 28,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_uuid' => $sharedAccount->uuid,
            'category_uuid' => $ownerCategory->uuid,
            'tracked_item_uuid' => $trackedItemUuid,
            'amount' => 19.9,
            'description' => 'Invitee shared tracked item transaction',
        ])
        ->assertSessionHasNoErrors();

    $trackedItem = TrackedItem::query()
        ->where('uuid', $trackedItemUuid)
        ->firstOrFail();

    expect((int) $trackedItem->account_id)->toBe($sharedAccount->id)
        ->and((int) $trackedItem->user_id)->toBe($sharedAccount->user_id);
});

test('transactions reject tracked items outside the selected group or category context', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account, , $trackedItem, , , $savingCategory] = seedTransactionsFixture($user);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 27,
            'type_key' => CategoryGroupTypeEnum::SAVING->value,
            'account_id' => $account->id,
            'category_id' => $savingCategory->id,
            'tracked_item_id' => $trackedItem->id,
            'amount' => 55,
            'description' => 'Elemento fuori contesto',
        ])
        ->assertSessionHasErrors('tracked_item_id');
});

test('tracked items linked to a category branch are valid on descendant leaves', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$account] = seedTransactionsFixture($user);

    $vehicleCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Auto',
        'slug' => 'auto-compatibilita-transazioni',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $bolloCategory = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $vehicleCategory->id,
        'name' => 'Bollo',
        'slug' => 'bollo-compatibilita-transazioni',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $trackedItem = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Kia',
        'slug' => 'kia-compatibilita-transazioni',
        'type' => 'auto',
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
        ],
    ]);

    $trackedItem->compatibleCategories()->sync([$vehicleCategory->id]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where("monthlySheet.editor.categories.{$account->uuid}", fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['id'] === $bolloCategory->id
                    && $category['label'] === 'Auto > Bollo'
                    && in_array($vehicleCategory->id, $category['ancestor_ids'], true)))
            ->where('monthlySheet.editor.tracked_items', fn ($trackedItems) => collect($trackedItems)
                ->contains(fn ($item) => $item['id'] === $trackedItem->id
                    && in_array($vehicleCategory->id, $item['category_ids'], true))));

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 21,
            'type_key' => CategoryGroupTypeEnum::EXPENSE->value,
            'account_id' => $account->id,
            'category_id' => $bolloCategory->id,
            'tracked_item_id' => $trackedItem->id,
            'amount' => 75,
            'description' => 'Bollo Kia',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category_id' => $bolloCategory->id,
        'tracked_item_id' => $trackedItem->id,
        'description' => 'Bollo Kia',
    ]);
});

test('giroconti create two linked transfer movements', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$sourceAccount, , , $destinationAccount] = seedTransactionsFixture($user);

    Category::query()
        ->where('user_id', $user->id)
        ->where('group_type', CategoryGroupTypeEnum::TRANSFER->value)
        ->update(['is_selectable' => false]);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 24,
            'type_key' => CategoryGroupTypeEnum::TRANSFER->value,
            'account_id' => $sourceAccount->id,
            'destination_account_id' => $destinationAccount->id,
            'amount' => 150.75,
            'description' => 'Giroconto operativo',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $transferCategory = Category::query()
        ->where('user_id', $user->id)
        ->where('foundation_key', CategoryFoundationService::INTERNAL_TRANSFER_FOUNDATION_KEY)
        ->firstOrFail();

    expect($transferCategory->is_selectable)->toBeFalse();

    $sourceTransaction = Transaction::query()
        ->where('user_id', $user->id)
        ->where('account_id', $sourceAccount->id)
        ->where('description', 'Giroconto operativo')
        ->where('direction', TransactionDirectionEnum::EXPENSE->value)
        ->firstOrFail();

    $destinationTransaction = Transaction::query()
        ->whereKey($sourceTransaction->related_transaction_id)
        ->firstOrFail();

    expect($sourceTransaction->is_transfer)->toBeTrue();
    expect($destinationTransaction->is_transfer)->toBeTrue();
    expect($sourceTransaction->related_transaction_id)->toBe($destinationTransaction->id);
    expect($destinationTransaction->related_transaction_id)->toBe($sourceTransaction->id);

    $this->assertDatabaseHas('transactions', [
        'id' => $sourceTransaction->id,
        'category_id' => $transferCategory->id,
        'transaction_date' => '2025-03-24 00:00:00',
        'amount' => 150.75,
        'direction' => TransactionDirectionEnum::EXPENSE->value,
    ]);

    $this->assertDatabaseHas('transactions', [
        'id' => $destinationTransaction->id,
        'account_id' => $destinationAccount->id,
        'category_id' => $transferCategory->id,
        'transaction_date' => '2025-03-24 00:00:00',
        'amount' => 150.75,
        'direction' => TransactionDirectionEnum::INCOME->value,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->contains(fn ($transaction) => $transaction['description'] === 'Giroconto operativo'
                    && $transaction['is_transfer'] === true
                    && $transaction['category_label'] === 'Trasferimento tra conti'
                    && $transaction['category_path'] === 'Giroconto interno tra conti'))
            ->where('monthlySheet.transactions', fn ($transactions) => collect($transactions)
                ->doesntContain(fn ($transaction) => $transaction['description'] === 'Giroconto operativo'
                    && $transaction['category_path'] === 'Addebito mensile della carta di credito')));
});

test('giroconti can be updated while keeping the pair linked', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$sourceAccount, , , $destinationAccount] = seedTransactionsFixture($user);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 24,
            'type_key' => CategoryGroupTypeEnum::TRANSFER->value,
            'account_id' => $sourceAccount->id,
            'destination_account_id' => $destinationAccount->id,
            'amount' => 150.75,
            'description' => 'Giroconto da aggiornare',
        ]);

    $sourceTransaction = Transaction::query()
        ->where('user_id', $user->id)
        ->where('account_id', $sourceAccount->id)
        ->where('description', 'Giroconto da aggiornare')
        ->where('direction', TransactionDirectionEnum::EXPENSE->value)
        ->firstOrFail();

    $this->actingAs($user)
        ->patch(route('transactions.update', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $sourceTransaction->uuid,
        ]), [
            'transaction_day' => 26,
            'type_key' => CategoryGroupTypeEnum::TRANSFER->value,
            'account_id' => $sourceAccount->id,
            'destination_account_id' => $destinationAccount->id,
            'amount' => 90.5,
            'description' => 'Giroconto aggiornato',
        ])
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    $sourceTransaction->refresh();
    $destinationTransaction = Transaction::query()
        ->whereKey($sourceTransaction->related_transaction_id)
        ->firstOrFail();

    expect($sourceTransaction->related_transaction_id)->toBe($destinationTransaction->id);
    expect($destinationTransaction->related_transaction_id)->toBe($sourceTransaction->id);

    $this->assertDatabaseHas('transactions', [
        'id' => $sourceTransaction->id,
        'transaction_date' => '2025-03-26 00:00:00',
        'amount' => 90.5,
        'description' => 'Giroconto aggiornato',
    ]);

    $this->assertDatabaseHas('transactions', [
        'id' => $destinationTransaction->id,
        'transaction_date' => '2025-03-26 00:00:00',
        'amount' => 90.5,
        'description' => 'Giroconto aggiornato',
    ]);
});

test('deleting one giroconto movement soft deletes the linked pair', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    [$sourceAccount, , , $destinationAccount] = seedTransactionsFixture($user);

    $this->actingAs($user)
        ->post(route('transactions.store', [
            'year' => 2025,
            'month' => 3,
        ]), [
            'transaction_day' => 24,
            'type_key' => CategoryGroupTypeEnum::TRANSFER->value,
            'account_id' => $sourceAccount->id,
            'destination_account_id' => $destinationAccount->id,
            'amount' => 150.75,
            'description' => 'Giroconto da eliminare',
        ]);

    $sourceTransaction = Transaction::query()
        ->where('user_id', $user->id)
        ->where('account_id', $sourceAccount->id)
        ->where('description', 'Giroconto da eliminare')
        ->where('direction', TransactionDirectionEnum::EXPENSE->value)
        ->firstOrFail();

    $destinationTransactionId = (int) $sourceTransaction->related_transaction_id;

    $this->actingAs($user)
        ->delete(route('transactions.destroy', [
            'year' => 2025,
            'month' => 3,
            'transaction' => $sourceTransaction->uuid,
        ]))
        ->assertRedirect(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]));

    expect(Transaction::withTrashed()->findOrFail($sourceTransaction->id)->trashed())->toBeTrue()
        ->and(Transaction::withTrashed()->findOrFail($destinationTransactionId)->trashed())->toBeTrue();
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

function seedTransactionsFixture(User $user, int $year = 2025): array
{
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => $year,
        'is_closed' => false,
    ]);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => $year,
        'base_currency' => 'EUR',
    ]);

    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'checking-transactions',
    ], [
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

    $destinationAccount = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto destinazione',
        'currency' => 'EUR',
        'opening_balance' => 250,
        'current_balance' => 250,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese correnti',
        'slug' => "spese-correnti-transazioni-{$user->id}-{$year}",
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    $transferCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Trasferimento interno',
        'slug' => "trasferimento-interno-transazioni-{$user->id}-{$year}",
        'direction_type' => CategoryDirectionTypeEnum::TRANSFER->value,
        'group_type' => CategoryGroupTypeEnum::TRANSFER->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $trackedItem = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Auto familiare',
        'slug' => "auto-familiare-transazioni-{$user->id}-{$year}",
        'type' => 'auto',
        'is_active' => true,
        'settings' => [
            'transaction_group_keys' => [CategoryGroupTypeEnum::EXPENSE->value],
        ],
    ]);

    $trackedItem->compatibleCategories()->sync([$category->id]);

    createTransactionForNavigation($user, $account, $category, 120, "{$year}-03-02", $trackedItem);
    createTransactionForNavigation($user, $account, $category, 45, "{$year}-03-18", $trackedItem);
    createTransactionForNavigation($user, $account, $category, 80, "{$year}-05-07");

    $savingCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Fondo emergenze',
        'slug' => "fondo-emergenze-transazioni-{$user->id}-{$year}",
        'direction_type' => CategoryDirectionTypeEnum::TRANSFER->value,
        'group_type' => CategoryGroupTypeEnum::SAVING->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    return [$account, $category, $trackedItem, $destinationAccount, $transferCategory, $savingCategory];
}

function ensureTransactionsContext(User $user, int $year = 2025): void
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
        'base_currency' => 'EUR',
    ]);
}

function shareAccountWithUser(
    Account $account,
    User $user,
    AccountMembershipRoleEnum $role,
    AccountMembershipStatusEnum $status = AccountMembershipStatusEnum::ACTIVE,
): AccountMembership {
    return AccountMembership::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'household_id' => null,
        'role' => $role->value,
        'status' => $status->value,
        'permissions' => null,
        'granted_by_user_id' => $account->user_id,
        'source' => MembershipSourceEnum::DIRECT->value,
        'joined_at' => now(),
    ]);
}

function createTransactionForNavigation(
    User $user,
    Account $account,
    Category $category,
    float $amount,
    string $date,
    ?TrackedItem $trackedItem = null,
): void {
    $latestBalance = Transaction::query()
        ->where('account_id', $account->id)
        ->max('balance_after');

    $previousBalance = $latestBalance !== null
        ? (float) $latestBalance
        : (float) $account->opening_balance;

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'transaction_date' => $date,
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => $amount,
        'currency' => 'EUR',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'description' => 'Transaction navigation fixture',
        'balance_after' => round($previousBalance - $amount, 2),
        'tracked_item_id' => $trackedItem?->id,
    ]);
}

function createRecurringPreviewFixture(
    User $user,
    Account $account,
    Category $category,
    ?TrackedItem $trackedItem = null,
    array $overrides = [],
): array {
    $entry = RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'scope_id' => null,
        'category_id' => $category->id,
        'tracked_item_id' => $trackedItem?->id,
        'merchant_id' => null,
        'title' => 'Recurring preview',
        'description' => 'Recurring preview',
        'notes' => null,
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_interval' => 1,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 20],
        'start_date' => '2025-03-20',
        'end_date' => null,
        'end_mode' => RecurringEndModeEnum::NEVER->value,
        'occurrences_limit' => null,
        'expected_amount' => 75,
        'total_amount' => null,
        'installments_count' => null,
        'next_occurrence_date' => '2025-03-20',
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
        ...$overrides,
    ]);

    $occurrence = RecurringEntryOccurrence::query()->create([
        'recurring_entry_id' => $entry->id,
        'sequence_number' => 1,
        'expected_date' => $overrides['start_date'] ?? '2025-03-20',
        'due_date' => $overrides['start_date'] ?? '2025-03-20',
        'expected_amount' => $overrides['expected_amount'] ?? 75,
        'status' => RecurringOccurrenceStatusEnum::PENDING->value,
        'notes' => null,
        'converted_transaction_id' => null,
        'matched_transaction_id' => null,
    ]);

    return [$entry, $occurrence->fresh(['recurringEntry'])];
}
