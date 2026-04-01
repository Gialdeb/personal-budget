<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\BudgetTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\MembershipSourceEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\AccountMembership;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
use App\Services\Categories\CategoryFoundationService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from budget planning', function () {
    $response = $this->get(route('budget-planning'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view the annual budget planning page', function () {
    $user = User::factory()->create();

    seedBudgetPlanningFixture($user);

    $this->actingAs($user);

    $response = $this->get(route('budget-planning', [
        'year' => 2026,
    ]));

    $response
        ->assertSuccessful()
        ->assertSessionHas('dashboard_year', 2026)
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/Planning')
            ->where('budgetPlanning.filters.year', 2026)
            ->where('budgetPlanning.settings.active_year', 2026)
            ->where('transactionsNavigation.context.year', 2026)
            ->where('budgetPlanning.filters.available_years', fn ($years) => collect($years)->isNotEmpty())
            ->where('budgetPlanning.filters.group_options', fn ($groups) => collect($groups)->isNotEmpty())
            ->where('budgetPlanning.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'income' && (float) $card['amount_raw'] === 2400.0))
            ->where('budgetPlanning.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'remaining' && (float) $card['amount_raw'] === 1720.0))
            ->has('budgetPlanning.months', 12)
            ->has('budgetPlanning.sections', 4)
            ->where('budgetPlanning.sections.0.flat_rows.0.uuid', fn (string $uuid) => Str::isUuid($uuid))
            ->where('budgetPlanning.sections', fn ($sections) => collect($sections)
                ->doesntContain(fn ($section) => $section['key'] === 'transfer'))
            ->where('budgetPlanning.sections', fn ($sections) => collect($sections)
                ->contains(fn ($section) => $section['key'] === 'expense'
                    && (float) $section['total_raw'] === 450.0))
            ->where('budgetPlanning.sections', fn ($sections) => collect($sections)
                ->contains(function ($section) {
                    if ($section['key'] !== 'expense') {
                        return false;
                    }

                    return collect($section['flat_rows'])->contains(
                        fn ($row) => $row['name'] === 'Spese'
                            && $row['has_children'] === true
                            && $row['is_editable'] === false
                            && (float) $row['row_total_raw'] === 450.0
                    );
                })),
        );

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'active_year' => 2026,
    ]);
});

