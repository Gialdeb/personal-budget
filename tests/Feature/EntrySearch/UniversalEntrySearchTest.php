<?php

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\User;
use Illuminate\Support\Str;

it('searches transactions for the active month and returns highlight navigation data', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user, ['name' => 'Main account']);
    $category = createSearchCategory($user, $account, 'Rent');
    $trackedItem = createSearchTrackedItem($user, $account, 'Apartment lease');

    $matchingTransaction = userTransaction($user, $account, [
        'category_id' => $category->id,
        'tracked_item_id' => $trackedItem->id,
        'description' => 'Rent April',
        'reference_code' => 'LEASE-APR',
        'transaction_date' => '2026-04-12',
        'value_date' => '2026-04-12',
        'amount' => '850.00',
        'currency' => 'EUR',
    ]);

    userTransaction($user, $account, [
        'category_id' => $category->id,
        'description' => 'Rent March',
        'transaction_date' => '2026-03-12',
        'value_date' => '2026-03-12',
        'amount' => '850.00',
        'currency' => 'EUR',
    ]);

    $response = $this->actingAs($user)->getJson(route('entry-search.index', [
        'q' => 'lease',
        'scope' => 'transactions',
        'current_year' => 2026,
        'current_month' => 4,
        'account_uuid' => $account->uuid,
        'category_uuid' => $category->uuid,
        'with_reference' => 1,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('filters.scope', 'transactions')
        ->assertJsonPath('filters.with_reference', true)
        ->assertJsonPath('total_results', 1)
        ->assertJsonPath('groups.0.month_key', '2026-04')
        ->assertJsonPath('groups.0.items.0.id', $matchingTransaction->uuid)
        ->assertJsonPath('groups.0.items.0.kind', 'transaction')
        ->assertJsonPath('groups.0.items.0.highlight_key', $matchingTransaction->uuid)
        ->assertJsonPath('groups.0.items.0.target_url', route('transactions.show', [
            'year' => 2026,
            'month' => 4,
            'highlight' => $matchingTransaction->uuid,
        ]));
});

it('searches recurring entries with recurring filters and returns a uniform payload shape', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user, ['name' => 'Bills account']);
    $category = createSearchCategory($user, $account, 'Subscriptions');
    $trackedItem = createSearchTrackedItem($user, $account, 'Streaming');

    $matchingEntry = createSearchRecurringEntry($user, $account, $category, $trackedItem, [
        'title' => 'Netflix Family',
        'notes' => 'Shared streaming account',
        'status' => RecurringEntryStatusEnum::PAUSED->value,
        'next_occurrence_date' => '2026-05-08',
        'start_date' => '2026-01-08',
        'expected_amount' => '19.99',
    ]);

    createSearchRecurringEntry($user, $account, $category, $trackedItem, [
        'title' => 'Spotify Premium',
        'notes' => null,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'next_occurrence_date' => '2026-04-02',
        'start_date' => '2026-01-02',
        'expected_amount' => '11.99',
    ]);

    $response = $this->actingAs($user)->getJson(route('entry-search.index', [
        'q' => 'netflix',
        'scope' => 'recurring',
        'across_months' => 1,
        'recurring_status' => RecurringEntryStatusEnum::PAUSED->value,
        'with_notes' => 1,
        'current_year' => 2026,
        'current_month' => 4,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('filters.scope', 'recurring')
        ->assertJsonPath('filters.recurring_status', 'paused')
        ->assertJsonPath('groups.0.items.0.id', $matchingEntry->uuid)
        ->assertJsonPath('groups.0.items.0.kind', 'recurring')
        ->assertJsonPath('groups.0.items.0.title', 'Netflix Family')
        ->assertJsonPath('groups.0.items.0.target_url', route('recurring-entries.index', [
            'year' => 2026,
            'month' => 5,
            'highlight' => $matchingEntry->uuid,
        ]));
});

it('searches across transactions and recurring entries grouped by month in descending order', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $category = createSearchCategory($user, $account, 'Utilities');

    $juneTransaction = userTransaction($user, $account, [
        'category_id' => $category->id,
        'description' => 'Alpha utility settlement',
        'transaction_date' => '2026-06-18',
        'value_date' => '2026-06-18',
        'amount' => '140.00',
        'currency' => 'EUR',
    ]);

    $mayEntry = createSearchRecurringEntry($user, $account, $category, null, [
        'title' => 'Alpha recurring utility',
        'next_occurrence_date' => '2026-05-04',
        'start_date' => '2026-01-04',
        'expected_amount' => '70.00',
    ]);

    $aprilTransaction = userTransaction($user, $account, [
        'category_id' => $category->id,
        'description' => 'Alpha archived bill',
        'transaction_date' => '2026-04-10',
        'value_date' => '2026-04-10',
        'amount' => '99.00',
        'currency' => 'EUR',
    ]);

    $response = $this->actingAs($user)->getJson(route('entry-search.index', [
        'q' => 'alpha',
        'scope' => 'all',
        'across_months' => 1,
        'current_year' => 2026,
        'current_month' => 4,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('total_results', 3)
        ->assertJsonPath('groups.0.month_key', '2026-06')
        ->assertJsonPath('groups.0.items.0.id', $juneTransaction->uuid)
        ->assertJsonPath('groups.1.month_key', '2026-05')
        ->assertJsonPath('groups.1.items.0.id', $mayEntry->uuid)
        ->assertJsonPath('groups.2.month_key', '2026-04')
        ->assertJsonPath('groups.2.items.0.id', $aprilTransaction->uuid);
});

it('supports a global text search with no additional filters', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $category = createSearchCategory($user, $account, 'Housing');

    $matchingTransaction = userTransaction($user, $account, [
        'category_id' => $category->id,
        'description' => 'Landlord April payment',
        'transaction_date' => '2026-04-12',
        'value_date' => '2026-04-12',
        'amount' => '950.00',
        'currency' => 'EUR',
    ]);

    $response = $this->actingAs($user)->getJson(route('entry-search.index', [
        'q' => 'landlord',
        'current_year' => 2026,
        'current_month' => 4,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('filters.scope', 'all')
        ->assertJsonPath('total_results', 1)
        ->assertJsonPath('groups.0.items.0.id', $matchingTransaction->uuid);
});

it('ignores recurring status when the selected scope is not recurring', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $category = createSearchCategory($user, $account, 'Subscriptions');

    $matchingTransaction = userTransaction($user, $account, [
        'category_id' => $category->id,
        'description' => 'Streaming payment',
        'transaction_date' => '2026-04-12',
        'value_date' => '2026-04-12',
        'amount' => '12.00',
        'currency' => 'EUR',
    ]);

    $response = $this->actingAs($user)->getJson(route('entry-search.index', [
        'q' => 'streaming',
        'scope' => 'transactions',
        'recurring_status' => RecurringEntryStatusEnum::PAUSED->value,
        'current_year' => 2026,
        'current_month' => 4,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('filters.scope', 'transactions')
        ->assertJsonPath('filters.recurring_status', null)
        ->assertJsonPath('total_results', 1)
        ->assertJsonPath('groups.0.items.0.id', $matchingTransaction->uuid);
});

it('returns an empty search payload when no criteria are active', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('entry-search.index', [
        'scope' => 'all',
        'current_year' => 2026,
        'current_month' => 4,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('total_results', 0)
        ->assertJsonPath('groups', []);
});

function createSearchCategory(User $user, Account $account, string $name): Category
{
    return Category::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'parent_id' => null,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        'foundation_key' => null,
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'color' => null,
        'icon' => null,
        'sort_order' => 1,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => false,
    ]);
}

function createSearchTrackedItem(User $user, Account $account, string $name): TrackedItem
{
    return TrackedItem::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'parent_id' => null,
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        'type' => null,
        'is_active' => true,
        'settings' => [],
    ]);
}

function createSearchRecurringEntry(
    User $user,
    Account $account,
    Category $category,
    ?TrackedItem $trackedItem,
    array $attributes = [],
): RecurringEntry {
    return RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'scope_id' => $account->scope_id,
        'category_id' => $category->id,
        'merchant_id' => null,
        'title' => 'Recurring plan',
        'description' => null,
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => '45.00',
        'total_amount' => null,
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'recurrence_rule' => ['kind' => 'monthly'],
        'start_date' => '2026-01-01',
        'end_date' => null,
        'next_occurrence_date' => '2026-04-01',
        'end_mode' => 'never',
        'occurrences_limit' => null,
        'installments_count' => null,
        'due_day' => 1,
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
        'notes' => 'Recurring note',
        'tracked_item_id' => $trackedItem?->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        ...$attributes,
    ]);
}
