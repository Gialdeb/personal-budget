<?php

use App\Enums\BudgetTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
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
            ->where('budgetPlanning.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'income' && (float) $card['amount_raw'] === 2400.0))
            ->where('budgetPlanning.summary_cards', fn ($cards) => collect($cards)
                ->contains(fn ($card) => $card['key'] === 'remaining' && (float) $card['amount_raw'] === 1720.0))
            ->has('budgetPlanning.months', 12)
            ->has('budgetPlanning.sections', 4)
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

test('users can update a leaf budget planning cell', function () {
    $user = User::factory()->create();

    $fixture = seedBudgetPlanningFixture($user);

    $this->actingAs($user);

    $response = $this->patchJson(route('budget-planning.update-cell'), [
        'year' => 2026,
        'month' => 3,
        'category_id' => $fixture['salary']->id,
        'amount' => 1350,
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('saved.category_id', $fixture['salary']->id)
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
        'category_id' => $fixture['expenseParent']->id,
        'amount' => 999,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);
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
