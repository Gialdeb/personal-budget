<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\User;
use App\Services\Categories\CategoryFoundationService;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use Inertia\Testing\AssertableInertia as Assert;

function sharedVerifiedUser(): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
    ]);
}

function sharedAccountType(): AccountType
{
    return AccountType::query()->firstOrCreate([
        'code' => 'shared-categories-test',
    ], [
        'name' => 'Shared categories test',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);
}

function makeAccount(User $owner, string $name): Account
{
    return Account::query()->create([
        'user_id' => $owner->id,
        'account_type_id' => sharedAccountType()->id,
        'name' => $name,
        'currency' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_manual' => true,
        'is_active' => true,
    ]);
}

function shareAccount(Account $account, User $user, AccountMembershipRoleEnum $role): AccountMembership
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

function makeSharedCategory(Account $account, string $name, ?Category $parent = null): Category
{
    return Category::query()->create([
        'user_id' => $account->user_id,
        'account_id' => $account->id,
        'parent_id' => $parent?->id,
        'name' => $name,
        'slug' => str(fake()->unique()->slug())->append('-'.$account->id)->toString(),
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => false,
    ]);
}

test('shared categories page shows an empty state when the user has no shared accounts', function () {
    $user = sharedVerifiedUser();

    $this->actingAs($user)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/SharedCategories')
            ->where('sharedCategories.accounts', [])
            ->where('settingsNavigation.has_shared_categories', false));

    $this->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('settingsNavigation.has_shared_categories', false));
});

test('shared categories page separates multiple shared account catalogs without leaking personal categories', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();

    $revolut = makeAccount($owner, 'Conto Revolut');
    $jointSavings = makeAccount($owner, 'Riserva Famiglia');

    shareAccount($revolut, $editor, AccountMembershipRoleEnum::EDITOR);
    shareAccount($jointSavings, $editor, AccountMembershipRoleEnum::EDITOR);

    Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Categoria personale',
        'slug' => 'categoria-personale',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $revolutRoot = makeSharedCategory($revolut, 'Entrate');
    makeSharedCategory($revolut, 'Stipendio', $revolutRoot);

    $savingsRoot = makeSharedCategory($jointSavings, 'Risparmi');
    makeSharedCategory($jointSavings, 'Fondo vacanze', $savingsRoot);

    $this->actingAs($owner)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/SharedCategories')
            ->has('sharedCategories.accounts', 2)
            ->where('settingsNavigation.has_shared_categories', true)
            ->where('sharedCategories.accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['name'] === 'Conto Revolut'
                    && collect($account['categories']['flat'])->every(
                        fn ($item) => $item['account_uuid'] === $revolut->uuid,
                    ),
            ))
            ->where('sharedCategories.accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['name'] === 'Riserva Famiglia'
                    && collect($account['categories']['flat'])->every(
                        fn ($item) => $item['account_uuid'] === $jointSavings->uuid,
                    ),
            ))
            ->where('sharedCategories.accounts', fn ($accounts) => collect($accounts)->every(
                fn ($account) => collect($account['categories']['flat'])->doesntContain(
                    fn ($item) => $item['name'] === 'Categoria personale',
                ),
            )));

    $this->actingAs($owner)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('settingsNavigation.has_shared_categories', true));
});

test('viewer can see shared categories but cannot edit them', function () {
    $owner = sharedVerifiedUser();
    $viewer = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto condiviso viewer');

    shareAccount($account, $viewer, AccountMembershipRoleEnum::VIEWER);
    makeSharedCategory($account, 'Spese');

    $this->actingAs($viewer)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/SharedCategories')
            ->where('sharedCategories.accounts.0.name', 'Conto condiviso viewer')
            ->where('sharedCategories.accounts.0.can_edit', false));
});

