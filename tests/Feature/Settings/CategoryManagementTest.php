<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccountProvisioningService;
use App\Services\Categories\CategoryFoundationService;
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
            ->where('categories.flat.0.subtree_height', 1)
            ->where('categories.flat.1.subtree_height', 0)
            ->where('categories.tree.0.children.0.name', 'Assicurazione')
            ->where('options.direction_types', fn ($options) => collect($options)->pluck('value')->all() === [
                'income',
                'expense',
            ])
            ->where('options.group_types', fn ($options) => collect($options)->pluck('value')->all() === [
                'income',
                'expense',
                'bill',
                'debt',
                'saving',
            ]));
});

test('shared account categories do not leak into the personal settings categories tree', function () {
    $user = verifiedUser();
    $accountType = AccountType::query()->firstOrCreate([
        'code' => 'category-settings-test',
    ], [
        'name' => 'Category settings test',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Conto shared categorie',
        'currency' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_manual' => true,
        'is_active' => true,
    ]);

    makeCategory($user, [
        'name' => 'Personale',
        'slug' => 'personale',
    ]);

    $sharedRoot = Category::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'name' => 'Shared root',
        'slug' => 'shared-root-account-taxonomy',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    Category::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'parent_id' => $sharedRoot->id,
        'name' => 'Shared child',
        'slug' => 'shared-child-account-taxonomy',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 1,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.flat', fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['name'] === 'Personale'
                    && $category['scope_kind'] === 'personal'
                    && $category['account_name'] === null))
            ->where('categories.flat', fn ($categories) => collect($categories)
                ->doesntContain(fn ($category) => $category['name'] === 'Shared root'))
            ->where('categories.flat', fn ($categories) => collect($categories)
                ->doesntContain(fn ($category) => $category['name'] === 'Shared child')));
});