test('budget planning keeps the default foundation subtree readable and editable only on leaf categories', function () {
    $user = User::factory()->create();

    app(CategoryFoundationService::class)->ensureForUser($user);

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

    $autoInsurance = findPlanningCategoryByPath($user, ['Spese', 'Auto', 'Assicurazione']);
    $streaming = findPlanningCategoryByPath($user, ['Spese', 'Abbonamenti', 'Streaming']);

    expect($autoInsurance)->not->toBeNull()
        ->and($streaming)->not->toBeNull();

    createPlanningBudget($user, $autoInsurance, 2026, 1, 120, BudgetTypeEnum::LIMIT);
    createPlanningBudget($user, $streaming, 2026, 1, 30, BudgetTypeEnum::LIMIT);

    $this->actingAs($user)
        ->get(route('budget-planning', ['year' => 2026]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('budgetPlanning.sections', fn ($sections) => collect($sections)
                ->contains(function ($section) {
                    if ($section['key'] !== 'expense') {
                        return false;
                    }

                    return collect($section['flat_rows'])->contains(
                        fn ($row) => $row['full_path'] === 'Spese > Auto'
                            && $row['has_children'] === true
                            && $row['is_editable'] === false
                    );
                }))
            ->where('budgetPlanning.sections', fn ($sections) => collect($sections)
                ->contains(function ($section) {
                    if ($section['key'] !== 'expense') {
                        return false;
                    }

                    return collect($section['flat_rows'])->contains(
                        fn ($row) => $row['full_path'] === 'Spese > Auto > Assicurazione'
                            && $row['is_editable'] === true
                            && (float) $row['row_total_raw'] === 120.0
                    );
                }))
            ->where('budgetPlanning.sections', fn ($sections) => collect($sections)
                ->contains(function ($section) {
                    if ($section['key'] !== 'expense') {
                        return false;
                    }

                    return collect($section['flat_rows'])->contains(
                        fn ($row) => $row['full_path'] === 'Spese > Abbonamenti > Streaming'
                            && $row['is_editable'] === true
                            && (float) $row['row_total_raw'] === 30.0
                    );
                })));
});

test('budget planning keeps homonymous categories separated by uuid instead of merging by label', function () {
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

    $root = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Spese custom',
        'slug' => 'spese-custom-homonyms',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $firstLeaf = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $root->id,
        'name' => 'Auto',
        'slug' => 'auto-primo',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 1,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $secondLeaf = Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $root->id,
        'name' => 'Auto',
        'slug' => 'auto-secondo',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 2,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    createPlanningBudget($user, $firstLeaf, 2026, 1, 50, BudgetTypeEnum::LIMIT);
    createPlanningBudget($user, $secondLeaf, 2026, 1, 80, BudgetTypeEnum::LIMIT);

    $this->actingAs($user)
        ->get(route('budget-planning', ['year' => 2026]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('budgetPlanning.sections', function ($sections) {
                $expenseSection = collect($sections)->firstWhere('key', 'expense');

                if (! is_array($expenseSection)) {
                    return false;
                }

                $homonymRows = collect($expenseSection['flat_rows'])
                    ->filter(fn ($row) => $row['full_path'] === 'Spese custom > Auto')
                    ->values();

                return $homonymRows->count() === 2
                    && $homonymRows->pluck('uuid')->unique()->count() === 2
                    && $homonymRows
                        ->pluck('row_total_raw')
                        ->map(fn ($value) => round((float) $value, 2))
                        ->sort()
                        ->values()
                        ->all() === [50.0, 80.0];
            }));
});

test('budget planning falls back to an allowed user year', function () {
    $user = User::factory()->create();

    seedBudgetPlanningFixture($user);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => 2026,
        'base_currency' => 'EUR',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('budget-planning', [
        'year' => 2035,
    ]));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/Planning')
            ->where('budgetPlanning.filters.year', 2026));
});

test('budget planning prefers the active year over a stale session year', function () {
    $user = User::factory()->create();

    seedBudgetPlanningFixture($user);

    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2024,
        'is_closed' => false,
    ]);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => 2024,
        'base_currency' => 'EUR',
    ]);

    $this->actingAs($user)
        ->withSession([
            'dashboard_year' => 2026,
        ]);

    $this->get(route('budget-planning'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/Planning')
            ->where('budgetPlanning.filters.year', 2024));
});