test('editor can create a shared category while viewer is forbidden', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $viewer = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto operativo');

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);
    shareAccount($account, $viewer, AccountMembershipRoleEnum::VIEWER);

    $this->actingAs($editor)
        ->post(route('shared-categories.store', $account), [
            'name' => 'Entrate',
            'slug' => 'entrate-shared',
            'parent_id' => null,
            'direction_type' => 'income',
            'group_type' => 'income',
            'sort_order' => 0,
            'icon' => 'circle-dollar-sign',
            'color' => '#15803d',
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'account_id' => $account->id,
        'name' => 'Entrate',
        'slug' => 'entrate-shared',
    ]);

    $this->actingAs($viewer)
        ->post(route('shared-categories.store', $account), [
            'name' => 'Spese viewer',
            'slug' => 'spese-viewer',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertForbidden();
});

test('editor can materialize a missing personal category into the shared account catalog in a controlled way', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto stipendi');

    app(CategoryFoundationService::class)->ensureForUser($owner);
    app(CategoryFoundationService::class)->ensureForUser($editor);

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $editorIncomeRoot = Category::query()
        ->where('user_id', $editor->id)
        ->where('foundation_key', 'income')
        ->whereNull('account_id')
        ->firstOrFail();

    $salary = Category::query()->create([
        'user_id' => $editor->id,
        'parent_id' => $editorIncomeRoot->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio-editor-materialize',
        'direction_type' => 'income',
        'group_type' => 'income',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $barilla = Category::query()->create([
        'user_id' => $editor->id,
        'parent_id' => $salary->id,
        'name' => 'Barilla Spa',
        'slug' => 'barilla-spa-editor-materialize',
        'direction_type' => 'income',
        'group_type' => 'income',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($editor)
        ->post(route('shared-categories.materialize', $account), [
            'source_category_uuid' => $barilla->uuid,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(
            'success',
            __('categories.sharedPage.materialize.flash.created', ['name' => 'Barilla Spa']),
        );

    $this->assertDatabaseHas('categories', [
        'account_id' => $account->id,
        'name' => 'Barilla Spa',
    ]);

    expect(Category::query()->where('account_id', $account->id)->where('name', 'Barilla Spa')->count())->toBe(1)
        ->and(Category::query()->ownedBy($owner->id)->where('name', 'Barilla Spa')->doesntExist())->toBeTrue()
        ->and(Category::query()->where('account_id', $account->id)->where('name', 'Barilla Spa')->value('slug'))
        ->toBe('barilla-spa-editor-materialize');

    $this->actingAs($editor)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts.0.categories.flat', fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['full_path'] === 'Entrate > Stipendio > Barilla Spa'))
            ->where('sharedCategories.accounts.0.source_categories', fn ($options) => collect($options)
                ->doesntContain(fn ($option) => $option['label'] === 'Entrate > Stipendio > Barilla Spa')));

    $this->actingAs($owner)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts.0.categories.flat', fn ($categories) => collect($categories)
                ->contains(fn ($category) => $category['full_path'] === 'Entrate > Stipendio > Barilla Spa')));
});

test('owner can import a custom category from a personal non shared account into the shared account', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $personalAccount = makeAccount($owner, 'Poste Italiane');
    $sharedAccount = makeAccount($owner, 'Conto condiviso informatica');

    shareAccount($sharedAccount, $editor, AccountMembershipRoleEnum::EDITOR);

    $expenseRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $personalAccount->id,
        'name' => 'Spese',
        'slug' => 'poste-spese',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
        'is_system' => true,
    ]);

    $informatics = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $personalAccount->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Informatica',
        'slug' => 'informatica-poste',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'icon' => 'laptop',
        'color' => '#2563eb',
        'sort_order' => 10,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($catalog) => $catalog['name'] === 'Conto condiviso informatica'
                    && collect($catalog['source_categories'])->contains(
                        fn ($category) => $category['label'] === 'Spese > Informatica'
                            && $category['full_path'] === 'Spese > Informatica'
                            && $category['source_account_name'] === 'Poste Italiane'
                            && $category['badgeLabel'] === 'Poste Italiane'
                            && $category['icon'] === 'laptop'
                            && $category['is_selectable'] === true
                    )
            )));

    $this->actingAs($owner)
        ->post(route('shared-categories.materialize', $sharedAccount), [
            'source_category_uuid' => $informatics->uuid,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    expect(Category::query()
        ->sharedForAccount($sharedAccount->id)
        ->where('name', 'Informatica')
        ->count())->toBe(1);

    $this->actingAs($owner)
        ->post(route('shared-categories.materialize', $sharedAccount), [
            'source_category_uuid' => $informatics->uuid,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    expect(Category::query()
        ->sharedForAccount($sharedAccount->id)
        ->where('name', 'Informatica')
        ->count())->toBe(1);
});

test('editor can import a custom category from their own personal account into an editable shared account', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $editorPersonalAccount = makeAccount($editor, 'Poste Italiane');
    $sharedAccount = makeAccount($owner, 'Conto condiviso editor');

    shareAccount($sharedAccount, $editor, AccountMembershipRoleEnum::EDITOR);

    $expenseRoot = Category::query()->create([
        'user_id' => $editor->id,
        'account_id' => $editorPersonalAccount->id,
        'name' => 'Spese',
        'slug' => 'editor-poste-spese',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
        'is_system' => true,
    ]);

    $informatics = Category::query()->create([
        'user_id' => $editor->id,
        'account_id' => $editorPersonalAccount->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Informatica',
        'slug' => 'informatica-editor-poste',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'icon' => 'laptop',
        'color' => '#2563eb',
        'sort_order' => 10,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($editor)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($catalog) => $catalog['name'] === 'Conto condiviso editor'
                    && collect($catalog['source_categories'])->contains(
                        fn ($category) => $category['label'] === 'Spese > Informatica'
                            && $category['source_account_name'] === 'Poste Italiane'
                            && $category['is_selectable'] === true
                    )
            )));

    $this->actingAs($editor)
        ->post(route('shared-categories.materialize', $sharedAccount), [
            'source_category_uuid' => $informatics->uuid,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    expect(Category::query()
        ->sharedForAccount($sharedAccount->id)
        ->where('name', 'Informatica')
        ->count())->toBe(1);
});

test('shared categories bridge candidates include navigation parents and selectable useful child categories', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto ponte');

    app(CategoryFoundationService::class)->ensureForUser($owner);
    app(CategoryFoundationService::class)->ensureForUser($editor);

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $editorExpenseRoot = Category::query()
        ->where('user_id', $editor->id)
        ->where('foundation_key', 'expense')
        ->whereNull('account_id')
        ->firstOrFail();

    $this->actingAs($editor)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts.0.source_categories', fn ($options) => collect($options)
                ->isNotEmpty()
                && collect($options)->contains(fn ($option) => $option['label'] === 'Spese'
                    && $option['full_path'] === 'Spese'
                    && $option['is_selectable'] === false)
                && collect($options)->contains(fn ($option) => $option['label'] === 'Spese > Auto'
                    && $option['full_path'] === 'Spese > Auto'
                    && $option['is_selectable'] === false)
                && collect($options)->contains(fn ($option) => $option['label'] === 'Spese > Farmacia'
                    && $option['full_path'] === 'Spese > Farmacia'
                    && $option['icon'] === 'pill'
                    && $option['is_selectable'] === true
                    && filled($option['color'] ?? null))
                && collect($options)->contains(fn ($option) => $option['label'] === 'Spese > Auto > Assicurazione'
                    && $option['full_path'] === 'Spese > Auto > Assicurazione'
                    && $option['icon'] === 'shield-check'
                    && $option['is_selectable'] === true
                    && filled($option['color'] ?? null))))
        ->assertSessionDoesntHaveErrors();

    expect($editorExpenseRoot->children()->where('name', 'Auto')->firstOrFail()->is_selectable)->toBeFalse();
});

