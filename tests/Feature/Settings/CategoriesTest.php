<?php

use App\Models\Category;
use App\Models\User;
use App\Services\Categories\CategoryFoundationService;
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

test('categories page exposes the default foundation subtree with non selectable intermediate nodes', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    app(CategoryFoundationService::class)->ensureForUser($user);

    $this->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.flat', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['full_path'] === 'Spese > Auto > Assicurazione'
                    && $item['is_selectable'] === true))
            ->where('categories.flat', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['full_path'] === 'Spese > Abbonamenti > App e software'
                    && $item['is_selectable'] === true))
            ->where('categories.flat', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['full_path'] === 'Spese > Auto'
                    && $item['is_selectable'] === false))
            ->where('categories.flat', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['full_path'] === 'Spese > Abbonamenti'
                    && $item['is_selectable'] === false)));
});

test('foundation categories expose coherent icons and colors for semantic defaults', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    app(CategoryFoundationService::class)->ensureForUser($user);

    $this->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.flat', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['full_path'] === 'Entrate'
                    && $item['icon'] === 'circle-dollar-sign'
                    && $item['color'] === '#15803d'))
            ->where('categories.flat', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['full_path'] === 'Spese > Auto'
                    && $item['icon'] === 'car-front'
                    && $item['color'] === '#0f766e'))
            ->where('categories.flat', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['full_path'] === 'Spese > Auto > Assicurazione'
                    && $item['icon'] === 'shield-check'
                    && $item['color'] === '#0f766e'))
            ->where('categories.flat', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['full_path'] === 'Spese > Abbonamenti > App e software'
                    && $item['icon'] === 'smartphone'
                    && $item['color'] === '#8b5cf6'))
            ->where('categories.flat', fn ($items) => collect($items)
                ->contains(fn ($item) => $item['full_path'] === 'Risparmi > Investimenti'
                    && $item['icon'] === 'chart-column'
                    && $item['color'] === '#0369a1')));
});

test('categories page hides technical system transfer categories from the standard settings ui', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    app(CategoryFoundationService::class)->ensureForUser($user);
    app(CategoryFoundationService::class)->ensureInternalTransferCategoryForUserId($user->id);
    app(CategoryFoundationService::class)->ensureCreditCardSettlementCategoryForUserId($user->id);

    $this->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.flat', fn ($items) => collect($items)->doesntContain(
                fn ($item) => in_array($item['foundation_key'], [
                    CategoryFoundationService::INTERNAL_TRANSFER_FOUNDATION_KEY,
                    CategoryFoundationService::CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY,
                ], true)
            ))
            ->where('categories.tree', fn ($items) => collect($items)->doesntContain(
                fn ($item) => in_array($item['foundation_key'], [
                    CategoryFoundationService::INTERNAL_TRANSFER_FOUNDATION_KEY,
                    CategoryFoundationService::CREDIT_CARD_SETTLEMENT_FOUNDATION_KEY,
                ], true)
            )));
});
