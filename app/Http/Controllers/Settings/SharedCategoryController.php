<?php

namespace App\Http\Controllers\Settings;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSharedCategoryRequest;
use App\Http\Requests\Settings\UpdateSharedCategoryRequest;
use App\Models\Account;
use App\Models\Category;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Supports\CategoryHierarchy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SharedCategoryController extends Controller
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected SharedAccountCategoryTaxonomyService $sharedAccountCategoryTaxonomyService,
        protected OperationalTransactionCategoryResolver $operationalTransactionCategoryResolver,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $payload = $this->buildSharedPayload($request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('settings/SharedCategories', $payload);
    }

    public function store(StoreSharedCategoryRequest $request, Account $account): RedirectResponse
    {
        $account = $this->editableSharedAccount($request, $account);

        DB::transaction(function () use ($request, $account): void {
            Category::query()->create([
                ...$request->validated(),
                'user_id' => $account->user_id,
                'account_id' => $account->id,
            ]);
        });

        return to_route('shared-categories.edit')->with('success', __('categories.flash.created'));
    }

    public function update(UpdateSharedCategoryRequest $request, Account $account, Category $category): RedirectResponse
    {
        $account = $this->editableSharedAccount($request, $account);
        $category = $this->sharedAccountCategory($account, $category);

        DB::transaction(function () use ($request, $category): void {
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
                $validated['parent_id'] = $category->parent_id;
                $validated['direction_type'] = $category->direction_type?->value;
                $validated['group_type'] = $category->group_type?->value;
            }

            $category->fill($validated);
            $category->save();
        });

        if (! $category->is_active) {
            $descendantIds = CategoryHierarchy::descendantIds(
                Category::query()
                    ->sharedForAccount($account->id)
                    ->get(['id', 'parent_id']),
                $category->id,
            );

            if ($descendantIds !== []) {
                Category::query()
                    ->whereIn('id', $descendantIds)
                    ->update(['is_active' => false]);
            }
        }

        return to_route('shared-categories.edit')->with('success', __('categories.flash.updated'));
    }

    public function destroy(Request $request, Account $account, Category $category): RedirectResponse
    {
        $account = $this->editableSharedAccount($request, $account);
        $category = $this->sharedAccountCategory($account, $category);

        if ($category->is_system) {
            throw ValidationException::withMessages([
                'delete' => __('categories.validation.system_locked'),
            ]);
        }

        $blockingReasons = $this->blockingReasons($category);

        if ($blockingReasons !== []) {
            throw ValidationException::withMessages([
                'delete' => __('categories.validation.delete_blocked', [
                    'reasons' => implode(', ', $blockingReasons),
                ]),
            ]);
        }

        DB::transaction(function () use ($category): void {
            $category->delete();
        });

        return to_route('shared-categories.edit')->with('success', __('categories.flash.deleted'));
    }

    public function toggleActive(Request $request, Account $account, Category $category): RedirectResponse
    {
        $account = $this->editableSharedAccount($request, $account);
        $category = $this->sharedAccountCategory($account, $category);

        if ($category->is_system) {
            throw ValidationException::withMessages([
                'toggle' => __('categories.validation.system_active_locked'),
            ]);
        }

        $desiredState = ! $category->is_active;
        $categories = Category::query()
            ->sharedForAccount($account->id)
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

        return to_route('shared-categories.edit')->with(
            'success',
            $desiredState
                ? __('categories.flash.activated')
                : __('categories.flash.deactivated'),
        );
    }

    public function materialize(Request $request, Account $account): RedirectResponse
    {
        $account = $this->editableSharedAccount($request, $account);

        $validated = $request->validate([
            'source_category_uuid' => ['required', 'uuid'],
        ], [
            'source_category_uuid.required' => __('categories.sharedPage.materialize.validation.required'),
        ]);

        $sourceCategory = Category::query()
            ->where('uuid', $validated['source_category_uuid'])
            ->whereNull('account_id')
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->where(function ($query): void {
                $query
                    ->whereNull('group_type')
                    ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
            })
            ->first();

        if (! $sourceCategory instanceof Category) {
            throw ValidationException::withMessages([
                'source_category_uuid' => __('categories.sharedPage.materialize.validation.unavailable'),
            ]);
        }

        $existingCategory = $this->sharedAccountCategoryTaxonomyService->findExistingCategoryForSourceCategory(
            $account,
            $sourceCategory,
        );

        $sharedCategory = $this->sharedAccountCategoryTaxonomyService->materializeSourceCategoryForAccount(
            $account,
            $sourceCategory,
            true,
        );

        if (! $sharedCategory instanceof Category) {
            throw ValidationException::withMessages([
                'source_category_uuid' => __('categories.sharedPage.materialize.validation.unavailable'),
            ]);
        }

        return to_route('shared-categories.edit')->with(
            'success',
            $existingCategory instanceof Category
                ? __('categories.sharedPage.materialize.flash.reused', ['name' => $sharedCategory->name])
                : __('categories.sharedPage.materialize.flash.created', ['name' => $sharedCategory->name])
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSharedPayload(int $userId): array
    {
        $accounts = $this->accessibleAccountsQuery
            ->get($userId)
            ->filter(fn (Account $account): bool => $this->sharedAccountCategoryTaxonomyService->isSharedAccount($account))
            ->values();

        return [
            'sharedCategories' => [
                'accounts' => $accounts
                    ->map(fn (Account $account): array => $this->sharedAccountCatalogPayload($account, $userId))
                    ->values()
                    ->all(),
            ],
            'options' => [
                'direction_types' => $this->sharedDirectionOptions(),
                'group_types' => $this->sharedGroupOptions(),
            ],
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    protected function sharedDirectionOptions(): array
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
    protected function sharedGroupOptions(): array
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

    /**
     * @return array<string, mixed>
     */
    protected function sharedAccountCatalogPayload(Account $account, int $currentUserId): array
    {
        $categories = $this->sharedCategoriesForAccount($account);

        $flatCategories = collect(CategoryHierarchy::buildFlat($categories))
            ->map(fn (array $category): array => $this->publicCategoryPayload($category))
            ->values()
            ->all();

        $treeCategories = collect(CategoryHierarchy::buildTree($categories))
            ->map(fn (array $category): array => $this->publicCategoryPayload($category))
            ->values()
            ->all();

        return [
            'uuid' => $account->uuid,
            'name' => $account->name,
            'bank_name' => $account->userBank?->name ?? $account->bank?->name,
            'is_owned' => (bool) $account->getAttribute('is_owned'),
            'is_shared' => true,
            'membership_role' => $account->getAttribute('membership_role'),
            'membership_status' => $account->getAttribute('membership_status'),
            'can_edit' => (bool) $account->getAttribute('can_edit'),
            'source_categories' => $this->sourceCategoryOptionsForAccount($account, $currentUserId),
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
        ];
    }

    /**
     * @return Collection<int, Category>
     */
    protected function sharedCategoriesForAccount(Account $account): Collection
    {
        $this->sharedAccountCategoryTaxonomyService->ensureForAccount($account);

        return Category::query()
            ->sharedForAccount($account->id)
            ->with(['account:id,uuid,name'])
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
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'uuid',
                'user_id',
                'account_id',
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
    }

    /**
     * @return list<array{value:string,label:string,owner_user_id:int}>
     */
    protected function sourceCategoryOptionsForAccount(Account $account, int $currentUserId): array
    {
        if (! (bool) $account->getAttribute('can_edit')) {
            return [];
        }

        $sourceCategories = Category::query()
            ->where('user_id', $currentUserId)
            ->whereNull('account_id')
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->where(function ($query): void {
                $query
                    ->whereNull('group_type')
                    ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
            })
            ->with('parent')
            ->orderBy('name')
            ->get([
                'id',
                'user_id',
                'uuid',
                'parent_id',
                'name',
                'slug',
                'direction_type',
                'group_type',
                'sort_order',
                'is_active',
                'is_selectable',
            ]);

        return collect(CategoryHierarchy::buildFlat($sourceCategories))
            ->filter(fn (array $category): bool => (bool) $category['is_selectable'])
            ->filter(fn (array $category): bool => $category['parent_uuid'] !== null)
            ->map(function (array $category) use ($sourceCategories, $account): ?array {
                $sourceCategory = $sourceCategories->firstWhere('id', $category['id']);

                if (! $sourceCategory instanceof Category) {
                    return null;
                }

                $existingCategory = $this->sharedAccountCategoryTaxonomyService->findExistingCategoryForSourceCategory(
                    $account,
                    $sourceCategory,
                );

                if ($existingCategory instanceof Category) {
                    return null;
                }

                return [
                    'value' => (string) $category['uuid'],
                    'label' => (string) $category['full_path'],
                    'owner_user_id' => (int) $sourceCategory->user_id,
                ];
            })
            ->filter(fn (?array $option): bool => $option !== null)
            ->unique('label')
            ->values()
            ->all();
    }

    protected function editableSharedAccount(Request $request, Account $account): Account
    {
        abort_unless(
            $this->sharedAccountCategoryTaxonomyService->isSharedAccount($account)
                && $this->accessibleAccountsQuery->canViewAccountId($request->user(), $account->id),
            404,
        );

        abort_unless(
            $this->accessibleAccountsQuery->canEditAccountId($request->user(), $account->id),
            403,
        );

        return $account;
    }

    protected function sharedAccountCategory(Account $account, Category $category): Category
    {
        abort_unless((int) $category->account_id === $account->id, 404);

        return $category;
    }

    /**
     * @return array<int, string>
     */
    protected function blockingReasons(Category $category): array
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
            'budgets_count' => __('categories.blocking_labels.budgets'),
            'recurring_entries_count' => __('categories.blocking_labels.recurring_entries'),
            'scheduled_entries_count' => __('categories.blocking_labels.scheduled_entries'),
            'default_merchants_count' => __('categories.blocking_labels.default_merchants'),
            'old_transaction_reviews_count' => __('categories.blocking_labels.old_transaction_reviews'),
            'new_transaction_reviews_count' => __('categories.blocking_labels.new_transaction_reviews'),
        ];

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
