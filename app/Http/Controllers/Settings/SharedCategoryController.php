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
use App\Services\Categories\CategoryFoundationService;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Support\Banks\BankNamePresenter;
use App\Supports\CategoryHierarchy;
use App\Supports\HierarchyOptionLabel;
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
                'name_is_custom' => true,
            ]);
        });

        return to_route('shared-categories.edit')->with('success', __('categories.flash.created'));
    }

    public function update(UpdateSharedCategoryRequest $request, Account $account, Category $category): RedirectResponse
    {
        $account = $this->editableSharedAccount($request, $account);
        $category = $this->sharedAccountCategory($account, $category);

        DB::transaction(function () use ($request, $account, $category): void {
            $validated = $request->validated();

            if ($category->is_system) {
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

                if (($validated['is_selectable'] ?? true) !== true) {
                    throw ValidationException::withMessages([
                        'is_selectable' => __('categories.validation.system_classification_locked'),
                    ]);
                }

                $validated['is_active'] = true;
                $validated['is_selectable'] = true;
                $validated['parent_id'] = $category->parent_id;
                $validated['direction_type'] = $category->direction_type?->value;
                $validated['group_type'] = $category->group_type?->value;
            }

            if (array_key_exists('name', $validated) && $validated['name'] !== $category->name) {
                $account->loadMissing('user');
                $validated['name_is_custom'] = $this->nameIsCustomForCategory(
                    $category,
                    (string) $validated['name'],
                    $account->user?->preferredLocale() ?? $request->user()->preferredLocale(),
                );
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

        $sourceAccountIds = $this->sourceAccountIdsForAccount($account, $request->user()->id);

        $sourceCategory = Category::query()
            ->where('uuid', $validated['source_category_uuid'])
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->where(function ($query) use ($request, $sourceAccountIds): void {
                $query
                    ->where(function ($personalQuery) use ($request): void {
                        $personalQuery
                            ->whereNull('account_id')
                            ->where('user_id', $request->user()->id);
                    })
                    ->orWhereIn('account_id', $sourceAccountIds);
            })
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
            'bank_name' => BankNamePresenter::forAccount($account),
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
                'name_is_custom',
                'slug',
                'foundation_key',
                'icon',
                'color',
                'direction_type',
                'group_type',
                'sort_order',
                'is_active',
                'is_selectable',
                'is_system',
            ]);
    }

    /**
     * @return list<array{value:string,label:string,uuid:string,full_path:string,slug:string,owner_user_id:int,source_account_uuid:string|null,source_account_name:string|null,icon:string|null,color:string|null,groupLabel:string|null,badgeLabel:string|null,ancestor_uuids:array<int, string>,is_selectable:bool}>
     */
    protected function sourceCategoryOptionsForAccount(Account $account, int $currentUserId): array
    {
        if (! (bool) $account->getAttribute('can_edit')) {
            return [];
        }

        $sourceAccountIds = $this->sourceAccountIdsForAccount($account, $currentUserId);

        $sourceCategories = Category::query()
            ->where('is_active', true)
            ->where(function ($query) use ($currentUserId, $sourceAccountIds): void {
                $query
                    ->where(function ($personalQuery) use ($currentUserId): void {
                        $personalQuery
                            ->whereNull('account_id')
                            ->where('user_id', $currentUserId);
                    })
                    ->orWhereIn('account_id', $sourceAccountIds);
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('group_type')
                    ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
            })
            ->with([
                'account:id,uuid,name,user_id',
                'parent',
            ])
            ->orderBy('name')
            ->get([
                'id',
                'user_id',
                'account_id',
                'uuid',
                'parent_id',
                'name',
                'name_is_custom',
                'slug',
                'foundation_key',
                'icon',
                'color',
                'direction_type',
                'group_type',
                'sort_order',
                'is_active',
                'is_selectable',
            ]);

        $flatCategories = collect(CategoryHierarchy::buildFlat($sourceCategories));

        $importableCategoryUuids = $flatCategories
            ->filter(function (array $category) use ($sourceCategories, $account): bool {
                if (! (bool) $category['is_selectable'] || $category['parent_uuid'] === null) {
                    return false;
                }

                $sourceCategory = $sourceCategories->firstWhere('id', $category['id']);

                if (! $sourceCategory instanceof Category) {
                    return false;
                }

                return ! $this->sharedAccountCategoryTaxonomyService
                    ->findExistingCategoryForSourceCategory($account, $sourceCategory) instanceof Category;
            })
            ->pluck('uuid')
            ->map(fn (mixed $uuid): string => (string) $uuid)
            ->values();

        $exposedCategoryUuids = $flatCategories
            ->filter(fn (array $category): bool => $importableCategoryUuids->contains((string) $category['uuid']))
            ->flatMap(fn (array $category): array => [
                ...($category['ancestor_uuids'] ?? []),
                (string) $category['uuid'],
            ])
            ->unique()
            ->values();

        return HierarchyOptionLabel::withDisambiguatedLabels(
            $flatCategories
                ->filter(fn (array $category): bool => $exposedCategoryUuids->contains((string) $category['uuid']))
                ->map(function (array $category) use ($sourceCategories, $importableCategoryUuids): ?array {
                    $sourceCategory = $sourceCategories->firstWhere('id', $category['id']);

                    if (! $sourceCategory instanceof Category) {
                        return null;
                    }

                    $sourceAccount = $sourceCategory->account;
                    $groupLabel = $sourceAccount instanceof Account
                        ? $sourceAccount->name
                        : ($category['group_label'] ?? null);
                    $groupLabel = is_string($groupLabel) && $groupLabel !== '' ? $groupLabel : null;
                    $isImportable = $importableCategoryUuids->contains((string) $category['uuid']);

                    return [
                        'value' => (string) $category['uuid'],
                        'uuid' => (string) $category['uuid'],
                        'full_path' => (string) $category['full_path'],
                        'slug' => (string) $category['slug'],
                        'owner_user_id' => (int) $sourceCategory->user_id,
                        'source_account_uuid' => $sourceAccount?->uuid,
                        'source_account_name' => $sourceAccount?->name,
                        'icon' => $category['icon'] ?? null,
                        'color' => $category['color'] ?? null,
                        'groupLabel' => $groupLabel,
                        'badgeLabel' => $groupLabel,
                        'ancestor_uuids' => $category['ancestor_uuids'] ?? [],
                        'is_selectable' => $isImportable,
                    ];
                })
                ->filter(fn (?array $option): bool => $option !== null)
        )
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function sourceAccountIdsForAccount(Account $targetAccount, int $currentUserId): array
    {
        return Account::query()
            ->ownedBy($currentUserId)
            ->whereKeyNot($targetAccount->id)
            ->where('is_active', true)
            ->get(['id'])
            ->reject(fn (Account $account): bool => $this->sharedAccountCategoryTaxonomyService->isSharedAccount($account))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
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

    protected function nameIsCustomForCategory(Category $category, string $name, string $locale): bool
    {
        $resolvedLocale = CategoryFoundationService::resolveFoundationLocale($locale);

        if (
            is_string($category->foundation_key)
            && $category->foundation_key !== ''
            && $name === CategoryFoundationService::localizedRootName($category->foundation_key, $resolvedLocale)
        ) {
            return false;
        }

        if (
            is_string($category->slug)
            && $category->slug !== ''
            && $name === CategoryFoundationService::localizedChildName($category->slug, $resolvedLocale)
        ) {
            return false;
        }

        return true;
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
