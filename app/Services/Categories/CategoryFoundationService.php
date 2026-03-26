<?php

namespace App\Services\Categories;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Models\User;

class CategoryFoundationService
{
    /**
     * @return list<array{
     *     foundation_key:string,
     *     name:string,
     *     slug:string,
     *     icon:string,
     *     color:string,
     *     direction_type:CategoryDirectionTypeEnum,
     *     group_type:CategoryGroupTypeEnum,
     *     sort_order:int
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'foundation_key' => 'income',
                'name' => 'Entrate',
                'slug' => 'entrate',
                'icon' => 'circle-dollar-sign',
                'color' => '#15803d',
                'direction_type' => CategoryDirectionTypeEnum::INCOME,
                'group_type' => CategoryGroupTypeEnum::INCOME,
                'sort_order' => 1,
            ],
            [
                'foundation_key' => 'expense',
                'name' => 'Spese',
                'slug' => 'spese',
                'icon' => 'credit-card',
                'color' => '#e11d48',
                'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
                'group_type' => CategoryGroupTypeEnum::EXPENSE,
                'sort_order' => 2,
            ],
            [
                'foundation_key' => 'bill',
                'name' => 'Bollette',
                'slug' => 'bollette',
                'icon' => 'receipt',
                'color' => '#1d4ed8',
                'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
                'group_type' => CategoryGroupTypeEnum::BILL,
                'sort_order' => 3,
            ],
            [
                'foundation_key' => 'debt',
                'name' => 'Debiti',
                'slug' => 'debiti',
                'icon' => 'hand-coins',
                'color' => '#7c3aed',
                'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
                'group_type' => CategoryGroupTypeEnum::DEBT,
                'sort_order' => 4,
            ],
            [
                'foundation_key' => 'saving',
                'name' => 'Risparmi',
                'slug' => 'risparmi',
                'icon' => 'piggy-bank',
                'color' => '#ca8a04',
                'direction_type' => CategoryDirectionTypeEnum::TRANSFER,
                'group_type' => CategoryGroupTypeEnum::SAVING,
                'sort_order' => 5,
            ],
        ];
    }

    public function ensureForUser(User $user): void
    {
        foreach (self::definitions() as $definition) {
            $category = Category::query()->firstOrNew([
                'user_id' => $user->id,
                'foundation_key' => $definition['foundation_key'],
            ]);

            $category->user_id = $user->id;
            $category->parent_id = null;
            $category->foundation_key = $definition['foundation_key'];
            $category->name = $definition['name'];
            $category->slug = $definition['slug'];
            $category->direction_type = $definition['direction_type'];
            $category->group_type = $definition['group_type'];
            $category->is_active = true;
            $category->is_system = true;

            if (! $category->exists) {
                $category->sort_order = $definition['sort_order'];
                $category->icon = $definition['icon'];
                $category->color = $definition['color'];
                $category->is_selectable = true;
            } else {
                $category->icon ??= $definition['icon'];
                $category->color ??= $definition['color'];
            }

            $category->save();
        }
    }
}
