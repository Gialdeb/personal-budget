<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function verifiedUser(): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
    ]);
}

function makeCategory(User $user, array $attributes = []): Category
{
    return Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Categoria '.fake()->unique()->word(),
        'slug' => fake()->unique()->slug(),
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
        ...$attributes,
    ]);
}

function csrfToken(): string
{
    return 'test-token';
}

test('categories page returns tree and flat payload ready for the ui', function () {
    $user = verifiedUser();

    $parent = makeCategory($user, [
        'name' => 'Veicoli',
        'slug' => 'veicoli',
        'sort_order' => 1,
    ]);

    makeCategory($user, [
        'parent_id' => $parent->id,
        'name' => 'Assicurazione',
        'slug' => 'assicurazione',
        'sort_order' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Categories')
            ->where('categories.summary.total_count', 2)
            ->where('categories.summary.root_count', 1)
            ->where('categories.flat.0.full_path', 'Veicoli')
            ->where('categories.flat.1.full_path', 'Veicoli > Assicurazione')
            ->where('categories.tree.0.children.0.name', 'Assicurazione')
            ->where('options.direction_types.0.label', 'Entrata'));
});

test('user can create a child category with normalized slug', function () {
    $user = verifiedUser();
    $parent = makeCategory($user, [
        'name' => 'Casa',
        'slug' => 'casa',
    ]);

    $response = $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Affitto Mensile',
            'slug' => 'Affitto Mensile',
            'parent_id' => $parent->id,
            'direction_type' => 'expense',
            'group_type' => 'bill',
            'icon' => 'house',
            'color' => '#0f766e',
            'sort_order' => 3,
            'is_active' => true,
            'is_selectable' => true,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'parent_id' => $parent->id,
        'name' => 'Affitto Mensile',
        'slug' => 'affitto-mensile',
        'group_type' => 'bill',
        'icon' => 'house',
        'color' => '#0f766e',
    ]);
});

test('user can still create a custom root category alongside system foundations', function () {
    $user = verifiedUser();

    $response = $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Animali',
            'slug' => 'animali',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 7,
            'is_active' => true,
            'is_selectable' => true,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'parent_id' => null,
        'name' => 'Animali',
        'slug' => 'animali',
        'is_system' => false,
    ]);
});

test('creating the first child moves parent budgets to the new child', function () {
    $user = verifiedUser();
    $parent = makeCategory($user, [
        'name' => 'Casa',
        'slug' => 'casa',
        'is_selectable' => false,
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $parent->id,
        'year' => 2026,
        'month' => 4,
        'amount' => 180,
        'budget_type' => 'limit',
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Luce',
            'slug' => 'luce',
            'parent_id' => $parent->id,
            'direction_type' => 'expense',
            'group_type' => 'bill',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertSessionHasNoErrors();

    $child = Category::query()
        ->where('user_id', $user->id)
        ->where('parent_id', $parent->id)
        ->where('slug', 'luce')
        ->firstOrFail();

    $this->assertDatabaseMissing('budgets', [
        'category_id' => $parent->id,
        'year' => 2026,
        'month' => 4,
        'budget_type' => 'limit',
    ]);

    $this->assertDatabaseHas('budgets', [
        'category_id' => $child->id,
        'year' => 2026,
        'month' => 4,
        'amount' => 180,
        'budget_type' => 'limit',
    ]);
});

test('user cannot assign a descendant as parent when updating a category', function () {
    $user = verifiedUser();

    $parent = makeCategory($user, [
        'name' => 'Trasporti',
        'slug' => 'trasporti',
    ]);

    $child = makeCategory($user, [
        'parent_id' => $parent->id,
        'name' => 'Auto',
        'slug' => 'auto',
    ]);

    $grandChild = makeCategory($user, [
        'parent_id' => $child->id,
        'name' => 'Assicurazione',
        'slug' => 'assicurazione-auto',
    ]);

    $response = $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->patch(route('categories.update', $parent), [
            '_token' => csrfToken(),
            'name' => 'Trasporti',
            'slug' => 'trasporti',
            'parent_id' => $grandChild->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => false,
        ]);

    $response
        ->assertSessionHasErrors('parent_id')
        ->assertRedirect(route('categories.edit'));

    expect($parent->fresh()->parent_id)->toBeNull();
});

test('active category cannot be created under an inactive parent', function () {
    $user = verifiedUser();

    $parent = makeCategory($user, [
        'name' => 'Archivio',
        'slug' => 'archivio',
        'is_active' => false,
    ]);

    $response = $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Voce attiva',
            'slug' => 'voce-attiva',
            'parent_id' => $parent->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ]);

    $response
        ->assertSessionHasErrors('parent_id')
        ->assertRedirect(route('categories.edit'));
});

