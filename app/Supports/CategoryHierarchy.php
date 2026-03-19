<?php

namespace App\Supports;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryHierarchy
{
    public static function buildFlat(Collection $categories): array
    {
        $childrenByParent = $categories->groupBy('parent_id');
        $descendantMap = static::descendantMap($categories);

        return static::flatten($childrenByParent, $descendantMap);
    }

    public static function buildTree(Collection $categories): array
    {
        $childrenByParent = $categories->groupBy('parent_id');
        $descendantMap = static::descendantMap($categories);

        return static::branch($childrenByParent, $descendantMap);
    }

    public static function descendantIds(Collection $categories, int $categoryId): array
    {
        return static::descendantMap($categories)[$categoryId] ?? [];
    }

    /**
     * @return array<int, array<int, int>>
     */
    public static function descendantMap(Collection $categories): array
    {
        $childrenByParent = $categories->groupBy('parent_id');
        $cache = [];

        $resolve = function (int $categoryId) use (&$resolve, $childrenByParent, &$cache): array {
            if (array_key_exists($categoryId, $cache)) {
                return $cache[$categoryId];
            }

            $descendantIds = [];

            /** @var Category $child */
            foreach ($childrenByParent->get($categoryId, collect()) as $child) {
                $descendantIds[] = $child->id;
                $descendantIds = array_merge($descendantIds, $resolve($child->id));
            }

            $cache[$categoryId] = array_values(array_unique($descendantIds));

            return $cache[$categoryId];
        };

        /** @var Category $category */
        foreach ($categories as $category) {
            $resolve($category->id);
        }

        return $cache;
    }

    /**
     * @param  Collection<int, Collection<int, Category>>  $childrenByParent
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

        /** @var Category $category */
        foreach ($childrenByParent->get($parentId, collect()) as $category) {
            $path = [...$ancestorNames, $category->name];

            $items[] = static::payload(
                $category,
                $depth,
                implode(' > ', $path),
                $descendantMap[$category->id] ?? []
            );

            $items = [
                ...$items,
                ...static::flatten(
                    $childrenByParent,
                    $descendantMap,
                    $category->id,
                    $path,
                    $depth + 1
                ),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, Collection<int, Category>>  $childrenByParent
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
            ->map(function (Category $category) use (
                $ancestorNames,
                $childrenByParent,
                $descendantMap,
                $depth
            ): array {
                $path = [...$ancestorNames, $category->name];

                return [
                    ...static::payload(
                        $category,
                        $depth,
                        implode(' > ', $path),
                        $descendantMap[$category->id] ?? []
                    ),
                    'children' => static::branch(
                        $childrenByParent,
                        $descendantMap,
                        $category->id,
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
        Category $category,
        int $depth,
        string $fullPath,
        array $descendantIds
    ): array {
        $usageCount = collect([
            'transactions_count',
            'transaction_splits_count',
            'transaction_matchers_count',
            'transaction_training_samples_count',
            'budgets_count',
            'recurring_entries_count',
            'scheduled_entries_count',
            'default_merchants_count',
            'old_transaction_reviews_count',
            'new_transaction_reviews_count',
        ])->sum(fn (string $countKey): int => (int) ($category->{$countKey} ?? 0));

        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'icon' => $category->icon,
            'color' => $category->color,
            'direction_type' => $category->direction_type?->value,
            'direction_label' => $category->direction_type?->label(),
            'group_type' => $category->group_type?->value,
            'group_label' => $category->group_type?->label(),
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'is_selectable' => $category->is_selectable,
            'depth' => $depth,
            'full_path' => $fullPath,
            'children_count' => (int) ($category->children_count ?? 0),
            'usage_count' => $usageCount,
            'is_deletable' => (int) ($category->children_count ?? 0) === 0 && $usageCount === 0,
            'descendant_ids' => $descendantIds,
        ];
    }
}
