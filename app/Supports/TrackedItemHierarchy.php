<?php

namespace App\Supports;

use App\Models\TrackedItem;
use Illuminate\Support\Collection;

class TrackedItemHierarchy
{
    public static function buildFlat(Collection $trackedItems): array
    {
        $childrenByParent = $trackedItems->groupBy('parent_id');
        $descendantMap = static::descendantMap($trackedItems);

        return static::flatten($childrenByParent, $descendantMap);
    }

    public static function buildTree(Collection $trackedItems): array
    {
        $childrenByParent = $trackedItems->groupBy('parent_id');
        $descendantMap = static::descendantMap($trackedItems);

        return static::branch($childrenByParent, $descendantMap);
    }

    public static function descendantIds(Collection $trackedItems, int $trackedItemId): array
    {
        return static::descendantMap($trackedItems)[$trackedItemId] ?? [];
    }

    /**
     * @return array<int, array<int, int>>
     */
    public static function descendantMap(Collection $trackedItems): array
    {
        $childrenByParent = $trackedItems->groupBy('parent_id');
        $cache = [];

        $resolve = function (int $trackedItemId) use (&$resolve, $childrenByParent, &$cache): array {
            if (array_key_exists($trackedItemId, $cache)) {
                return $cache[$trackedItemId];
            }

            $descendantIds = [];

            /** @var TrackedItem $child */
            foreach ($childrenByParent->get($trackedItemId, collect()) as $child) {
                $descendantIds[] = $child->id;
                $descendantIds = array_merge($descendantIds, $resolve($child->id));
            }

            $cache[$trackedItemId] = array_values(array_unique($descendantIds));

            return $cache[$trackedItemId];
        };

        /** @var TrackedItem $trackedItem */
        foreach ($trackedItems as $trackedItem) {
            $resolve($trackedItem->id);
        }

        return $cache;
    }

    /**
     * @param  Collection<int, Collection<int, TrackedItem>>  $childrenByParent
     * @param  array<int, array<int, int>>  $descendantMap
     * @return array<int, array<string, mixed>>
     */
    protected static function flatten(
        Collection $childrenByParent,
        array $descendantMap,
        ?int $parentId = null,
        array $ancestorNames = [],
        int $depth = 0
    ): array {
        $items = [];

        /** @var TrackedItem $trackedItem */
        foreach ($childrenByParent->get($parentId, collect()) as $trackedItem) {
            $path = [...$ancestorNames, $trackedItem->name];

            $items[] = static::payload(
                $trackedItem,
                $depth,
                implode(' > ', $path),
                $ancestorNames,
                $descendantMap[$trackedItem->id] ?? []
            );

            $items = [
                ...$items,
                ...static::flatten(
                    $childrenByParent,
                    $descendantMap,
                    $trackedItem->id,
                    $path,
                    $depth + 1
                ),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, Collection<int, TrackedItem>>  $childrenByParent
     * @param  array<int, array<int, int>>  $descendantMap
     * @return array<int, array<string, mixed>>
     */
    protected static function branch(
        Collection $childrenByParent,
        array $descendantMap,
        ?int $parentId = null,
        array $ancestorNames = [],
        int $depth = 0
    ): array {
        return $childrenByParent->get($parentId, collect())
            ->map(function (TrackedItem $trackedItem) use (
                $ancestorNames,
                $childrenByParent,
                $descendantMap,
                $depth
            ): array {
                $path = [...$ancestorNames, $trackedItem->name];

                return [
                    ...static::payload(
                        $trackedItem,
                        $depth,
                        implode(' > ', $path),
                        $ancestorNames,
                        $descendantMap[$trackedItem->id] ?? []
                    ),
                    'children' => static::branch(
                        $childrenByParent,
                        $descendantMap,
                        $trackedItem->id,
                        $path,
                        $depth + 1
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $descendantIds
     * @return array<string, mixed>
     */
    protected static function payload(
        TrackedItem $trackedItem,
        int $depth,
        string $fullPath,
        array $ancestorNames,
        array $descendantIds
    ): array {
        $counts = [
            'children' => (int) ($trackedItem->children_count ?? 0),
            'transactions' => (int) ($trackedItem->transactions_count ?? 0),
            'budgets' => (int) ($trackedItem->budgets_count ?? 0),
            'recurring_entries' => (int) ($trackedItem->recurring_entries_count ?? 0),
            'scheduled_entries' => (int) ($trackedItem->scheduled_entries_count ?? 0),
        ];

        $usageCount = $counts['transactions']
            + $counts['budgets']
            + $counts['recurring_entries']
            + $counts['scheduled_entries'];

        return [
            'id' => $trackedItem->id,
            'parent_id' => $trackedItem->parent_id,
            'name' => $trackedItem->name,
            'slug' => $trackedItem->slug,
            'type' => $trackedItem->type,
            'is_active' => (bool) $trackedItem->is_active,
            'depth' => $depth,
            'full_path' => $fullPath,
            'parent_name' => $ancestorNames[count($ancestorNames) - 1] ?? null,
            'parent_full_path' => $ancestorNames !== [] ? implode(' > ', $ancestorNames) : null,
            'children_count' => $counts['children'],
            'counts' => $counts,
            'usage_count' => $usageCount,
            'used' => $usageCount > 0,
            'is_deletable' => $counts['children'] === 0 && $usageCount === 0,
            'descendant_ids' => $descendantIds,
        ];
    }
}
