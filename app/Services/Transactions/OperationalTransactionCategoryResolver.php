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
use App\Supports\TrackedItemHierarchy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalTransactionCategoryResolver
{
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
        return Category::query()
            ->whereIn('user_id', $this->contributorUserIdsForAccount($account))
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
        return Category::query()
            ->whereIn('user_id', $this->contributorUserIdsForAccount($account))
            ->find($categoryId);
    }

    /**
     * @return array<int, int>
     */
    public function categoryContextIdsForAccount(Account $account, int $categoryId): array
    {
        $categories = Category::query()
            ->whereIn('user_id', $this->contributorUserIdsForAccount($account))
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
        return TrackedItem::query()
            ->whereIn('user_id', $this->contributorUserIdsForAccount($account))
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
            ]);
    }

    public function findTrackedItemForAccount(Account $account, int $trackedItemId): ?TrackedItem
    {
        return TrackedItem::query()
            ->whereIn('user_id', $this->contributorUserIdsForAccount($account))
            ->with('compatibleCategories:id,uuid,parent_id,user_id')
            ->find($trackedItemId);
    }

    /**
     * @param  Collection<int, TrackedItem>  $trackedItems
     * @return array<int, array<string, mixed>>
     */
    public function trackedItemOptionsFromCollection(Collection $trackedItems): array
    {
        $trackedItemsById = $trackedItems->keyBy('id');

        return collect(TrackedItemHierarchy::buildFlat($trackedItems))
            ->map(function (array $trackedItem) use ($trackedItemsById): array {
                $sourceTrackedItem = $trackedItemsById->get($trackedItem['id']);

                return [
                    'id' => $trackedItem['id'],
                    'value' => $trackedItem['uuid'],
                    'uuid' => $trackedItem['uuid'],
                    'label' => $trackedItem['full_path'],
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
            ->values()
            ->all();
    }
}
