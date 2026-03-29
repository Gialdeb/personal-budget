<?php

namespace App\Concerns;

use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Supports\TrackedItemHierarchy;
use Illuminate\Contracts\Validation\ValidationRule;
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
            return "L'elemento padre selezionato non è valido.";
        }

        if ($trackedItem !== null && $parentId === $trackedItem->id) {
            return 'Un elemento da tracciare non può avere sé stesso come padre.';
        }

        if ($trackedItem !== null) {
            $descendantIds = TrackedItemHierarchy::descendantIds($trackedItems, $trackedItem->id);

            if (in_array($parentId, $descendantIds, true)) {
                return 'Non puoi assegnare come padre un elemento suo discendente.';
            }
        }

        if ($isActive === true && ! $parentTrackedItem->is_active) {
            return 'Un elemento attivo non può appartenere a un padre disattivato.';
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
            return 'La gerarchia degli elementi tracciati supporta al massimo tre livelli.';
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
}
