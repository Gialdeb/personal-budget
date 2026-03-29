<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function trackedItemsVerifiedUser(): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
    ]);
}

function makeTrackedItemForSettings(User $user, array $attributes = []): TrackedItem
{
    return TrackedItem::query()->create([
        'user_id' => $user->id,
        'account_id' => null,
        'name' => 'Elemento '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'type' => null,
        'is_active' => true,
        ...$attributes,
    ]);
}

function trackedItemsAccountType(): AccountType
{
    return AccountType::query()->firstOrCreate([
        'code' => 'tracked-items-foundation-test',
    ], [
        'name' => 'Tracked items foundation test',
        'balance_nature' => 'asset',
    ]);
}

function trackedItemsAccount(User $owner, string $name = 'Conto tracked items'): Account
{
    return Account::query()->create([
        'user_id' => $owner->id,
        'account_type_id' => trackedItemsAccountType()->id,
        'name' => $name,
        'currency' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_manual' => true,
        'is_active' => true,
    ]);
}

function shareTrackedItemsAccount(Account $account, User $user, AccountMembershipRoleEnum $role): AccountMembership
{
    return AccountMembership::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'role' => $role,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'granted_by_user_id' => $account->user_id,
        'joined_at' => now(),
    ]);
}

test('tracked items page is displayed', function () {
    $user = trackedItemsVerifiedUser();

    $this->actingAs($user)
        ->get(route('tracked-items.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TrackedItems'),
        );
});

test('tracked items page returns tree and flat payload ready for the ui', function () {
    $user = trackedItemsVerifiedUser();
    $vehicleCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Auto',
        'slug' => 'auto-tracked-items-settings',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $parent = makeTrackedItemForSettings($user, [
        'name' => 'Veicoli',
        'slug' => 'veicoli',
        'type' => 'gruppo',
    ]);

    makeTrackedItemForSettings($user, [
        'parent_id' => $parent->id,
        'name' => 'Kia',
        'slug' => 'kia',
        'type' => 'auto',
    ])->compatibleCategories()->sync([$vehicleCategory->id]);

    $this->actingAs($user)
        ->get(route('tracked-items.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TrackedItems')
            ->where('trackedItems.summary.total_count', 2)
            ->where('trackedItems.summary.root_count', 1)
            ->where('trackedItems.summary.leaf_count', 1)
            ->where('trackedItems.flat.0.uuid', fn (string $uuid) => Str::isUuid($uuid))
            ->missing('trackedItems.flat.0.id')
            ->where('trackedItems.flat.0.full_path', 'Veicoli')
            ->where('trackedItems.flat.1.full_path', 'Veicoli > Kia')
            ->where('trackedItems.flat.1.parent_full_path', 'Veicoli')
            ->where('trackedItems.flat.1.compatible_category_uuids.0', $vehicleCategory->uuid)
            ->where('trackedItems.tree.0.children.0.name', 'Kia')
            ->where('options.types.0', 'auto')
            ->where('options.categories.0.uuid', $vehicleCategory->uuid)
            ->where('options.categories.0.label', 'Auto'));
});

test('tracked items can be created using public uuids', function () {
    $user = trackedItemsVerifiedUser();

    $vehicleCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Moto',
        'slug' => 'moto-tracked-items-store-test',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $parent = makeTrackedItemForSettings($user, [
        'name' => 'Garage',
        'slug' => 'garage',
        'type' => 'gruppo',
    ]);

    $this->actingAs($user)
        ->post(route('tracked-items.store'), [
            'name' => 'Scooter',
            'slug' => 'scooter',
            'parent_uuid' => $parent->uuid,
            'type' => 'mezzo',
            'category_uuids' => [$vehicleCategory->uuid],
            'settings' => [
                'transaction_group_keys' => ['expense'],
            ],
            'is_active' => true,
        ])
        ->assertRedirect(route('tracked-items.edit'));

    $trackedItem = TrackedItem::query()
        ->where('user_id', $user->id)
        ->where('slug', 'scooter')
        ->firstOrFail();

    expect($trackedItem->parent_id)->toBe($parent->id);
    expect($trackedItem->compatibleCategories()->pluck('categories.id')->all())
        ->toBe([$vehicleCategory->id]);
});