test('users can update a leaf budget planning cell', function () {
    $user = User::factory()->create();

    $fixture = seedBudgetPlanningFixture($user);

    $this->actingAs($user);

    $response = $this->patchJson(route('budget-planning.update-cell'), [
        'year' => 2026,
        'month' => 3,
        'category_uuid' => $fixture['salary']->uuid,
        'amount' => 1350,
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('saved.category_uuid', $fixture['salary']->uuid)
        ->assertJsonPath('saved.month', 3)
        ->assertJsonPath('saved.amount_raw', 1350);

    $this->assertDatabaseHas('budgets', [
        'user_id' => $user->id,
        'category_id' => $fixture['salary']->id,
        'year' => 2026,
        'month' => 3,
        'amount' => 1350,
        'budget_type' => BudgetTypeEnum::FORECAST->value,
    ]);
});

test('users cannot update a parent budget planning row', function () {
    $user = User::factory()->create();

    $fixture = seedBudgetPlanningFixture($user);

    $this->actingAs($user);

    $response = $this->patchJson(route('budget-planning.update-cell'), [
        'year' => 2026,
        'month' => 1,
        'category_uuid' => $fixture['expenseParent']->uuid,
        'amount' => 999,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_uuid']);
});

test('users can copy previous year values into the selected planning year', function () {
    $user = User::factory()->create();

    $fixture = seedBudgetPlanningFixture($user);

    Budget::query()->where('user_id', $user->id)->where('year', 2026)->delete();

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $fixture['groceries']->id,
        'year' => 2025,
        'month' => 1,
        'amount' => 320,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    $this->actingAs($user);

    $response = $this->postJson(route('budget-planning.copy-previous-year'), [
        'year' => 2026,
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('budgetPlanning.filters.year', 2026);

    $this->assertDatabaseHas('budgets', [
        'user_id' => $user->id,
        'category_id' => $fixture['groceries']->id,
        'year' => 2026,
        'month' => 1,
        'amount' => 320,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);
});

test('users cannot update a budget cell in a closed year', function () {
    $user = User::factory()->create();

    $fixture = seedBudgetPlanningFixture($user);

    UserYear::query()
        ->where('user_id', $user->id)
        ->where('year', 2026)
        ->update(['is_closed' => true]);

    $this->actingAs($user);

    $this->patchJson(route('budget-planning.update-cell'), [
        'year' => 2026,
        'month' => 3,
        'category_uuid' => $fixture['salary']->uuid,
        'amount' => 1350,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['year']);
});

test('users cannot copy previous year values into a closed year', function () {
    $user = User::factory()->create();

    seedBudgetPlanningFixture($user);

    UserYear::query()
        ->where('user_id', $user->id)
        ->where('year', 2026)
        ->update(['is_closed' => true]);

    $this->actingAs($user);

    $this->postJson(route('budget-planning.copy-previous-year'), [
        'year' => 2026,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['year']);
});

test('budget planning keeps a single personal budget row even if the same category is used on a shared account', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $account = createTestAccount($owner, ['name' => 'Conto shared planning']);

    UserYear::query()->create(['user_id' => $owner->id, 'year' => 2026, 'is_closed' => false]);
    UserYear::query()->create(['user_id' => $invitee->id, 'year' => 2026, 'is_closed' => false]);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $invitee->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::EDITOR,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::INVITATION,
        'joined_at' => now(),
    ]);

    $expenseRoot = createPlanningCategory($owner, 'Spese', 'planning-shared-spese-root', null, false, CategoryGroupTypeEnum::EXPENSE);
    $insurance = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'planning-shared-assicurazione-reference',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $sharedExpenseRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Spese',
        'slug' => 'shared-planning-spese',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $sharedInsurance = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $sharedExpenseRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'shared-planning-assicurazione',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Budget::query()->create([
        'user_id' => $owner->id,
        'category_id' => $insurance->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 700,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'category_id' => $sharedInsurance->id,
        'transaction_date' => '2026-02-15',
        'value_date' => '2026-02-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'amount' => 670,
        'currency' => 'EUR',
        'description' => 'Assicurazione shared',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'kind' => TransactionKindEnum::MANUAL->value,
    ]);

    $this->actingAs($owner)
        ->get(route('budget-planning', ['year' => 2026]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('budgetPlanning.sections', fn ($sections) => collect($sections)
                ->contains(function ($section) {
                    if ($section['key'] !== 'expense') {
                        return false;
                    }

                    return collect($section['flat_rows'])->contains(
                        fn ($row) => $row['name'] === 'Assicurazione'
                            && (float) $row['row_total_raw'] === 700.0
                    );
                }))
            ->where('budgetPlanning.sections', fn ($sections) => collect($sections)
                ->contains(function ($section) {
                    if ($section['key'] !== 'expense') {
                        return false;
                    }

                    return collect($section['flat_rows'])
                        ->where('name', 'Assicurazione')
                        ->count() === 1;
                }))
        );
});

test('invitee cannot edit a shared account category as if it were a separate budget row', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $account = createTestAccount($owner, ['name' => 'Conto shared planning']);

    UserYear::query()->create(['user_id' => $owner->id, 'year' => 2026, 'is_closed' => false]);
    UserYear::query()->create(['user_id' => $invitee->id, 'year' => 2026, 'is_closed' => false]);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $invitee->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::EDITOR,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::INVITATION,
        'joined_at' => now(),
    ]);

    $expenseRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'name' => 'Spese',
        'slug' => 'shared-planning-spese-update',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => false,
    ]);

    $insurance = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'shared-planning-assicurazione-update',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $this->actingAs($invitee)
        ->patchJson(route('budget-planning.update-cell'), [
            'year' => 2026,
            'month' => 2,
            'category_uuid' => $insurance->uuid,
            'amount' => 720,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_uuid']);
});

/**
 * @return array<string, Category>
 */
function seedBudgetPlanningFixture(User $user): array
{
    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2025,
        'is_closed' => false,
    ]);

    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => 2025,
        'base_currency' => 'EUR',
    ]);

    $incomeParent = createPlanningCategory($user, 'Entrate', 'entrate', null, false, CategoryGroupTypeEnum::INCOME);
    $salary = createPlanningCategory($user, 'Stipendio', 'stipendio', $incomeParent->id, true, CategoryGroupTypeEnum::INCOME, CategoryDirectionTypeEnum::INCOME);

    $expenseParent = createPlanningCategory($user, 'Spese', 'spese', null, false, CategoryGroupTypeEnum::EXPENSE);
    $groceries = createPlanningCategory($user, 'Spesa alimentare', 'spesa-alimentare', $expenseParent->id, true, CategoryGroupTypeEnum::EXPENSE);

    $billParent = createPlanningCategory($user, 'Bollette', 'bollette', null, false, CategoryGroupTypeEnum::BILL);
    $electricity = createPlanningCategory($user, 'Luce', 'luce', $billParent->id, true, CategoryGroupTypeEnum::BILL);

    $savingParent = createPlanningCategory($user, 'Risparmi', 'risparmi', null, false, CategoryGroupTypeEnum::SAVING);
    $emergency = createPlanningCategory($user, 'Fondo emergenze', 'fondo-emergenze', $savingParent->id, true, CategoryGroupTypeEnum::SAVING);

    createPlanningBudget($user, $salary, 2026, 1, 1200, BudgetTypeEnum::FORECAST);
    createPlanningBudget($user, $salary, 2026, 2, 1200, BudgetTypeEnum::FORECAST);
    createPlanningBudget($user, $groceries, 2026, 1, 200, BudgetTypeEnum::LIMIT);
    createPlanningBudget($user, $groceries, 2026, 2, 250, BudgetTypeEnum::LIMIT);
    createPlanningBudget($user, $electricity, 2026, 1, 80, BudgetTypeEnum::LIMIT);
    createPlanningBudget($user, $savingParent, 2026, 1, 999, BudgetTypeEnum::TARGET);
    createPlanningBudget($user, $emergency, 2026, 2, 150, BudgetTypeEnum::TARGET);

    return [
        'incomeParent' => $incomeParent,
        'salary' => $salary,
        'expenseParent' => $expenseParent,
        'groceries' => $groceries,
        'billParent' => $billParent,
        'electricity' => $electricity,
        'savingParent' => $savingParent,
        'emergency' => $emergency,
    ];
}