test('shared categories bridge exposes only the current user personal candidates for owner and invitee', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto ponte privacy');

    app(CategoryFoundationService::class)->ensureForUser($owner);
    app(CategoryFoundationService::class)->ensureForUser($editor);

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $ownerExpenseRoot = Category::query()
        ->where('user_id', $owner->id)
        ->where('foundation_key', 'expense')
        ->whereNull('account_id')
        ->firstOrFail();

    $editorExpenseRoot = Category::query()
        ->where('user_id', $editor->id)
        ->where('foundation_key', 'expense')
        ->whereNull('account_id')
        ->firstOrFail();

    Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $ownerExpenseRoot->id,
        'name' => 'Auto owner privata',
        'slug' => 'auto-owner-privata-shared-bridge',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Category::query()->create([
        'user_id' => $editor->id,
        'parent_id' => $editorExpenseRoot->id,
        'name' => 'Auto invitee privata',
        'slug' => 'auto-invitee-privata-shared-bridge',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts.0.source_categories', fn ($options) => collect($options)
                ->contains(fn ($option) => $option['label'] === 'Spese > Auto owner privata')
                && collect($options)->doesntContain(fn ($option) => $option['label'] === 'Spese > Auto invitee privata'))
        );

    $this->actingAs($editor)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts.0.source_categories', fn ($options) => collect($options)
                ->contains(fn ($option) => $option['label'] === 'Spese > Auto invitee privata')
                && collect($options)->doesntContain(fn ($option) => $option['label'] === 'Spese > Auto owner privata'))
        );
});

