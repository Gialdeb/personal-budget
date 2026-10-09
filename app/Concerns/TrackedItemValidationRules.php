<?php

namespace App\Concerns;

use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Supports\TrackedItemHierarchy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait TrackedItemValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function trackedItemRules(int $userId, ?TrackedItem $trackedItem = null, ?int $accountId = null): array
    {
        $slugUniqueRule = Rule::unique(TrackedItem::class, 'slug')
            ->where(function ($query) use ($userId, $accountId): void {
                if ($accountId === null) {
                    $query->where('user_id', $userId)
                        ->whereNull('account_id');

                    return;
                }

                $query->where('account_id', $accountId);
            });

        if ($trackedItem !== null) {
            $slugUniqueRule = $slugUniqueRule->ignore($trackedItem->id);
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slugUniqueRule,
            ],
            'parent_uuid' => ['nullable', 'uuid'],
            'parent_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:50', 'regex:/^[\pL\pN\s\-_]+$/u'],
            'website_url' => ['nullable', 'string', 'max:2048'],
            'settings' => ['nullable', 'array'],
            'settings.transaction_group_keys' => ['nullable', 'array'],
            'settings.transaction_group_keys.*' => ['string', Rule::in(CategoryGroupTypeEnum::values())],
            'category_uuids' => ['nullable', 'array'],
            'category_uuids.*' => ['uuid'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) use ($userId, $accountId): void {
                    if ($accountId === null) {
                        $query->where('user_id', $userId)->whereNull('account_id');

                        return;
                    }

                    $query->where('account_id', $accountId);
                }),
            ],
            'is_active' => ['required', 'boolean'],
            'is_shared_expense_counterpart' => ['required', 'boolean'],
        ];
    }

    protected function validateParentTrackedItem(
        int $userId,
        ?int $parentId,
        ?TrackedItem $trackedItem = null,
        ?bool $isActive = null,
        ?int $accountId = null
    ): ?string {
        if ($parentId === null) {
            return null;
        }

        $trackedItems = TrackedItem::query()
            ->inCatalog($userId, $accountId)
            ->get(['id', 'parent_id', 'is_active']);

        $parentTrackedItem = $trackedItems->firstWhere('id', $parentId);

        if ($parentTrackedItem === null) {
            return __('ui.the_selected_parent_item_is_invalid_75775810');
        }

        if ($trackedItem !== null && $parentId === $trackedItem->id) {
            return __('ui.a_reference_cannot_be_its_own_parent_21f58273');
        }

        if ($trackedItem !== null) {
            $descendantIds = TrackedItemHierarchy::descendantIds($trackedItems, $trackedItem->id);

            if (in_array($parentId, $descendantIds, true)) {
                return __('ui.a_descendant_cannot_be_assigned_as_a_parent_reference_fbcacdb1');
            }
        }

        if ($isActive === true && ! $parentTrackedItem->is_active) {
            return __('ui.an_active_reference_cannot_belong_to_an_inactive_parent_bf4c8267');
        }

        $subtreeHeightMap = TrackedItemHierarchy::subtreeHeightMap($trackedItems);
        $trackedItemsById = $trackedItems->keyBy('id');
        $parentDepth = 0;
        $currentParentId = $parentId;
        $visited = [];

        while ($currentParentId !== null && $trackedItemsById->has($currentParentId)) {
            if (in_array($currentParentId, $visited, true)) {
                break;
            }

            $visited[] = $currentParentId;
            $currentParentId = $trackedItemsById->get($currentParentId)?->parent_id;

            if ($currentParentId !== null) {
                $parentDepth++;
            }
        }

        $trackedItemSubtreeHeight = $trackedItem instanceof TrackedItem
            ? (int) ($subtreeHeightMap[$trackedItem->id] ?? 0)
            : 0;

        if (($parentDepth + 1 + $trackedItemSubtreeHeight) > 2) {
            return __('ui.the_reference_hierarchy_supports_at_most_three_levels_7abe04d1');
        }

        return null;
    }

    protected function validateTrackedItemNameUniqueness(
        int $userId,
        string $name,
        ?TrackedItem $trackedItem = null,
        ?int $accountId = null,
    ): ?string {
        $normalizedName = mb_strtolower(trim($name));

        if ($normalizedName === '') {
            return null;
        }

        $query = TrackedItem::query()
            ->inCatalog($userId, $accountId)
            ->whereRaw('LOWER(name) = ?', [$normalizedName]);

        if ($trackedItem instanceof TrackedItem) {
            $query->whereKeyNot($trackedItem->id);
        }

        if ($query->exists()) {
            return __('ui.an_item_with_this_name_already_exists_in_this_catalog_a14e96ec');
        }

        return null;
    }

    /**
     * @param  array<int, string>  $categoryUuids
     * @return array<int, int>
     */
    protected function resolveTrackedItemCategoryIds(int $userId, array $categoryUuids, ?int $accountId = null): array
    {
        if ($categoryUuids === []) {
            return [];
        }

        return $this->trackedItemCategoryScopeQuery($userId, $accountId)
            ->whereIn('uuid', $categoryUuids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    protected function trackedItemCategoryScopeQuery(int $userId, ?int $accountId = null)
    {
        if ($accountId === null) {
            return Category::query()->ownedBy($userId);
        }

        return Category::query()->sharedForAccount($accountId);
    }

    protected function normalizeTrackedItemSlug(
        int $userId,
        string $name,
        ?string $slug = null,
        ?TrackedItem $trackedItem = null,
        ?int $accountId = null,
    ): string {
        $normalizedNameSlug = Str::slug($name) ?: 'elemento';
        $normalizedProvidedSlug = Str::slug((string) $slug);

        if ($normalizedProvidedSlug !== '' && $normalizedProvidedSlug !== $normalizedNameSlug) {
            return $normalizedProvidedSlug;
        }

        $candidate = $normalizedProvidedSlug !== '' ? $normalizedProvidedSlug : $normalizedNameSlug;
        $suffix = 2;

        while (
            TrackedItem::query()
                ->inCatalog($userId, $accountId)
                ->when($trackedItem instanceof TrackedItem, fn ($query) => $query->whereKeyNot($trackedItem->id))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = sprintf('%s-%d', $normalizedNameSlug, $suffix++);
        }

        return $candidate;
    }
}
