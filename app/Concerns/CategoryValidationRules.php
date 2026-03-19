<?php

namespace App\Concerns;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Supports\CategoryHierarchy;
use Illuminate\Contracts\Validation\ValidationRule;
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
                    ? Rule::unique(Category::class)->where('user_id', $userId)
                    : Rule::unique(Category::class)->where('user_id', $userId)->ignore($category->id),
            ],
            'parent_id' => ['nullable', 'integer'],
            'direction_type' => ['required', Rule::enum(CategoryDirectionTypeEnum::class)],
            'group_type' => ['required', Rule::enum(CategoryGroupTypeEnum::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
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
        ?bool $isActive = null
    ): ?string {
        if ($parentId === null) {
            return null;
        }

        $categories = Category::query()
            ->ownedBy($userId)
            ->get(['id', 'parent_id', 'is_active']);

        $parentCategory = $categories->firstWhere('id', $parentId);

        if ($parentCategory === null) {
            return 'La categoria padre selezionata non è valida.';
        }

        if ($category !== null && $parentId === $category->id) {
            return 'Una categoria non può avere sé stessa come categoria padre.';
        }

        if ($category !== null) {
            $descendantIds = CategoryHierarchy::descendantIds($categories, $category->id);

            if (in_array($parentId, $descendantIds, true)) {
                return 'Non puoi assegnare come categoria padre una sua discendente.';
            }
        }

        if ($isActive === true && ! $parentCategory->is_active) {
            return 'Una categoria attiva non può appartenere a una categoria padre disattiva.';
        }

        return null;
    }
}
