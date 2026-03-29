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

        return to_route('categories.edit')->with('success', __('categories.flash.created'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category = $this->ownedCategory($request, $category);

        $originalParentId = $category->parent_id;

        DB::transaction(function () use ($request, $category, $originalParentId): void {
            $validated = $request->validated();

            if ($category->is_system) {
                if (($validated['name'] ?? $category->name) !== $category->name) {
                    throw ValidationException::withMessages([
                        'name' => __('categories.validation.system_name_locked'),
                    ]);
                }

                if (($validated['slug'] ?? $category->slug) !== $category->slug) {
                    throw ValidationException::withMessages([
                        'slug' => __('categories.validation.system_name_locked'),
                    ]);
                }

                if (($validated['is_active'] ?? true) !== true) {
                    throw ValidationException::withMessages([
                        'is_active' => __('categories.validation.system_active_locked'),
                    ]);
                }

                if (($validated['parent_id'] ?? $category->parent_id) !== $category->parent_id) {
                    throw ValidationException::withMessages([
                        'parent_id' => __('categories.validation.system_parent_locked'),
                    ]);
                }

                if (($validated['direction_type'] ?? $category->direction_type?->value) !== $category->direction_type?->value) {
                    throw ValidationException::withMessages([
                        'direction_type' => __('categories.validation.system_classification_locked'),
                    ]);
                }

                if (($validated['group_type'] ?? $category->group_type?->value) !== $category->group_type?->value) {
                    throw ValidationException::withMessages([
                        'group_type' => __('categories.validation.system_classification_locked'),
                    ]);
                }

                $validated['name'] = $category->name;
                $validated['slug'] = $category->slug;
                $validated['is_active'] = true;
                $validated['direction_type'] = $category->direction_type?->value;
                $validated['group_type'] = $category->group_type?->value;
                $validated['parent_id'] = $category->parent_id;
            }

            $category->fill($validated);
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

        return to_route('categories.edit')->with('success', __('categories.flash.updated'));
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $category = $this->ownedCategory($request, $category);

        if ($category->is_system) {
            throw ValidationException::withMessages([
                'delete' => __('categories.validation.system_locked'),
            ]);
        }

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
                'delete' => __('categories.validation.delete_blocked', [
                    'reasons' => implode(', ', $blockingReasons),
                ]),
            ]);
        }

        DB::transaction(function () use ($category, $parent, $canReturnBudgetToParent): void {
            if ($canReturnBudgetToParent && $parent !== null) {
                $this->moveBudgets($category, $parent);
            }

            $category->delete();
        });

        return to_route('categories.edit')->with('success', __('categories.flash.deleted'));
    }

    public function toggleActive(Request $request, Category $category): RedirectResponse
    {
        $category = $this->ownedCategory($request, $category);

        if ($category->is_system) {
            throw ValidationException::withMessages([
                'toggle' => __('categories.validation.system_active_locked'),
            ]);
        }

        $desiredState = ! $category->is_active;

        $categories = Category::query()
            ->ownedBy($request->user()->id)
            ->get(['id', 'parent_id', 'is_active']);

        if ($desiredState && $category->parent_id !== null) {
            $parent = $categories->firstWhere('id', $category->parent_id);

            if ($parent !== null && ! $parent->is_active) {
                throw ValidationException::withMessages([
                    'toggle' => __('categories.validation.activate_parent_first'),
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
                ? __('categories.flash.activated')
                : __('categories.flash.deactivated')
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
                'uuid',
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
                'is_system',
                'foundation_key',
            ]);
        $categories = $categories
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        $flatCategories = collect(CategoryHierarchy::buildFlat($categories))
            ->map(fn (array $category): array => $this->publicCategoryPayload($category))
            ->values()
            ->all();
        $treeCategories = collect(CategoryHierarchy::buildTree($categories))
            ->map(fn (array $category): array => $this->publicCategoryPayload($category))
            ->values()
            ->all();

        return [
            'categories' => [
                'tree' => $treeCategories,
                'flat' => $flatCategories,
                'summary' => [
                    'total_count' => count($flatCategories),
                    'root_count' => collect($flatCategories)->where('parent_uuid', null)->count(),
                    'active_count' => collect($flatCategories)->where('is_active', true)->count(),
                    'selectable_count' => collect($flatCategories)->where('is_selectable', true)->count(),
                    'used_count' => collect($flatCategories)->where('usage_count', '>', 0)->count(),
                ],
            ],
            'options' => [
                'direction_types' => $this->personalDirectionOptions(),
                'group_types' => $this->personalGroupOptions(),
            ],
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    protected function personalDirectionOptions(): array
    {
        return collect(CategoryDirectionTypeEnum::options())
            ->whereIn('value', [
                CategoryDirectionTypeEnum::INCOME->value,
                CategoryDirectionTypeEnum::EXPENSE->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    protected function personalGroupOptions(): array
    {
        return collect(CategoryGroupTypeEnum::options())
            ->whereIn('value', [
                CategoryGroupTypeEnum::INCOME->value,
                CategoryGroupTypeEnum::EXPENSE->value,
                CategoryGroupTypeEnum::BILL->value,
                CategoryGroupTypeEnum::DEBT->value,
                CategoryGroupTypeEnum::SAVING->value,
            ])
            ->values()
            ->all();
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
                ? __('categories.blocking_reasons.child_one')
                : __('categories.blocking_reasons.child_many', ['count' => $category->children_count]);
        }

        $labels = [
            'transactions_count' => __('categories.blocking_labels.transactions'),
            'transaction_splits_count' => __('categories.blocking_labels.transaction_splits'),
            'transaction_matchers_count' => __('categories.blocking_labels.transaction_matchers'),
            'transaction_training_samples_count' => __('categories.blocking_labels.transaction_training_samples'),
            'recurring_entries_count' => __('categories.blocking_labels.recurring_entries'),
            'scheduled_entries_count' => __('categories.blocking_labels.scheduled_entries'),
            'default_merchants_count' => __('categories.blocking_labels.default_merchants'),
            'old_transaction_reviews_count' => __('categories.blocking_labels.old_transaction_reviews'),
            'new_transaction_reviews_count' => __('categories.blocking_labels.new_transaction_reviews'),
        ];

        if (! $ignoreBudgetUsage) {
            $labels['budgets_count'] = __('categories.blocking_labels.budgets');
        }

        foreach ($labels as $countKey => $label) {
            $count = (int) $category->{$countKey};

            if ($count > 0) {
                $reasons[] = $count === 1
                    ? __('categories.blocking_reasons.used_one', ['label' => $label])
                    : __('categories.blocking_reasons.used_many', ['count' => $count, 'label' => $label]);
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

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    protected function publicCategoryPayload(array $category): array
    {
        unset($category['id'], $category['ancestor_ids']);

        if (isset($category['children']) && is_array($category['children'])) {
            $category['children'] = collect($category['children'])
                ->map(fn (array $child): array => $this->publicCategoryPayload($child))
                ->values()
                ->all();
        }

        $isShared = ($category['account_id'] ?? null) !== null;

        $category['scope_kind'] = $isShared ? 'shared' : 'personal';
        $category['is_personal'] = ! $isShared;
        $category['is_shared'] = $isShared;
        $category['account_uuid'] = $category['account']['uuid'] ?? null;
        $category['account_name'] = $category['account']['name'] ?? null;

        unset($category['account_id'], $category['account']);

        return $category;
    }
}