test('user can create a child category with normalized slug', function () {
    $user = verifiedUser();
    $parent = makeCategory($user, [
        'name' => 'Casa',
        'slug' => 'casa',
        'group_type' => 'bill',
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

test('child category inherits direction and group from the parent on create', function () {
    $user = verifiedUser();
    $parent = makeCategory($user, [
        'name' => 'Entrate',
        'slug' => 'entrate-test',
        'direction_type' => 'income',
        'group_type' => 'income',
    ]);

    $response = $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Stipendio coerente',
            'slug' => 'stipendio-coerente',
            'parent_id' => $parent->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ]);

    $response
        ->assertRedirect(route('categories.edit'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'slug' => 'stipendio-coerente',
        'direction_type' => 'income',
        'group_type' => 'income',
    ]);
});

test('user cannot create a fourth level category', function () {
    $user = verifiedUser();

    $root = makeCategory($user, [
        'name' => 'Casa',
        'slug' => 'casa-fourth-level',
    ]);

    $child = makeCategory($user, [
        'parent_id' => $root->id,
        'name' => 'Mutuo',
        'slug' => 'mutuo-fourth-level',
    ]);

    $leaf = makeCategory($user, [
        'parent_id' => $child->id,
        'name' => 'Quota capitale',
        'slug' => 'quota-capitale-fourth-level',
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Non ammessa',
            'slug' => 'non-ammessa-fourth-level',
            'parent_id' => $leaf->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('categories.edit'))
        ->assertSessionHasErrors('parent_id');

    $this->assertDatabaseMissing('categories', [
        'user_id' => $user->id,
        'slug' => 'non-ammessa-fourth-level',
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

test('same user cannot create two personal categories with the same slug', function () {
    $user = verifiedUser();

    makeCategory($user, [
        'name' => 'Alimentari',
        'slug' => 'alimentari',
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Alimentari duplicate',
            'slug' => 'alimentari',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('categories.edit'))
        ->assertSessionHasErrors('slug');
});

test('same user cannot create two root categories with the same name', function () {
    $user = verifiedUser();

    makeCategory($user, [
        'name' => 'Animali',
        'slug' => 'animali',
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Animali',
            'slug' => 'animali',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 2,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('categories.edit'))
        ->assertSessionHasErrors('name');
});

test('same user cannot create two leaf categories with the same name under the same parent', function () {
    $user = verifiedUser();
    $parent = makeCategory($user, [
        'name' => 'Abbonamenti',
        'slug' => 'abbonamenti-parent',
        'is_selectable' => false,
    ]);

    makeCategory($user, [
        'parent_id' => $parent->id,
        'name' => 'Streaming',
        'slug' => 'streaming',
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Streaming',
            'slug' => 'streaming',
            'parent_id' => $parent->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 2,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('categories.edit'))
        ->assertSessionHasErrors('name');
});

test('same leaf name remains allowed in different category branches', function () {
    $user = verifiedUser();
    $auto = makeCategory($user, [
        'name' => 'Auto',
        'slug' => 'auto-branch',
        'is_selectable' => false,
    ]);
    $moto = makeCategory($user, [
        'name' => 'Moto',
        'slug' => 'moto-branch',
        'is_selectable' => false,
    ]);

    foreach ([[$auto->id, 'assicurazione'], [$moto->id, 'assicurazione-moto']] as [$parentId, $slug]) {
        $this
            ->withSession(['_token' => csrfToken()])
            ->actingAs($user)
            ->post(route('categories.store'), [
                '_token' => csrfToken(),
                'name' => 'Assicurazione',
                'slug' => $slug,
                'parent_id' => $parentId,
                'direction_type' => 'expense',
                'group_type' => 'expense',
                'sort_order' => 1,
                'is_active' => true,
                'is_selectable' => true,
            ])
            ->assertSessionHasNoErrors();
    }

    expect(Category::query()
        ->ownedBy($user->id)
        ->where('name', 'Assicurazione')
        ->count())->toBe(2);
});

test('foundation leaves cannot be duplicated under the same branch but remain allowed across different branches', function () {
    $user = verifiedUser();

    app(CategoryFoundationService::class)->ensureForUser($user);

    $auto = Category::query()->ownedBy($user->id)->where('slug', 'auto')->firstOrFail();
    $moto = Category::query()->ownedBy($user->id)->where('slug', 'moto')->firstOrFail();

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Assicurazione',
            'slug' => 'assicurazione-duplicata',
            'parent_id' => $auto->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 99,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('categories.edit'))
        ->assertSessionHasErrors('name');

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'RC storica',
            'slug' => 'rc-storica',
            'parent_id' => $auto->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 100,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'parent_id' => $moto->id,
        'name' => 'Assicurazione',
    ]);
});

test('creating the first child moves parent budgets to the new child', function () {
    $user = verifiedUser();
    $parent = makeCategory($user, [
        'name' => 'Casa',
        'slug' => 'casa',
        'group_type' => 'bill',
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

test('categories page exposes level three nodes but they cannot be used as valid parents', function () {
    $user = verifiedUser();

    $root = makeCategory($user, [
        'name' => 'Entrate custom',
        'slug' => 'entrate-custom-parent-options',
        'direction_type' => 'income',
        'group_type' => 'income',
    ]);

    $child = makeCategory($user, [
        'parent_id' => $root->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio-parent-options',
        'direction_type' => 'income',
        'group_type' => 'income',
    ]);

    makeCategory($user, [
        'parent_id' => $child->id,
        'name' => 'Amazon Spa',
        'slug' => 'amazon-spa-parent-options',
        'direction_type' => 'income',
        'group_type' => 'income',
    ]);

    $this->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.flat', fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['full_path'] === 'Entrate custom'
                    && $category['depth'] === 0
                    && $category['subtree_height'] === 2))
            ->where('categories.flat', fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['full_path'] === 'Entrate custom > Stipendio'
                    && $category['depth'] === 1
                    && $category['subtree_height'] === 1))
            ->where('categories.flat', fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['full_path'] === 'Entrate custom > Stipendio > Amazon Spa'
                    && $category['depth'] === 2
                    && $category['subtree_height'] === 0)));
});

test('updating a child keeps branch direction and group inherited from the parent', function () {
    $user = verifiedUser();

    $parent = makeCategory($user, [
        'name' => 'Bollette',
        'slug' => 'bollette-update-inheritance',
        'direction_type' => 'expense',
        'group_type' => 'bill',
    ]);

    $child = makeCategory($user, [
        'parent_id' => $parent->id,
        'name' => 'Luce',
        'slug' => 'luce-update-inheritance',
        'direction_type' => 'expense',
        'group_type' => 'bill',
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->patch(route('categories.update', $child), [
            '_token' => csrfToken(),
            'name' => 'Energia elettrica',
            'slug' => 'energia-elettrica-update-inheritance',
            'parent_id' => $parent->id,
            'direction_type' => 'income',
            'group_type' => 'income',
            'sort_order' => 3,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    $child->refresh();

    expect($child->name)->toBe('Energia elettrica')
        ->and($child->direction_type->value)->toBe('expense')
        ->and($child->group_type->value)->toBe('bill');
});

test('child categories cannot be moved to a parent with an incompatible branch', function () {
    $user = verifiedUser();

    $incomeRoot = makeCategory($user, [
        'name' => 'Entrate custom move',
        'slug' => 'entrate-custom-move',
        'direction_type' => 'income',
        'group_type' => 'income',
    ]);

    $incomeChild = makeCategory($user, [
        'parent_id' => $incomeRoot->id,
        'name' => 'Stipendio move',
        'slug' => 'stipendio-move',
        'direction_type' => 'income',
        'group_type' => 'income',
    ]);

    $expenseRoot = makeCategory($user, [
        'name' => 'Spese custom move',
        'slug' => 'spese-custom-move',
        'direction_type' => 'expense',
        'group_type' => 'expense',
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->patch(route('categories.update', $incomeChild), [
            '_token' => csrfToken(),
            'name' => 'Stipendio move',
            'slug' => 'stipendio-move',
            'parent_id' => $expenseRoot->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('categories.edit'))
        ->assertSessionHasErrors('parent_id');

    expect($incomeChild->fresh()->parent_id)->toBe($incomeRoot->id)
        ->and($incomeChild->fresh()->direction_type->value)->toBe('income')
        ->and($incomeChild->fresh()->group_type->value)->toBe('income');
});

test('saving foundation and its children use expense saving classification', function () {
    $user = verifiedUser();

    app(CategoryFoundationService::class)->ensureForUser($user);

    $savingRoot = Category::query()
        ->ownedBy($user->id)
        ->where('foundation_key', 'saving')
        ->firstOrFail();

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->post(route('categories.store'), [
            '_token' => csrfToken(),
            'name' => 'Fondo studio',
            'slug' => 'fondo-studio-saving-root',
            'parent_id' => $savingRoot->id,
            'direction_type' => 'transfer',
            'group_type' => 'transfer',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    $this->assertDatabaseHas('categories', [
        'id' => $savingRoot->id,
        'direction_type' => 'expense',
        'group_type' => 'saving',
    ]);

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'parent_id' => $savingRoot->id,
        'slug' => 'fondo-studio-saving-root',
        'direction_type' => 'expense',
        'group_type' => 'saving',
    ]);
});

test('system foundation root category cannot change parent', function () {
    $user = verifiedUser();

    app(CategoryFoundationService::class)->ensureForUser($user);

    $incomeRoot = Category::query()
        ->ownedBy($user->id)
        ->where('foundation_key', 'income')
        ->firstOrFail();

    $expenseRoot = Category::query()
        ->ownedBy($user->id)
        ->where('foundation_key', 'expense')
        ->firstOrFail();

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->patch(route('categories.update', $incomeRoot), [
            '_token' => csrfToken(),
            'name' => 'Entrate',
            'slug' => 'entrate',
            'parent_id' => $expenseRoot->id,
            'direction_type' => 'income',
            'group_type' => 'income',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('categories.edit'))
        ->assertSessionHasErrors('parent_id');

    expect($incomeRoot->fresh()->parent_id)->toBeNull();
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

test('system foundation category can be renamed and keep its persisted slug if requested', function () {
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
            'name' => 'Income stream',
            'slug' => 'income-stream',
            'parent_id' => null,
            'direction_type' => 'income',
            'group_type' => 'income',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    expect($category->fresh()->name)->toBe('Income stream')
        ->and($category->fresh()->slug)->toBe('income-stream');
});

test('renaming a foundation category keeps transaction relations intact', function () {
    $user = verifiedUser();
    $account = app(AccountProvisioningService::class)
        ->ensureDefaultCashAccount($user);

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Bollette',
        'slug' => 'bollette',
        'foundation_key' => 'bill',
        'direction_type' => 'expense',
        'group_type' => 'bill',
        'sort_order' => 3,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => now()->toDateString(),
        'value_date' => now()->toDateString(),
        'direction' => 'expense',
        'kind' => 'manual',
        'amount' => 79.90,
        'currency' => 'EUR',
        'description' => 'Utility bill',
        'source_type' => 'manual',
        'status' => 'confirmed',
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->patch(route('categories.update', $category), [
            '_token' => csrfToken(),
            'name' => 'Home Bills',
            'slug' => 'home-bills',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'bill',
            'sort_order' => 3,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('categories.edit'));

    expect($transaction->fresh()->category_id)->toBe($category->id)
        ->and($transaction->fresh()->category?->name)->toBe('Home Bills');
});

test('system foundation root category cannot change direction or group', function () {
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

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->patch(route('categories.update', $category), [
            '_token' => csrfToken(),
            'name' => 'Entrate',
            'slug' => 'entrate',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertSessionHasErrors('direction_type')
        ->assertRedirect(route('categories.edit'));

    $category->refresh();

    expect($category->direction_type->value)->toBe('income')
        ->and($category->group_type->value)->toBe('income');
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

test('technical transfer system category cannot be updated from the standard category routes', function () {
    $user = verifiedUser();

    $category = app(CategoryFoundationService::class)
        ->ensureInternalTransferCategoryForUserId($user->id);

    $this
        ->withSession(['_token' => csrfToken()])
        ->actingAs($user)
        ->from(route('categories.edit'))
        ->patch(route('categories.update', $category), [
            '_token' => csrfToken(),
            'name' => 'Transfer rinominato',
            'slug' => 'transfer-rinominato',
            'parent_id' => null,
            'direction_type' => 'transfer',
            'group_type' => 'transfer',
            'sort_order' => 998,
            'is_active' => true,
            'is_selectable' => false,
        ])
        ->assertSessionHasErrors('name')
        ->assertRedirect(route('categories.edit'));

    expect($category->fresh()->name)->not->toBe('Transfer rinominato')
        ->and($category->fresh()->slug)->not->toBe('transfer-rinominato');
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