test('used category cannot be deleted', function () {
    $user = verifiedUser();
    $category = makeCategory($user, [
        'name' => 'Spesa mensile',
        'slug' => 'spesa-mensile',
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 200,
        'budget_type' => 'target',
    ]);

    $response = $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->delete(route('categories.destroy', $category), [
            '_token' => csrfToken(),
        ]);

    $response
        ->assertSessionHasErrors('delete')
        ->assertRedirect(route('categories.edit'));

    expect($category->fresh())->not->toBeNull();
});

test('system foundation category cannot be renamed', function () {
    $user = verifiedUser();

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Entrate',
        'slug' => 'entrate',
        'foundation_key' => 'income',
        'direction_type' => 'income',
        'group_type' => 'income',
        'sort_order' => 1,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $response = $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->patch(route('categories.update', $category), [
            '_token' => csrfToken(),
            'name' => 'Stipendi',
            'slug' => 'stipendi',
            'parent_id' => null,
            'direction_type' => 'income',
            'group_type' => 'income',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ]);

    $response
        ->assertSessionHasErrors('name')
        ->assertRedirect(route('categories.edit'));

    expect($category->fresh()->name)->toBe('Entrate');
});

test('system foundation category keeps active true but can update icon color and ordering', function () {
    $user = verifiedUser();

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Bollette',
        'slug' => 'bollette',
        'foundation_key' => 'bill',
        'direction_type' => 'expense',
        'group_type' => 'bill',
        'icon' => 'receipt',
        'color' => '#1d4ed8',
        'sort_order' => 3,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->patch(route('categories.update', $category), [
            '_token' => csrfToken(),
            'name' => 'Bollette',
            'slug' => 'bollette',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'bill',
            'icon' => 'smartphone',
            'color' => '#0891b2',
            'sort_order' => 9,
            'is_active' => false,
            'is_selectable' => true,
        ])
        ->assertSessionHasErrors('is_active')
        ->assertRedirect(route('categories.edit'));

    $category->refresh();

    expect($category->icon)->toBe('receipt')
        ->and($category->color)->toBe('#1d4ed8')
        ->and($category->sort_order)->toBe(3)
        ->and($category->is_active)->toBeTrue();

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->patch(route('categories.update', $category), [
            '_token' => csrfToken(),
            'name' => 'Bollette',
            'slug' => 'bollette',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'bill',
            'icon' => 'smartphone',
            'color' => '#0891b2',
            'sort_order' => 9,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    $category->refresh();

    expect($category->icon)->toBe('smartphone')
        ->and($category->color)->toBe('#0891b2')
        ->and($category->sort_order)->toBe(9)
        ->and($category->name)->toBe('Bollette')
        ->and($category->is_active)->toBeTrue();
});

test('system foundation category cannot be deleted while custom categories remain manageable', function () {
    $user = verifiedUser();

    $systemCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese',
        'slug' => 'spese',
        'foundation_key' => 'expense',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 2,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $customCategory = makeCategory($user, [
        'name' => 'Viaggi',
        'slug' => 'viaggi',
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->delete(route('categories.destroy', $systemCategory), [
            '_token' => csrfToken(),
        ])
        ->assertSessionHasErrors('delete')
        ->assertRedirect(route('categories.edit'));

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->delete(route('categories.destroy', $customCategory), [
            '_token' => csrfToken(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    expect($systemCategory->fresh())->not->toBeNull();
    expect($customCategory->fresh())->toBeNull();
});

test('custom category remains freely editable including name icon color and active state', function () {
    $user = verifiedUser();

    $category = makeCategory($user, [
        'name' => 'Viaggi',
        'slug' => 'viaggi',
        'icon' => 'plane',
        'color' => '#1d4ed8',
        'is_active' => true,
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->patch(route('categories.update', $category), [
            '_token' => csrfToken(),
            'name' => 'Vacanze',
            'slug' => 'vacanze',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'icon' => 'theater',
            'color' => '#c026d3',
            'sort_order' => 12,
            'is_active' => false,
            'is_selectable' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    $category->refresh();

    expect($category->name)->toBe('Vacanze')
        ->and($category->slug)->toBe('vacanze')
        ->and($category->icon)->toBe('theater')
        ->and($category->color)->toBe('#c026d3')
        ->and($category->sort_order)->toBe(12)
        ->and($category->is_active)->toBeFalse();
});

test('unused category can be deleted safely', function () {
    $user = verifiedUser();
    $category = makeCategory($user, [
        'name' => 'Occasionale',
        'slug' => 'occasionale',
    ]);

    $response = $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->delete(route('categories.destroy', $category), [
            '_token' => csrfToken(),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    $this->assertDatabaseMissing('categories', [
        'id' => $category->id,
    ]);
});

test('deleting the last child returns its budgets to the parent', function () {
    $user = verifiedUser();
    $parent = makeCategory($user, [
        'name' => 'Casa',
        'slug' => 'casa',
        'is_selectable' => false,
    ]);

    $child = makeCategory($user, [
        'parent_id' => $parent->id,
        'name' => 'Luce',
        'slug' => 'luce',
        'group_type' => 'bill',
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $child->id,
        'year' => 2026,
        'month' => 4,
        'amount' => 180,
        'budget_type' => 'limit',
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->delete(route('categories.destroy', $child), [
            '_token' => csrfToken(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    $this->assertDatabaseMissing('categories', [
        'id' => $child->id,
    ]);

    $this->assertDatabaseHas('budgets', [
        'category_id' => $parent->id,
        'year' => 2026,
        'month' => 4,
        'amount' => 180,
        'budget_type' => 'limit',
    ]);
});

test('deactivating a category also deactivates its descendants', function () {
    $user = verifiedUser();

    $parent = makeCategory($user, [
        'name' => 'Famiglia',
        'slug' => 'famiglia',
    ]);

    $child = makeCategory($user, [
        'parent_id' => $parent->id,
        'name' => 'Scuola',
        'slug' => 'scuola',
    ]);

    $response = $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->patch(route('categories.toggle-active', $parent), [
            '_token' => csrfToken(),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    expect($parent->fresh()->is_active)->toBeFalse();
    expect($child->fresh()->is_active)->toBeFalse();
});

test('system foundation category cannot be deactivated through the toggle endpoint', function () {
    $user = verifiedUser();

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Risparmi',
        'slug' => 'risparmi',
        'foundation_key' => 'saving',
        'direction_type' => 'transfer',
        'group_type' => 'saving',
        'icon' => 'piggy-bank',
        'color' => '#ca8a04',
        'sort_order' => 5,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->patch(route('categories.toggle-active', $category), [
            '_token' => csrfToken(),
        ])
        ->assertSessionHasErrors('toggle')
        ->assertRedirect(route('categories.edit'));

    expect($category->fresh()->is_active)->toBeTrue();
});

test('user can update selectable and active flags explicitly', function () {
    $user = verifiedUser();

    $category = makeCategory($user, [
        'name' => 'Tempo libero',
        'slug' => 'tempo-libero',
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $response = $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->patch(route('categories.update', $category), [
            '_token' => csrfToken(),
            'name' => 'Tempo libero',
            'slug' => 'tempo-libero',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'icon' => 'gamepad-2',
            'color' => '#7c3aed',
            'sort_order' => 0,
            'is_active' => false,
            'is_selectable' => false,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    $category->refresh();

    expect($category->is_active)->toBeFalse();
    expect($category->is_selectable)->toBeFalse();
    expect($category->icon)->toBe('gamepad-2');
    expect($category->color)->toBe('#7c3aed');
});