function createPlanningCategory(
    User $user,
    string $name,
    string $slug,
    ?int $parentId,
    bool $isSelectable,
    CategoryGroupTypeEnum $groupType,
    CategoryDirectionTypeEnum $directionType = CategoryDirectionTypeEnum::EXPENSE
): Category {
    return Category::query()->create([
        'user_id' => $user->id,
        'parent_id' => $parentId,
        'name' => $name,
        'slug' => $slug,
        'direction_type' => $directionType->value,
        'group_type' => $groupType->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => $isSelectable,
    ]);
}

function createPlanningBudget(
    User $user,
    Category $category,
    int $year,
    int $month,
    float $amount,
    BudgetTypeEnum $budgetType
): void {
    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'year' => $year,
        'month' => $month,
        'amount' => $amount,
        'budget_type' => $budgetType->value,
    ]);
}

function findPlanningCategoryByPath(User $user, array $path): ?Category
{
    $parentId = null;
    $category = null;

    foreach ($path as $segment) {
        $category = Category::query()
            ->where('user_id', $user->id)
            ->whereNull('account_id')
            ->where('parent_id', $parentId)
            ->where('name', $segment)
            ->first();

        if (! $category instanceof Category) {
            return null;
        }

        $parentId = $category->id;
    }

    return $category;
}