test('personal tracked items page excludes account scoped tracked items and shared categories', function () {
    $user = trackedItemsVerifiedUser();
    $account = trackedItemsAccount($user);

    $personalCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Auto personali',
        'slug' => 'auto-personali',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $sharedCategory = Category::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'name' => 'Auto shared',
        'slug' => 'auto-shared',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    makeTrackedItemForSettings($user, [
        'name' => 'Veicolo personale',
        'slug' => 'veicolo-personale',
    ]);

    TrackedItem::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'name' => 'Veicolo shared',
        'slug' => 'veicolo-shared',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('tracked-items.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('trackedItems.flat', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['name'] === 'Veicolo personale'))
            ->where('trackedItems.flat', fn ($items) => collect($items)
                ->doesntContain(fn ($item) => $item['name'] === 'Veicolo shared'))
            ->where('options.categories', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['uuid'] === $personalCategory->uuid))
            ->where('options.categories', fn ($items) => collect($items)
                ->doesntContain(fn ($item) => $item['uuid'] === $sharedCategory->uuid)));
});

test('tracked item model distinguishes personal and account scoped catalogs', function () {
    $user = trackedItemsVerifiedUser();
    $account = trackedItemsAccount($user);

    $personal = makeTrackedItemForSettings($user, [
        'name' => 'Moto personale',
        'slug' => 'moto-personale',
    ]);

    $shared = TrackedItem::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'name' => 'Moto shared',
        'slug' => 'moto-shared',
        'is_active' => true,
    ]);

    expect(TrackedItem::query()->ownedBy($user->id)->pluck('id')->all())
        ->toBe([$personal->id])
        ->and(TrackedItem::query()->sharedForAccount($account->id)->pluck('id')->all())
        ->toBe([$shared->id]);
});

test('tracked items cannot create a fourth hierarchy level', function () {
    $user = trackedItemsVerifiedUser();

    $root = makeTrackedItemForSettings($user, [
        'name' => 'Root',
        'slug' => 'root-item',
    ]);

    $levelTwo = makeTrackedItemForSettings($user, [
        'parent_id' => $root->id,
        'name' => 'Livello 2',
        'slug' => 'livello-2-item',
    ]);

    $levelThree = makeTrackedItemForSettings($user, [
        'parent_id' => $levelTwo->id,
        'name' => 'Livello 3',
        'slug' => 'livello-3-item',
    ]);

    $this->actingAs($user)
        ->from(route('tracked-items.edit'))
        ->post(route('tracked-items.store'), [
            'name' => 'Livello 4',
            'slug' => 'livello-4-item',
            'parent_uuid' => $levelThree->uuid,
            'is_active' => true,
        ])
        ->assertRedirect(route('tracked-items.edit'))
        ->assertSessionHasErrors('parent_id');
});

test('personal tracked items cannot link shared categories from an incompatible scope', function () {
    $user = trackedItemsVerifiedUser();
    $account = trackedItemsAccount($user);

    $sharedCategory = Category::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'name' => 'Categoria shared',
        'slug' => 'categoria-shared-tracked',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($user)
        ->from(route('tracked-items.edit'))
        ->post(route('tracked-items.store'), [
            'name' => 'Riferimento personale',
            'slug' => 'riferimento-personale',
            'category_uuids' => [$sharedCategory->uuid],
            'is_active' => true,
        ])
        ->assertRedirect(route('tracked-items.edit'))
        ->assertSessionHasErrors('category_uuids');
});

