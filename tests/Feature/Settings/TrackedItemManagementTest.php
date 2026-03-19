<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\User;

function trackedItemsManagementVerifiedUser(): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
    ]);
}

function trackedItemsCsrfToken(): string
{
    return 'tracked-items-test-token';
}

function makeTrackedItemForManagement(User $user, array $attributes = []): TrackedItem
{
    return TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => 'Elemento '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'type' => null,
        'is_active' => true,
        ...$attributes,
    ]);
}

function makeBudgetCategoryForTrackedItem(User $user): Category
{
    return Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Budget '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);
}

function makeTrackedItemCompatibleCategory(User $user, array $attributes = []): Category
{
    return Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Auto '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
        ...$attributes,
    ]);
}

test('user can create a child tracked item with normalized slug', function () {
    $user = trackedItemsManagementVerifiedUser();
    $parent = makeTrackedItemForManagement($user, [
        'name' => 'Auto',
        'slug' => 'auto',
    ]);

    $response = $this
        ->withSession(['_token' => trackedItemsCsrfToken()])
        ->actingAs($user)
        ->post(route('tracked-items.store'), [
            '_token' => trackedItemsCsrfToken(),
            'name' => 'Beverly 400',
            'parent_id' => $parent->id,
            'type' => 'moto',
            'is_active' => true,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tracked-items.edit'));

    $this->assertDatabaseHas('tracked_items', [
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'name' => 'Beverly 400',
        'slug' => 'beverly-400',
        'type' => 'moto',
    ]);
});

test('user can associate a tracked item to compatible category branches', function () {
    $user = trackedItemsManagementVerifiedUser();
    $vehicleCategory = makeTrackedItemCompatibleCategory($user, [
        'name' => 'Auto',
        'slug' => 'auto-compatibile',
    ]);

    $response = $this
        ->withSession(['_token' => trackedItemsCsrfToken()])
        ->actingAs($user)
        ->post(route('tracked-items.store'), [
            '_token' => trackedItemsCsrfToken(),
            'name' => 'Kia',
            'parent_id' => null,
            'type' => 'auto',
            'category_ids' => [$vehicleCategory->id],
            'is_active' => true,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tracked-items.edit'));

    $trackedItem = TrackedItem::query()
        ->where('user_id', $user->id)
        ->where('slug', 'kia')
        ->firstOrFail();

    $this->assertDatabaseHas('tracked_item_categories', [
        'tracked_item_id' => $trackedItem->id,
        'category_id' => $vehicleCategory->id,
    ]);
});

test('user cannot assign a descendant as parent when updating a tracked item', function () {
    $user = trackedItemsManagementVerifiedUser();

    $parent = makeTrackedItemForManagement($user, [
        'name' => 'Veicoli',
        'slug' => 'veicoli',
    ]);

    $child = makeTrackedItemForManagement($user, [
        'parent_id' => $parent->id,
        'name' => 'Auto',
        'slug' => 'auto',
    ]);

    $grandChild = makeTrackedItemForManagement($user, [
        'parent_id' => $child->id,
        'name' => 'Kia',
        'slug' => 'kia',
    ]);

    $response = $this
        ->withSession(['_token' => trackedItemsCsrfToken()])
        ->actingAs($user)
        ->from(route('tracked-items.edit'))
        ->patch(route('tracked-items.update', $parent), [
            '_token' => trackedItemsCsrfToken(),
            'name' => 'Veicoli',
            'parent_id' => $grandChild->id,
            'type' => 'gruppo',
            'is_active' => true,
        ]);

    $response
        ->assertSessionHasErrors('parent_id')
        ->assertRedirect(route('tracked-items.edit'));

    expect($parent->fresh()->parent_id)->toBeNull();
});

test('active tracked item cannot be created under an inactive parent', function () {
    $user = trackedItemsManagementVerifiedUser();

    $parent = makeTrackedItemForManagement($user, [
        'name' => 'Archivio',
        'slug' => 'archivio',
        'is_active' => false,
    ]);

    $response = $this
        ->withSession(['_token' => trackedItemsCsrfToken()])
        ->actingAs($user)
        ->from(route('tracked-items.edit'))
        ->post(route('tracked-items.store'), [
            '_token' => trackedItemsCsrfToken(),
            'name' => 'Kia',
            'parent_id' => $parent->id,
            'type' => 'auto',
            'is_active' => true,
        ]);

    $response
        ->assertSessionHasErrors('parent_id')
        ->assertRedirect(route('tracked-items.edit'));
});

test('disabling a tracked item disables descendants too', function () {
    $user = trackedItemsManagementVerifiedUser();

    $parent = makeTrackedItemForManagement($user, [
        'name' => 'Veicoli',
        'slug' => 'veicoli',
    ]);

    $child = makeTrackedItemForManagement($user, [
        'parent_id' => $parent->id,
        'name' => 'Kia',
        'slug' => 'kia',
    ]);

    $this
        ->withSession(['_token' => trackedItemsCsrfToken()])
        ->actingAs($user)
        ->patch(route('tracked-items.toggle-active', $parent), [
            '_token' => trackedItemsCsrfToken(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tracked-items.edit'));

    expect($parent->fresh()->is_active)->toBeFalse();
    expect($child->fresh()->is_active)->toBeFalse();
});

test('used tracked item cannot be deleted', function () {
    $user = trackedItemsManagementVerifiedUser();
    $trackedItem = makeTrackedItemForManagement($user, [
        'name' => 'Smart',
        'slug' => 'smart',
    ]);
    $category = makeBudgetCategoryForTrackedItem($user);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'tracked_item_id' => $trackedItem->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 200,
        'budget_type' => 'target',
    ]);

    $response = $this
        ->withSession(['_token' => trackedItemsCsrfToken()])
        ->actingAs($user)
        ->from(route('tracked-items.edit'))
        ->delete(route('tracked-items.destroy', $trackedItem), [
            '_token' => trackedItemsCsrfToken(),
        ]);

    $response
        ->assertSessionHasErrors('delete')
        ->assertRedirect(route('tracked-items.edit'));

    expect($trackedItem->fresh())->not->toBeNull();
});

test('unused leaf tracked item can be deleted', function () {
    $user = trackedItemsManagementVerifiedUser();
    $trackedItem = makeTrackedItemForManagement($user, [
        'name' => 'Cane',
        'slug' => 'cane',
    ]);

    $this
        ->withSession(['_token' => trackedItemsCsrfToken()])
        ->actingAs($user)
        ->delete(route('tracked-items.destroy', $trackedItem), [
            '_token' => trackedItemsCsrfToken(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tracked-items.edit'));

    $this->assertDatabaseMissing('tracked_items', [
        'id' => $trackedItem->id,
    ]);
});
