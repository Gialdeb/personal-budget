<?php

use App\Models\TrackedItem;
use App\Models\User;
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
        'name' => 'Elemento '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'type' => null,
        'is_active' => true,
        ...$attributes,
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
    ]);

    $this->actingAs($user)
        ->get(route('tracked-items.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TrackedItems')
            ->where('trackedItems.summary.total_count', 2)
            ->where('trackedItems.summary.root_count', 1)
            ->where('trackedItems.summary.leaf_count', 1)
            ->where('trackedItems.flat.0.full_path', 'Veicoli')
            ->where('trackedItems.flat.1.full_path', 'Veicoli > Kia')
            ->where('trackedItems.flat.1.parent_full_path', 'Veicoli')
            ->where('trackedItems.tree.0.children.0.name', 'Kia')
            ->where('options.types.0', 'auto'));
});