test('editor personal tracked item missing in shared can be materialized into shared account catalog', function () {
    $owner = trackedItemsVerifiedUser();
    $editor = trackedItemsVerifiedUser();

    $account = trackedItemsAccount($owner, 'Conto shared tracked');
    shareTrackedItemsAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $personalCategory = Category::query()->create([
        'user_id' => $editor->id,
        'name' => 'Supermercato',
        'slug' => 'supermercato-editor-tracked',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $sharedCategory = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Supermercato',
        'slug' => 'supermercato-shared-tracked',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $sourceTrackedItem = makeTrackedItemForSettings($editor, [
        'name' => 'Eurospin',
        'slug' => 'eurospin-editor-shared',
        'type' => 'negozio',
    ]);
    $sourceTrackedItem->compatibleCategories()->sync([$personalCategory->id]);

    $this->actingAs($editor)
        ->post(route('tracked-items.materialize', $account), [
            'source_tracked_item_uuid' => $sourceTrackedItem->uuid,
        ])
        ->assertRedirect(route('tracked-items.edit'))
        ->assertSessionHasNoErrors();

    $sharedTrackedItem = TrackedItem::query()
        ->where('account_id', $account->id)
        ->where('slug', 'eurospin-editor-shared')
        ->firstOrFail();

    $sharedTrackedItemCategoryIds = $sharedTrackedItem->compatibleCategories()
        ->pluck('categories.id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    expect((int) $sharedTrackedItem->user_id)->toBe($owner->id)
        ->and($sharedTrackedItem->account_id)->toBe($account->id)
        ->and($sharedTrackedItem->parent_id)->toBeNull()
        ->and($sharedTrackedItemCategoryIds)->not->toContain($personalCategory->id)
        ->and(Category::query()
            ->whereIn('id', $sharedTrackedItemCategoryIds)
            ->pluck('account_id')
            ->unique()
            ->values()
            ->all())->toBe([$account->id]);

    $this->actingAs($editor)
        ->get(route('tracked-items.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedBridge.accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($bridgeAccount) => $bridgeAccount['uuid'] === $account->uuid
                    && collect($bridgeAccount['source_tracked_items'])->doesntContain(
                        fn ($item) => $item['uuid'] === $sourceTrackedItem->uuid,
                    ))));

    $this->actingAs($owner)
        ->get(route('transactions.show', [
            'year' => 2025,
            'month' => 3,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('monthlySheet.editor.tracked_items', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['uuid'] === $sharedTrackedItem->uuid)));
});

test('owner can materialize a personal tracked item without duplicates and without leaking to other accounts', function () {
    $owner = trackedItemsVerifiedUser();
    $editor = trackedItemsVerifiedUser();

    $accountA = trackedItemsAccount($owner, 'Conto A tracked');
    $accountB = trackedItemsAccount($owner, 'Conto B tracked');

    shareTrackedItemsAccount($accountA, $editor, AccountMembershipRoleEnum::EDITOR);
    shareTrackedItemsAccount($accountB, $editor, AccountMembershipRoleEnum::EDITOR);

    $personalCategory = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Veicoli',
        'slug' => 'veicoli-owner-tracked',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $accountA->id,
        'name' => 'Veicoli',
        'slug' => 'veicoli-account-a-tracked',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $accountB->id,
        'name' => 'Veicoli',
        'slug' => 'veicoli-account-b-tracked',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $sourceTrackedItem = makeTrackedItemForSettings($owner, [
        'name' => 'Giulietta',
        'slug' => 'giulietta-owner-shared',
        'type' => 'auto',
    ]);
    $sourceTrackedItem->compatibleCategories()->sync([$personalCategory->id]);

    $this->actingAs($owner)
        ->post(route('tracked-items.materialize', $accountA), [
            'source_tracked_item_uuid' => $sourceTrackedItem->uuid,
        ])
        ->assertRedirect(route('tracked-items.edit'));

    $this->actingAs($owner)
        ->post(route('tracked-items.materialize', $accountA), [
            'source_tracked_item_uuid' => $sourceTrackedItem->uuid,
        ])
        ->assertRedirect(route('tracked-items.edit'));

    expect(TrackedItem::query()
        ->where('account_id', $accountA->id)
        ->where('slug', 'giulietta-owner-shared')
        ->count())->toBe(1)
        ->and(TrackedItem::query()
            ->where('account_id', $accountB->id)
            ->where('slug', 'giulietta-owner-shared')
            ->count())->toBe(0);

    $this->actingAs($owner)
        ->get(route('tracked-items.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedBridge.accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($bridgeAccount) => $bridgeAccount['uuid'] === $accountA->uuid
                    && collect($bridgeAccount['source_tracked_items'])->doesntContain(
                        fn ($item) => $item['uuid'] === $sourceTrackedItem->uuid,
                    )))
            ->where('sharedBridge.accounts', fn ($accounts) => collect($accounts)
                ->contains(fn ($bridgeAccount) => $bridgeAccount['uuid'] === $accountB->uuid
                    && collect($bridgeAccount['source_tracked_items'])->contains(
                        fn ($item) => $item['uuid'] === $sourceTrackedItem->uuid,
                    ))));
});

test('non shared accounts do not expose the tracked item bridge endpoint', function () {
    $owner = trackedItemsVerifiedUser();
    $account = trackedItemsAccount($owner, 'Conto personale tracked');

    $personalCategory = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Supermercato personale',
        'slug' => 'supermercato-personale-no-shared',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $trackedItem = makeTrackedItemForSettings($owner, [
        'name' => 'Deco',
        'slug' => 'deco-non-shared',
    ]);
    $trackedItem->compatibleCategories()->sync([$personalCategory->id]);

    $this->actingAs($owner)
        ->post(route('tracked-items.materialize', $account), [
            'source_tracked_item_uuid' => $trackedItem->uuid,
        ])
        ->assertNotFound();

    $this->assertDatabaseMissing('tracked_items', [
        'account_id' => $account->id,
        'slug' => 'deco-non-shared',
    ]);
});
