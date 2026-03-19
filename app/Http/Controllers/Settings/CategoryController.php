<?php

namespace App\Http\Controllers\Settings;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCategoryRequest;
use App\Http\Requests\Settings\UpdateCategoryRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Supports\CategoryHierarchy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $payload = $this->buildPayload($request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('settings/Categories', $payload);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = DB::transaction(function () use ($request): Category {
            $category = Category::query()->create([
                ...$request->validated(),
                'user_id' => $request->user()->id,
            ]);

            if ($category->parent_id !== null) {
                $parent = Category::query()
                    ->ownedBy($request->user()->id)
                    ->find($category->parent_id);

                if ($parent !== null) {
                    $siblingCount = Category::query()
                        ->where('parent_id', $parent->id)
                        ->count();

                    if ($siblingCount === 1) {
                        $this->moveBudgets($parent, $category);
                    }
                }
            }

            return $category;
        });

        return to_route('categories.edit')->with('success', 'Categoria creata correttamente.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category = $this->ownedCategory($request, $category);
        $originalParentId = $category->parent_id;

        DB::transaction(function () use ($request, $category, $originalParentId): void {
            $category->fill($request->validated());
            $category->save();

            if ($category->parent_id !== null && $category->parent_id !== $originalParentId) {
                $parent = Category::query()
                    ->ownedBy($request->user()->id)
                    ->find($category->parent_id);

                if ($parent !== null) {
                    $siblingCount = Category::query()
                        ->where('parent_id', $parent->id)
                        ->count();

                    if ($siblingCount === 1) {
                        $this->moveBudgets($parent, $category);
                    }
                }
            }
        });

        if (! $category->is_active) {
            $descendantIds = CategoryHierarchy::descendantIds(
                Category::query()
                    ->ownedBy($request->user()->id)
                    ->get(['id', 'parent_id']),
                $category->id
            );

            if ($descendantIds !== []) {
                Category::query()
                    ->whereIn('id', $descendantIds)
                    ->update(['is_active' => false]);
            }
        }

        return to_route('categories.edit')->with('success', 'Categoria aggiornata correttamente.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $category = $this->ownedCategory($request, $category);
        $parent = $category->parent_id !== null
            ? Category::query()->ownedBy($request->user()->id)->find($category->parent_id)
            : null;
        $canReturnBudgetToParent = $parent !== null
            && $category->children()->count() === 0
            && Category::query()
                ->where('parent_id', $parent->id)
                ->whereKeyNot($category->id)
                ->count() === 0;

        $blockingReasons = $this->blockingReasons($category, $canReturnBudgetToParent);

        if ($blockingReasons !== []) {
            throw ValidationException::withMessages([
                'delete' => 'Questa categoria non può essere eliminata: '.implode(', ', $blockingReasons).'.',
            ]);
        }

        DB::transaction(function () use ($category, $parent, $canReturnBudgetToParent): void {
            if ($canReturnBudgetToParent && $parent !== null) {
                $this->moveBudgets($category, $parent);
            }

            $category->delete();
        });

        return to_route('categories.edit')->with('success', 'Categoria eliminata correttamente.');
    }

    public function toggleActive(Request $request, Category $category): RedirectResponse
    {
        $category = $this->ownedCategory($request, $category);
        $desiredState = ! $category->is_active;

        $categories = Category::query()
            ->ownedBy($request->user()->id)
            ->get(['id', 'parent_id', 'is_active']);

        if ($desiredState && $category->parent_id !== null) {
            $parent = $categories->firstWhere('id', $category->parent_id);

            if ($parent !== null && ! $parent->is_active) {
                throw ValidationException::withMessages([
                    'toggle' => 'Attiva prima la categoria padre per riattivare questa categoria.',
                ]);
            }
        }

        $idsToUpdate = [$category->id];

        if (! $desiredState) {
            $idsToUpdate = [
                ...$idsToUpdate,
                ...CategoryHierarchy::descendantIds($categories, $category->id),
            ];
        }

        Category::query()
            ->whereIn('id', array_values(array_unique($idsToUpdate)))
            ->update(['is_active' => $desiredState]);

        return to_route('categories.edit')->with(
            'success',
            $desiredState
                ? 'Categoria attivata correttamente.'
                : 'Categoria disattivata correttamente.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(int $userId): array
    {
        $categories = Category::query()
            ->ownedBy($userId)
            ->withCount([
                'children',
                'transactions',
                'transactionSplits',
                'transactionMatchers',
                'transactionTrainingSamples',
                'budgets',
                'recurringEntries',
                'scheduledEntries',
                'defaultMerchants',
                'oldTransactionReviews',
                'newTransactionReviews',
            ])
            ->get([
                'id',
                'user_id',
                'parent_id',
                'name',
                'slug',
                'icon',
                'color',
                'direction_type',
                'group_type',
                'sort_order',
                'is_active',
                'is_selectable',
            ]);
        $categories = $categories
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        $flatCategories = CategoryHierarchy::buildFlat($categories);
        $treeCategories = CategoryHierarchy::buildTree($categories);

        return [
            'categories' => [
                'tree' => $treeCategories,
                'flat' => $flatCategories,
                'summary' => [
                    'total_count' => count($flatCategories),
                    'root_count' => collect($flatCategories)->where('parent_id', null)->count(),
                    'active_count' => collect($flatCategories)->where('is_active', true)->count(),
                    'selectable_count' => collect($flatCategories)->where('is_selectable', true)->count(),
                    'used_count' => collect($flatCategories)->where('usage_count', '>', 0)->count(),
                ],
            ],
            'options' => [
                'direction_types' => CategoryDirectionTypeEnum::options(),
                'group_types' => CategoryGroupTypeEnum::options(),
            ],
        ];
    }

    protected function ownedCategory(Request $request, Category $category): Category
    {
        abort_unless($category->user_id === $request->user()->id, 404);

        return $category;
    }

    /**
     * @return array<int, string>
     */
    protected function blockingReasons(Category $category, bool $ignoreBudgetUsage = false): array
    {
        $category->loadCount([
            'children',
            'transactions',
            'transactionSplits',
            'transactionMatchers',
            'transactionTrainingSamples',
            'budgets',
            'recurringEntries',
            'scheduledEntries',
            'defaultMerchants',
            'oldTransactionReviews',
            'newTransactionReviews',
        ]);

        $reasons = [];

        if ($category->children_count > 0) {
            $reasons[] = $category->children_count === 1
                ? 'ha una categoria figlia'
                : "ha {$category->children_count} categorie figlie";
        }

        $labels = [
            'transactions_count' => 'transazioni',
            'transaction_splits_count' => 'split di transazioni',
            'transaction_matchers_count' => 'regole di categorizzazione',
            'transaction_training_samples_count' => 'campioni di training',
            'recurring_entries_count' => 'ricorrenze',
            'scheduled_entries_count' => 'scadenze pianificate',
            'default_merchants_count' => 'merchant predefiniti',
            'old_transaction_reviews_count' => 'revisioni transazioni precedenti',
            'new_transaction_reviews_count' => 'revisioni transazioni nuove',
        ];

        if (! $ignoreBudgetUsage) {
            $labels['budgets_count'] = 'budget';
        }

        foreach ($labels as $countKey => $label) {
            $count = (int) $category->{$countKey};

            if ($count > 0) {
                $reasons[] = $count === 1
                    ? "è usata in 1 {$label}"
                    : "è usata in {$count} {$label}";
            }
        }

        return $reasons;
    }

    protected function moveBudgets(Category $from, Category $to): void
    {
        if ($from->id === $to->id) {
            return;
        }

        $budgets = Budget::query()
            ->where('category_id', $from->id)
            ->get();

        foreach ($budgets as $budget) {
            $existingBudget = Budget::query()
                ->where('user_id', $budget->user_id)
                ->where('scope_id', $budget->scope_id)
                ->where('tracked_item_id', $budget->tracked_item_id)
                ->where('category_id', $to->id)
                ->where('year', $budget->year)
                ->where('month', $budget->month)
                ->where('budget_type', $budget->budget_type)
                ->first();

            if ($existingBudget !== null) {
                $existingBudget->update([
                    'amount' => round((float) $existingBudget->amount + (float) $budget->amount, 2),
                ]);

                $budget->delete();

                continue;
            }

            $budget->update([
                'category_id' => $to->id,
            ]);
        }
    }
}