test('shared categories bridge rejects materializing a personal category owned by another contributor', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto ponte privacy backend');

    app(CategoryFoundationService::class)->ensureForUser($owner);
    app(CategoryFoundationService::class)->ensureForUser($editor);

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $ownerExpenseRoot = Category::query()
        ->where('user_id', $owner->id)
        ->where('foundation_key', 'expense')
        ->whereNull('account_id')
        ->firstOrFail();

    $ownerPersonalCategory = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $ownerExpenseRoot->id,
        'name' => 'Auto owner privata backend',
        'slug' => 'auto-owner-privata-shared-bridge-backend',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($editor)
        ->from(route('shared-categories.edit'))
        ->post(route('shared-categories.materialize', $account), [
            'source_category_uuid' => $ownerPersonalCategory->uuid,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasErrors('source_category_uuid');

    expect(Category::query()
        ->where('account_id', $account->id)
        ->where('name', 'Auto owner privata backend')
        ->doesntExist())->toBeTrue();
});

test('owner can materialize a personal category without creating duplicates and without leaking to other accounts', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $accountA = makeAccount($owner, 'Conto shared A');
    $accountB = makeAccount($owner, 'Conto shared B');

    app(CategoryFoundationService::class)->ensureForUser($owner);

    shareAccount($accountA, $editor, AccountMembershipRoleEnum::EDITOR);
    shareAccount($accountB, $editor, AccountMembershipRoleEnum::EDITOR);

    $expenseRoot = Category::query()
        ->where('user_id', $owner->id)
        ->where('foundation_key', 'expense')
        ->whereNull('account_id')
        ->firstOrFail();

    $auto = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Auto',
        'slug' => 'auto-owner-materialize',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('shared-categories.materialize', $accountA), [
            'source_category_uuid' => $auto->uuid,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->post(route('shared-categories.materialize', $accountA), [
            'source_category_uuid' => $auto->uuid,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    expect(Category::query()->where('account_id', $accountA->id)->where('name', 'Auto')->count())->toBe(1)
        ->and(Category::query()->where('account_id', $accountB->id)->where('name', 'Auto')->count())->toBe(0)
        ->and(Category::query()->ownedBy($owner->id)->where('name', 'Auto')->count())->toBeGreaterThanOrEqual(1)
        ->and(Category::query()->where('account_id', $accountA->id)->where('name', 'Auto')->value('slug'))
        ->toBe('auto-owner-materialize');

    $this->actingAs($owner)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($sharedAccount) => $sharedAccount['name'] === 'Conto shared A'
                    && collect($sharedAccount['source_categories'])->doesntContain(
                        fn ($option) => $option['label'] === 'Spese > Auto'
                            && $option['slug'] === 'auto-owner-materialize'
                            && $option['is_selectable'] === true,
                    ),
            )));
});

