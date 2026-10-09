<?php

namespace App\Concerns;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Supports\CategoryHierarchy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait CategoryValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function categoryRules(int $userId, ?Category $category = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $category === null
                    ? Rule::unique(Category::class)->where(fn ($query) => $query
                        ->where('user_id', $userId)
                        ->whereNull('account_id'))
                    : Rule::unique(Category::class)->where(fn ($query) => $query
                        ->where('user_id', $userId)
                        ->whereNull('account_id'))
                        ->ignore($category->id),
            ],
            'parent_uuid' => ['nullable', 'uuid'],
            'parent_id' => ['nullable', 'integer'],
            'direction_type' => ['required', Rule::enum(CategoryDirectionTypeEnum::class)],
            'group_type' => ['required', Rule::enum(CategoryGroupTypeEnum::class)],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'is_active' => ['required', 'boolean'],
            'is_selectable' => ['required', 'boolean'],
        ];
    }

    protected function validateParentCategory(
        int $userId,
        ?int $parentId,
        ?Category $category = null,
        ?bool $isActive = null,
        ?string $directionType = null,
        ?string $groupType = null,
    ): ?string {
        if ($parentId === null) {
            return null;
        }

        $categories = $this->personalCategories($userId);

        $parentCategory = $categories->firstWhere('id', $parentId);

        if ($parentCategory === null) {
            return __('ui.the_selected_parent_category_is_invalid_2bd73faa');
        }

        if ($category !== null && $parentId === $category->id) {
            return __('ui.a_category_cannot_be_its_own_parent_362a9b26');
        }

        if ($category !== null) {
            $descendantIds = CategoryHierarchy::descendantIds($categories, $category->id);

            if (in_array($parentId, $descendantIds, true)) {
                return __('ui.a_descendant_cannot_be_assigned_as_a_parent_category_c4b18b25');
            }
        }

        if ($isActive === true && ! $parentCategory->is_active) {
            return __('ui.an_active_category_cannot_belong_to_an_inactive_parent_category_0d5ee935');
        }

        if (
            $category !== null
            && $category->parent_id !== null
            && $parentId !== $category->parent_id
            && (
                $parentCategory->direction_type?->value !== $category->direction_type?->value
                || $parentCategory->group_type?->value !== $category->group_type?->value
            )
        ) {
            return __('ui.a_child_category_can_only_be_moved_to_a_branch_compatible_with_i_c3647c3c');
        }

        $parentDepth = $this->categoryDepth($categories, $parentCategory->id);
        $subtreeHeight = $category !== null
            ? $this->categorySubtreeHeight($categories, $category)
            : 0;

        if (($parentDepth + 1 + $subtreeHeight) > 2) {
            return __('categories.validation.max_depth');
        }

        if (
            $directionType !== null
            && $parentCategory->direction_type?->value !== null
            && $directionType !== $parentCategory->direction_type->value
        ) {
            return __('ui.a_child_category_must_have_the_same_financial_direction_as_its_p_a00486b6');
        }

        if (
            $groupType !== null
            && $parentCategory->group_type?->value !== null
            && $groupType !== $parentCategory->group_type->value
        ) {
            return __('ui.a_child_category_must_have_the_same_group_as_its_parent_bf8c3e20');
        }

        return null;
    }

    protected function validateSiblingCategoryNameUniqueness(
        int $userId,
        string $name,
        ?int $parentId,
        ?Category $category = null,
    ): ?string {
        $normalizedName = mb_strtolower(trim($name));

        if ($normalizedName === '') {
            return null;
        }

        $query = Category::query()
            ->ownedBy($userId)
            ->when(
                $parentId === null,
                fn ($builder) => $builder->whereNull('parent_id'),
                fn ($builder) => $builder->where('parent_id', $parentId),
            )
            ->where(DB::raw('LOWER(name)'), '=', $normalizedName);

        if ($category instanceof Category) {
            $query->whereKeyNot($category->id);
        }

        if ($query->exists()) {
            return __('ui.a_category_with_this_name_already_exists_at_this_level_a0923160');
        }

        return null;
    }

    /**
     * @return Collection<int, Category>
     */
    protected function personalCategories(int $userId): Collection
    {
        return Category::query()
            ->ownedBy($userId)
            ->get(['id', 'parent_id', 'is_active', 'direction_type', 'group_type']);
    }

    protected function categoryDepth(Collection $categories, int $categoryId): int
    {
        $depth = 0;
        $current = $categories->firstWhere('id', $categoryId);
        $visited = [];

        while ($current !== null && $current->parent_id !== null) {
            if (in_array($current->id, $visited, true)) {
                break;
            }

            $visited[] = $current->id;
            $depth++;
            $current = $categories->firstWhere('id', $current->parent_id);
        }

        return $depth;
    }

    protected function categorySubtreeHeight(Collection $categories, Category $category): int
    {
        $childrenByParent = $categories->groupBy('parent_id');
        $cache = [];

        $resolve = function (int $categoryId) use (&$resolve, $childrenByParent, &$cache): int {
            if (array_key_exists($categoryId, $cache)) {
                return $cache[$categoryId];
            }

            /** @var Collection<int, Category> $children */
            $children = $childrenByParent->get($categoryId, collect());

            if ($children->isEmpty()) {
                $cache[$categoryId] = 0;

                return 0;
            }

            $cache[$categoryId] = 1 + $children
                ->map(fn (Category $child): int => $resolve($child->id))
                ->max();

            return $cache[$categoryId];
        };

        return $resolve($category->id);
    }

    protected function normalizePersonalCategorySlug(int $userId, string $name, ?string $slug = null, ?Category $category = null): string
    {
        $normalizedNameSlug = Str::slug($name) ?: 'categoria';
        $normalizedProvidedSlug = Str::slug((string) $slug);

        if ($normalizedProvidedSlug !== '' && $normalizedProvidedSlug !== $normalizedNameSlug) {
            return $normalizedProvidedSlug;
        }

        $candidate = $normalizedProvidedSlug !== '' ? $normalizedProvidedSlug : $normalizedNameSlug;
        $suffix = 2;

        while (
            Category::query()
                ->ownedBy($userId)
                ->when($category instanceof Category, fn ($query) => $query->whereKeyNot($category->id))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = sprintf('%s-%d', $normalizedNameSlug, $suffix++);
        }

        return $candidate;
    }
}
