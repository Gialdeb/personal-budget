<?php

namespace App\Services\Transactions;

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\Scope;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use App\Services\TrackedItems\SharedAccountTrackedItemCatalogService;
use App\Supports\HierarchyOptionLabel;
use App\Supports\TrackedItemHierarchy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalTransactionCategoryResolver
{
    public function __construct(
        protected SharedAccountCategoryTaxonomyService $sharedAccountCategoryTaxonomyService,
        protected SharedAccountTrackedItemCatalogService $sharedAccountTrackedItemCatalogService,
    ) {}

    /**
     * @return array<int, int>
     */
    public function contributorUserIdsForAccount(Account $account): array
    {
        $contributorIds = [$account->user_id];

        $editorIds = AccountMembership::query()
            ->where('account_id', $account->id)
            ->where('status', AccountMembershipStatusEnum::ACTIVE->value)
            ->where('role', AccountMembershipRoleEnum::EDITOR->value)
            ->pluck('user_id')
            ->map(fn ($userId): int => (int) $userId)
            ->values()
            ->all();

        return collect([...$contributorIds, ...$editorIds])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $usedCategoryIds
     * @return Collection<int, Category>
     */
    public function categoriesForAccount(Account $account, array $usedCategoryIds = []): Collection
    {
        if ($this->sharedAccountCategoryTaxonomyService->usesAccountScopedCatalog($account)) {
            return $this->sharedAccountCategoryTaxonomyService->categoriesForAccount($account, $usedCategoryIds);
        }

        return Category::query()
            ->whereIn('user_id', $this->contributorUserIdsForAccount($account))
            ->whereNull('account_id')
            ->withCount('children')
            ->where(function (Builder $query) use ($usedCategoryIds): void {
                $query->where('is_active', true);

                if ($usedCategoryIds !== []) {
                    $query->orWhereIn('id', $usedCategoryIds);
                }
            })
            ->where(function (Builder $query): void {
                $query->whereNull('group_type')
                    ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'user_id',
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
            ]);
    }

    public function findCategoryForAccount(Account $account, int $categoryId): ?Category
    {
        if ($this->sharedAccountCategoryTaxonomyService->usesAccountScopedCatalog($account)) {
            return $this->sharedAccountCategoryTaxonomyService->findCategoryForAccount($account, $categoryId);
        }

        return Category::query()
            ->whereIn('user_id', $this->contributorUserIdsForAccount($account))
            ->whereNull('account_id')
            ->find($categoryId);
    }

    /**
     * @return array<int, int>
     */
    public function categoryContextIdsForAccount(Account $account, int $categoryId): array
    {
        $categories = Category::query()
            ->when(
                $this->sharedAccountCategoryTaxonomyService->usesAccountScopedCatalog($account),
                fn (Builder $query) => $query->sharedForAccount($account->id),
                fn (Builder $query) => $query
                    ->whereIn('user_id', $this->contributorUserIdsForAccount($account))
                    ->whereNull('account_id')
            )
            ->get(['id', 'parent_id'])
            ->keyBy('id');

        $contextIds = [];
        $currentCategoryId = $categoryId;
        $visited = [];

        while ($currentCategoryId > 0 && $categories->has($currentCategoryId)) {
            if (in_array($currentCategoryId, $visited, true)) {
                break;
            }

            $visited[] = $currentCategoryId;
            $contextIds[] = $currentCategoryId;
            $currentCategoryId = (int) ($categories[$currentCategoryId]->parent_id ?? 0);
        }

        return array_values(array_unique($contextIds));
    }

    /**
     * @param  array<int, int>  $usedScopeIds
     * @return Collection<int, Scope>
     */
    public function scopesForAccount(Account $account, array $usedScopeIds = []): Collection
    {
        return Scope::query()
            ->whereIn('user_id', $this->contributorUserIdsForAccount($account))
            ->where(function (Builder $query) use ($usedScopeIds): void {
                $query->where('is_active', true);

                if ($usedScopeIds !== []) {
                    $query->orWhereIn('id', $usedScopeIds);
                }
            })
            ->orderBy('name')
            ->get([
                'id',
                'user_id',
                'uuid',
                'name',
                'type',
                'color',
                'is_active',
            ]);
    }

    public function findScopeForAccount(Account $account, int $scopeId): ?Scope
    {
        return Scope::query()
            ->whereIn('user_id', $this->contributorUserIdsForAccount($account))
            ->find($scopeId);
    }

    /**
     * @param  array<int, int>  $usedTrackedItemIds
     * @return Collection<int, TrackedItem>
     */
    public function trackedItemsForAccount(Account $account, array $usedTrackedItemIds = []): Collection
    {
        $contributorUserIds = $this->contributorUserIdsForAccount($account);
        $usesAccountScopedCatalog = $this->sharedAccountTrackedItemCatalogService->usesAccountScopedCatalog($account);

        if ($usesAccountScopedCatalog) {
            $this->sharedAccountTrackedItemCatalogService->ensureForAccount($account);
        }

        return TrackedItem::query()
            ->where(function (Builder $query) use (
                $account,
                $contributorUserIds,
                $usesAccountScopedCatalog,
                $usedTrackedItemIds,
            ): void {
                if ($usesAccountScopedCatalog) {
                    $query->sharedForAccount($account->id);

                    if ($usedTrackedItemIds !== []) {
                        $query->orWhere(function (Builder $legacyQuery) use ($contributorUserIds, $usedTrackedItemIds): void {
                            $legacyQuery
                                ->whereNull('account_id')
                                ->whereIn('user_id', $contributorUserIds)
                                ->whereIn('id', $usedTrackedItemIds);
                        });
                    }

                    return;
                }

                $query
                    ->whereNull('account_id')
                    ->whereIn('user_id', $contributorUserIds);
            })
            ->with('compatibleCategories:id,uuid')
            ->withCount('children')
            ->where(function (Builder $query) use ($usedTrackedItemIds): void {
                $query->where('is_active', true);

                if ($usedTrackedItemIds !== []) {
                    $query->orWhereIn('id', $usedTrackedItemIds);
                }
            })
            ->orderBy('name')
            ->get([
                'id',
                'user_id',
                'uuid',
                'parent_id',
                'name',
                'slug',
                'type',
                'is_active',
                'settings',
            ])
            ->map(fn (TrackedItem $trackedItem): TrackedItem => $this->normalizeTrackedItemCompatibilityForAccount(
                $account,
                $trackedItem,
            ));
    }

    public function findTrackedItemForAccount(Account $account, int $trackedItemId): ?TrackedItem
    {
        $contributorUserIds = $this->contributorUserIdsForAccount($account);
        $usesAccountScopedCatalog = $this->sharedAccountTrackedItemCatalogService->usesAccountScopedCatalog($account);

        if ($usesAccountScopedCatalog) {
            $this->sharedAccountTrackedItemCatalogService->ensureForAccount($account);
        }

        $trackedItem = TrackedItem::query()
            ->where(function (Builder $query) use (
                $account,
                $contributorUserIds,
                $usesAccountScopedCatalog,
                $trackedItemId,
            ): void {
                if ($usesAccountScopedCatalog) {
                    $query->where('account_id', $account->id)
                        ->orWhere(function (Builder $legacyQuery) use ($account, $contributorUserIds, $trackedItemId): void {
                            if (! $this->isLegacyTrackedItemUsedByAccount($account, $trackedItemId)) {
                                $legacyQuery->whereKey(-1);

                                return;
                            }

                            $legacyQuery
                                ->whereNull('account_id')
                                ->whereIn('user_id', $contributorUserIds)
                                ->where('id', $trackedItemId);
                        });

                    return;
                }

                $query
                    ->whereNull('account_id')
                    ->whereIn('user_id', $contributorUserIds);
            })
            ->with('compatibleCategories:id,uuid,parent_id,user_id')
            ->find($trackedItemId);

        if (! $trackedItem instanceof TrackedItem) {
            return null;
        }

        return $this->normalizeTrackedItemCompatibilityForAccount($account, $trackedItem);
    }

    /**
     * @param  Collection<int, TrackedItem>  $trackedItems
     * @return array<int, array<string, mixed>>
     */
    public function trackedItemOptionsFromCollection(Collection $trackedItems): array
    {
        $trackedItemsById = $trackedItems->keyBy('id');

        return HierarchyOptionLabel::withDisambiguatedLabels(
            collect(TrackedItemHierarchy::buildFlat($trackedItems))
                ->map(function (array $trackedItem) use ($trackedItemsById): array {
                    $sourceTrackedItem = $trackedItemsById->get($trackedItem['id']);

                    return [
                        'id' => $trackedItem['id'],
                        'value' => $trackedItem['uuid'],
                        'uuid' => $trackedItem['uuid'],
                        'full_path' => $trackedItem['full_path'],
                        'slug' => $trackedItem['slug'],
                        'owner_user_id' => $sourceTrackedItem instanceof TrackedItem
                            ? (int) $sourceTrackedItem->user_id
                            : null,
                        'group_keys' => collect($trackedItem['settings']['transaction_group_keys'] ?? [])
                            ->filter(fn ($value): bool => is_string($value) && $value !== '')
                            ->values()
                            ->all(),
                        'category_ids' => collect($trackedItem['compatible_category_ids'] ?? [])
                            ->map(fn ($value): int => (int) $value)
                            ->values()
                            ->all(),
                        'category_uuids' => collect($trackedItem['compatible_category_uuids'] ?? [])
                            ->filter(fn ($value): bool => is_string($value) && $value !== '')
                            ->values()
                            ->all(),
                    ];
                })
        )
            ->values()
            ->all();
    }

    protected function normalizeTrackedItemCompatibilityForAccount(Account $account, TrackedItem $trackedItem): TrackedItem
    {
        if (! $this->sharedAccountCategoryTaxonomyService->isSharedAccount($account)) {
            return $trackedItem;
        }

        $compatibleCategories = $trackedItem->relationLoaded('compatibleCategories')
            ? $trackedItem->compatibleCategories
            : collect();

        $mappedCategories = $compatibleCategories
            ->map(fn (Category $category): ?Category => $this->sharedAccountCategoryTaxonomyService->findCategoryForAccount(
                $account,
                $category->id,
            ))
            ->filter(fn (?Category $category): bool => $category instanceof Category)
            ->unique('id')
            ->values();

        $trackedItem->setRelation('compatibleCategories', $mappedCategories);

        return $trackedItem;
    }

    protected function isLegacyTrackedItemUsedByAccount(Account $account, int $trackedItemId): bool
    {
        return Transaction::query()
            ->where('account_id', $account->id)
            ->where('tracked_item_id', $trackedItemId)
            ->exists();
    }
}
