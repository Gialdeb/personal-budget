<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('categories page is displayed', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $parent = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Casa',
        'slug' => 'casa-categories-test',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
    ]);
    Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'name' => 'Bollette',
        'slug' => 'bollette-categories-test',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Categories')
            ->where('categories.flat', fn ($items) => collect($items)
                ->every(fn (array $item) => Str::isUuid($item['uuid']) && ! array_key_exists('id', $item)))
            ->missing('categories.tree.0.id'),
        );
});

test('categories can be created using public parent uuid', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $parent = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Entrate',
        'slug' => 'entrate-categories-store-test',
        'direction_type' => 'income',
        'group_type' => 'income',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Stipendio',
            'slug' => 'stipendio-categories-store-test',
            'parent_uuid' => $parent->uuid,
            'direction_type' => 'income',
            'group_type' => 'income',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('categories.edit'));

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'name' => 'Stipendio',
    ]);
});
