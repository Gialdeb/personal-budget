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
        $uuidMap = $trackedItems
            ->mapWithKeys(fn (TrackedItem $trackedItem): array => [$trackedItem->id => $trackedItem->uuid])
            ->all();

        return static::flatten($childrenByParent, $descendantMap, $uuidMap, null, [], [], [], 0);
    }

    public static function buildTree(Collection $trackedItems): array
    {
        $childrenByParent = $trackedItems->groupBy('parent_id');
        $descendantMap = static::descendantMap($trackedItems);
        $uuidMap = $trackedItems
            ->mapWithKeys(fn (TrackedItem $trackedItem): array => [$trackedItem->id => $trackedItem->uuid])
            ->all();

        return static::branch($childrenByParent, $descendantMap, $uuidMap);
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

        $resolve = function (?int $trackedItemId) use (&$resolve, $childrenByParent, &$cache): array {
            if ($trackedItemId === null) {
                return [];
            }
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
        array $uuidMap,
        ?int $parentId = null,
        array $ancestorNames = [],
        array $ancestorUuids = [],
        array $ancestorIds = [],
        int $depth = 0
    ): array {
        $items = [];

        /** @var TrackedItem $trackedItem */
        foreach ($childrenByParent->get($parentId, collect()) as $trackedItem) {
            // Prevent infinite loops by checking if this item is already in the path
            if (in_array($trackedItem->uuid, $ancestorUuids, true) || in_array($trackedItem->id, $ancestorIds, true)) {
                continue;
            }

            $path = [...$ancestorNames, $trackedItem->name];
            $pathUuids = [...$ancestorUuids, $trackedItem->uuid];
            $pathIds = [...$ancestorIds, $trackedItem->id];

            $items[] = static::payload(
                $trackedItem,
                $depth,
                implode(' > ', $path),
                $ancestorNames,
                $ancestorUuids,
                $ancestorIds,
                $uuidMap,
                $descendantMap[$trackedItem->id] ?? []
            );

            $items = [
                ...$items,
                ...static::flatten(
                    $childrenByParent,
                    $descendantMap,
                    $uuidMap,
                    $trackedItem->id,
                    $path,
                    $pathUuids,
                    $pathIds,
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
        array $uuidMap,
        ?int $parentId = null,
        array $ancestorNames = [],
        array $ancestorUuids = [],
        int $depth = 0
    ): array {
        return $childrenByParent->get($parentId, collect())
            ->filter(function (TrackedItem $trackedItem) use ($ancestorUuids): bool {
                // Prevent infinite loops by checking if this item is already in the path
                return ! in_array($trackedItem->uuid, $ancestorUuids, true);
            })
            ->map(function (TrackedItem $trackedItem) use (
                $ancestorNames,
                $ancestorUuids,
                $childrenByParent,
                $descendantMap,
                $uuidMap,
                $depth
            ): array {
                $path = [...$ancestorNames, $trackedItem->name];
                $pathUuids = [...$ancestorUuids, $trackedItem->uuid];

                return [
                    ...static::payload(
                        $trackedItem,
                        $depth,
                        implode(' > ', $path),
                        $ancestorNames,
                        $ancestorUuids,
                        [],
                        $uuidMap,
                        $descendantMap[$trackedItem->id] ?? []
                    ),
                    'children' => static::branch(
                        $childrenByParent,
                        $descendantMap,
                        $uuidMap,
                        $trackedItem->id,
                        $path,
                        $pathUuids,
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
        array $ancestorUuids,
        array $ancestorIds,
        array $uuidMap,
        array $descendantIds
    ): array {
        $descendantUuids = collect($descendantIds)
            ->map(fn (int $descendantId): ?string => $uuidMap[$descendantId] ?? null)
            ->filter()
            ->values()
            ->all();

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
            'uuid' => $trackedItem->uuid,
            'parent_uuid' => $ancestorUuids !== [] ? $ancestorUuids[count($ancestorUuids) - 1] : null,
            'name' => $trackedItem->name,
            'slug' => $trackedItem->slug,
            'type' => $trackedItem->type,
            'settings' => $trackedItem->settings,
            'compatible_category_uuids' => $trackedItem->relationLoaded('compatibleCategories')
                ? $trackedItem->compatibleCategories
                    ->pluck('uuid')
                    ->filter(fn ($uuid): bool => is_string($uuid) && $uuid !== '')
                    ->values()
                    ->all()
                : [],
            'compatible_category_ids' => $trackedItem->relationLoaded('compatibleCategories')
                ? $trackedItem->compatibleCategories
                    ->pluck('id')
                    ->filter(fn ($id): bool => is_int($id))
                    ->values()
                    ->all()
                : [],
            'is_active' => (bool) $trackedItem->is_active,
            'depth' => $depth,
            'full_path' => $fullPath,
            'parent_name' => $ancestorNames[count($ancestorNames) - 1] ?? null,
            'parent_full_path' => $ancestorNames !== [] ? implode(' > ', $ancestorNames) : null,
            'ancestor_ids' => $ancestorIds,
            'ancestor_uuids' => $ancestorUuids,
            'children_count' => $counts['children'],
            'counts' => $counts,
            'usage_count' => $usageCount,
            'used' => $usageCount > 0,
            'is_deletable' => $counts['children'] === 0 && $usageCount === 0,
            'descendant_uuids' => $descendantUuids,
        ];
    }
}