test('non shared accounts cannot use the personal to shared materialization bridge', function () {
    $owner = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto personale');

    app(CategoryFoundationService::class)->ensureForUser($owner);

    $expenseRoot = Category::query()
        ->where('user_id', $owner->id)
        ->where('foundation_key', 'expense')
        ->whereNull('account_id')
        ->firstOrFail();

    $auto = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Auto',
        'slug' => 'auto-non-shared-materialize',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('shared-categories.materialize', $account), [
            'source_category_uuid' => $auto->uuid,
        ])
        ->assertNotFound();

    expect(Category::query()->where('account_id', $account->id)->doesntExist())->toBeTrue();
});

test('personal and shared categories can reuse the same slug while shared slugs stay unique per account', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();

    $accountA = makeAccount($owner, 'Conto shared A');
    $accountB = makeAccount($owner, 'Conto shared B');

    shareAccount($accountA, $editor, AccountMembershipRoleEnum::EDITOR);
    shareAccount($accountB, $editor, AccountMembershipRoleEnum::EDITOR);

    Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Alimentari personali',
        'slug' => 'alimentari',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('shared-categories.store', $accountA), [
            'name' => 'Alimentari',
            'slug' => 'alimentari',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'user_id' => $owner->id,
        'account_id' => null,
        'slug' => 'alimentari',
    ]);

    $this->assertDatabaseHas('categories', [
        'user_id' => $owner->id,
        'account_id' => $accountA->id,
        'slug' => 'alimentari',
    ]);

    $this->actingAs($owner)
        ->from(route('shared-categories.edit'))
        ->post(route('shared-categories.store', $accountA), [
            'name' => 'Alimentari duplicate account',
            'slug' => 'alimentari',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasErrors('slug');

    $this->actingAs($owner)
        ->post(route('shared-categories.store', $accountB), [
            'name' => 'Alimentari secondo conto',
            'slug' => 'alimentari',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'user_id' => $owner->id,
        'account_id' => $accountB->id,
        'slug' => 'alimentari',
    ]);
});

test('manually created shared category stays visible in the shared tree for owner and invitee after reload', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto Revolut');

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $sharedExpenseRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => null,
        'name' => 'Spese',
        'slug' => 'shared-root-spese',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('shared-categories.store', $account), [
            'name' => 'Alimentari',
            'slug' => 'alimentari',
            'parent_id' => $sharedExpenseRoot->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 1,
            'icon' => 'utensils-crossed',
            'color' => '#1d4ed8',
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'account_id' => $account->id,
        'parent_id' => $sharedExpenseRoot->id,
        'name' => 'Alimentari',
        'slug' => 'alimentari',
    ]);

    $this->actingAs($owner)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($catalog) => $catalog['name'] === 'Conto Revolut'
                    && collect($catalog['categories']['flat'])->contains(
                        fn ($category) => $category['name'] === 'Alimentari'
                            && $category['full_path'] === 'Spese > Alimentari',
                    ),
            )));

    $this->actingAs($editor)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($catalog) => $catalog['name'] === 'Conto Revolut'
                    && collect($catalog['categories']['flat'])->contains(
                        fn ($category) => $category['name'] === 'Alimentari'
                            && $category['full_path'] === 'Spese > Alimentari',
                    ),
            )));
});

