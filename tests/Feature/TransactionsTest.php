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
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
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

    [, $category, $trackedItem] = seedTransactionsFixture($user);

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
            ->where('monthlySheet.filters.group_options', fn ($groups) => collect($groups)
                ->contains(fn ($group) => $group['value'] === 'expense'))
            ->where('monthlySheet.filters.category_options', fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['label'] === 'Spese correnti' && Str::isUuid($category['uuid'])))
            ->where('monthlySheet.filters.account_options', fn ($accounts) => collect($accounts)
                ->contains(fn ($account) => $account['label'] === 'Conto widget' && Str::isUuid($account['uuid'])))
            ->where('monthlySheet.editor.group_options', fn ($groups) => collect($groups)
                ->contains(fn ($group) => $group['value'] === 'expense'))
            ->where('monthlySheet.editor.group_options', fn ($groups) => collect($groups)
                ->contains(fn ($group) => $group['value'] === 'transfer'
                    && $group['label'] === 'Giroconto'))
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
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
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

test('transactions can be deleted from the monthly sheet', function () {
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
        ]));

    $this->assertDatabaseMissing('transactions', [
        'id' => $transaction->id,
    ]);
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
        ->assertJsonPath('item.label', 'Cane domestico')
        ->assertJsonPath('item.group_keys.0', CategoryGroupTypeEnum::EXPENSE->value)
        ->assertJsonPath('item.category_ids.0', $category->id);

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
            ->where('monthlySheet.editor.categories', fn ($categories) => collect($categories)
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

    [$sourceAccount, , , $destinationAccount, $transferCategory] = seedTransactionsFixture($user);

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

test('deleting one giroconto movement removes the linked pair', function () {
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

    $this->assertDatabaseMissing('transactions', [
        'id' => $sourceTransaction->id,
    ]);

    $this->assertDatabaseMissing('transactions', [
        'id' => $destinationTransactionId,
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
        'slug' => 'spese-correnti-transazioni',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
    ]);

    $transferCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Trasferimento interno',
        'slug' => "trasferimento-interno-transazioni-{$year}",
        'direction_type' => CategoryDirectionTypeEnum::TRANSFER->value,
        'group_type' => CategoryGroupTypeEnum::TRANSFER->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $trackedItem = TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Auto familiare',
        'slug' => "auto-familiare-transazioni-{$year}",
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
        'slug' => "fondo-emergenze-transazioni-{$year}",
        'direction_type' => CategoryDirectionTypeEnum::TRANSFER->value,
        'group_type' => CategoryGroupTypeEnum::SAVING->value,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    return [$account, $category, $trackedItem, $destinationAccount, $transferCategory, $savingCategory];
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