test('shared categories source options keep homonymous leaves distinct with contextual labels', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto omonimi shared');

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $personalRoot = Category::query()->create([
        'user_id' => $owner->id,
        'name' => 'Spese custom',
        'slug' => 'spese-custom-duplicates',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $personalRoot->id,
        'name' => 'Alimentari',
        'slug' => 'alimentari-primo',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 1,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $personalRoot->id,
        'name' => 'Alimentari',
        'slug' => 'alimentari-secondo',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 2,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sharedCategories.accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($catalog) => $catalog['name'] === 'Conto omonimi shared'
                    && collect($catalog['source_categories'])->contains(
                        fn ($category) => $category['label'] === 'Spese custom > Alimentari · alimentari-primo'
                    )
                    && collect($catalog['source_categories'])->contains(
                        fn ($category) => $category['label'] === 'Spese custom > Alimentari · alimentari-secondo'
                    )
            )));
});

test('owner and editor can update, toggle, and delete shared categories without server errors while viewers are blocked', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $viewer = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto shared CRUD');

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);
    shareAccount($account, $viewer, AccountMembershipRoleEnum::VIEWER);

    $category = makeSharedCategory($account, 'Stipendio');

    $this->actingAs($owner)
        ->patch(route('shared-categories.update', [$account, $category]), [
            'name' => 'Stipendio netto',
            'slug' => 'stipendio-netto',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 2,
            'icon' => 'badge-euro',
            'color' => '#15803d',
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('Stipendio netto');

    $this->actingAs($editor)
        ->patch(route('shared-categories.toggle-active', [$account, $category]), [])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    expect($category->fresh()->is_active)->toBeFalse();

    $this->actingAs($editor)
        ->patch(route('shared-categories.update', [$account, $category]), [
            'name' => 'Stipendio netto editor',
            'slug' => 'stipendio-netto-editor',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 4,
            'icon' => 'badge-euro',
            'color' => '#1d4ed8',
            'is_active' => false,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('Stipendio netto editor');

    $this->actingAs($viewer)
        ->patch(route('shared-categories.update', [$account, $category]), [
            'name' => 'Viewer blocco',
            'slug' => 'viewer-blocco',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->patch(route('shared-categories.toggle-active', [$account, $category]), [])
        ->assertForbidden();

    $this->actingAs($editor)
        ->delete(route('shared-categories.destroy', [$account, $category]))
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('categories', [
        'id' => $category->id,
    ]);
});

test('shared categories page excludes tax investment and transfer group options', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto gruppi');

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $this->actingAs($owner)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
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

test('shared child category inherits direction and group from the parent on create', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto shared inheritance');

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $incomeRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Entrate',
        'slug' => 'entrate-shared-inheritance',
        'direction_type' => 'income',
        'group_type' => 'income',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('shared-categories.store', $account), [
            'name' => 'Stipendio',
            'slug' => 'stipendio-shared-inheritance',
            'parent_id' => $incomeRoot->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 1,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'account_id' => $account->id,
        'parent_id' => $incomeRoot->id,
        'slug' => 'stipendio-shared-inheritance',
        'direction_type' => 'income',
        'group_type' => 'income',
    ]);
});

test('shared categories cannot create a fourth level node', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto shared depth');

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $root = makeSharedCategory($account, 'Root');
    $child = makeSharedCategory($account, 'Child', $root);
    $leaf = makeSharedCategory($account, 'Leaf', $child);

    $this->actingAs($owner)
        ->from(route('shared-categories.edit'))
        ->post(route('shared-categories.store', $account), [
            'name' => 'Fourth',
            'slug' => 'fourth-shared-level',
            'parent_id' => $leaf->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasErrors('parent_id');
});

test('shared child category cannot move to an incompatible branch', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto shared move');

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $incomeRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Entrate',
        'slug' => 'entrate-shared-move',
        'direction_type' => 'income',
        'group_type' => 'income',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $expenseRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Spese',
        'slug' => 'spese-shared-move',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 1,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $incomeChild = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $incomeRoot->id,
        'name' => 'Stipendio',
        'slug' => 'stipendio-shared-move',
        'direction_type' => 'income',
        'group_type' => 'income',
        'sort_order' => 2,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => false,
    ]);

    $this->actingAs($owner)
        ->from(route('shared-categories.edit'))
        ->patch(route('shared-categories.update', [$account, $incomeChild]), [
            'name' => 'Stipendio',
            'slug' => 'stipendio-shared-move',
            'parent_id' => $expenseRoot->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 2,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasErrors('parent_id');
});

test('shared foundation root category cannot change parent or classification', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto foundation shared');

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $incomeRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Entrate',
        'slug' => 'entrate-shared-foundation',
        'foundation_key' => 'income',
        'direction_type' => 'income',
        'group_type' => 'income',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $expenseRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Spese',
        'slug' => 'spese-shared-foundation',
        'foundation_key' => 'expense',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 1,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $this->actingAs($owner)
        ->from(route('shared-categories.edit'))
        ->patch(route('shared-categories.update', [$account, $incomeRoot]), [
            'name' => 'Entrate',
            'slug' => 'entrate-shared-foundation',
            'parent_id' => $expenseRoot->id,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasErrors('parent_id');

    $this->actingAs($owner)
        ->from(route('shared-categories.edit'))
        ->patch(route('shared-categories.update', [$account, $incomeRoot]), [
            'name' => 'Entrate',
            'slug' => 'entrate-shared-foundation',
            'parent_id' => null,
            'direction_type' => 'expense',
            'group_type' => 'expense',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasErrors('direction_type');
});

test('shared foundation root category can be renamed by the account owner', function () {
    $owner = sharedVerifiedUser();
    $editor = sharedVerifiedUser();
    $account = makeAccount($owner, 'Conto foundation rename');

    shareAccount($account, $editor, AccountMembershipRoleEnum::EDITOR);

    $incomeRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Entrate',
        'slug' => 'shared-'.$account->id.'-root-income',
        'foundation_key' => null,
        'direction_type' => 'income',
        'group_type' => 'income',
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $this->actingAs($owner)
        ->from(route('shared-categories.edit'))
        ->patch(route('shared-categories.update', [$account, $incomeRoot]), [
            'name' => 'Income hub',
            'slug' => 'income-hub',
            'parent_id' => null,
            'direction_type' => 'income',
            'group_type' => 'income',
            'sort_order' => 0,
            'is_active' => true,
            'is_selectable' => true,
        ])
        ->assertRedirect(route('shared-categories.edit'))
        ->assertSessionHasNoErrors();

    expect($incomeRoot->fresh()->name)->toBe('Income hub')
        ->and($incomeRoot->fresh()->slug)->toBe('income-hub');
});

test('shared localized category backfill updates untouched defaults without overwriting custom names', function () {
    $owner = sharedVerifiedUser();
    $owner->forceFill([
        'locale' => 'en',
        'format_locale' => 'en-US',
    ])->save();

    $account = makeAccount($owner, 'Joint account');

    $expenseRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Spese',
        'slug' => 'shared-'.$account->id.'-root-expense',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 1,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Alimentari',
        'slug' => 'shared-'.$account->id.'-'.$expenseRoot->id.'-alimentari',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 10,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => false,
    ]);

    Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Utility bucket',
        'slug' => 'shared-'.$account->id.'-'.$expenseRoot->id.'-internet',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'sort_order' => 40,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => false,
    ]);

    app(SharedAccountCategoryTaxonomyService::class)
        ->backfillLocalizedDefaultsForAccount($account->fresh('user'));

    expect($expenseRoot->fresh()->name)->toBe('Expenses')
        ->and(Category::query()
            ->where('account_id', $account->id)
            ->where('slug', 'shared-'.$account->id.'-'.$expenseRoot->id.'-alimentari')
            ->value('name'))
        ->toBe('Groceries')
        ->and(Category::query()
            ->where('account_id', $account->id)
            ->where('slug', 'shared-'.$account->id.'-'.$expenseRoot->id.'-internet')
            ->value('name'))
        ->toBe('Utility bucket');
});
