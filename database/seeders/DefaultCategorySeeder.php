<?php

namespace Database\Seeders;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $categories = [
            ['name' => 'Stipendio', 'direction_type' => CategoryDirectionTypeEnum::INCOME, 'group_type' => CategoryGroupTypeEnum::INCOME],
            ['name' => 'Affitto ricevuto', 'direction_type' => CategoryDirectionTypeEnum::INCOME, 'group_type' => CategoryGroupTypeEnum::INCOME],
            ['name' => 'Altre entrate', 'direction_type' => CategoryDirectionTypeEnum::INCOME, 'group_type' => CategoryGroupTypeEnum::INCOME],

            ['name' => 'Alimentari', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::EXPENSE],
            ['name' => 'Auto', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::EXPENSE],
            ['name' => 'Salute', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::EXPENSE],
            ['name' => 'Cane', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::EXPENSE],
            ['name' => 'Extra', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::EXPENSE],
            ['name' => 'Tempo libero', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::EXPENSE],

            ['name' => 'Luce', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::BILL],
            ['name' => 'Gas', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::BILL],
            ['name' => 'Acqua', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::BILL],
            ['name' => 'Internet', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::BILL],
            ['name' => 'Condominio', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::BILL],
            ['name' => 'TARI', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::BILL],

            ['name' => 'Mutuo', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::DEBT],
            ['name' => 'Prestito', 'direction_type' => CategoryDirectionTypeEnum::EXPENSE, 'group_type' => CategoryGroupTypeEnum::DEBT],

            ['name' => 'Fondo emergenze', 'direction_type' => CategoryDirectionTypeEnum::TRANSFER, 'group_type' => CategoryGroupTypeEnum::SAVING],
            ['name' => 'Accantonamento', 'direction_type' => CategoryDirectionTypeEnum::TRANSFER, 'group_type' => CategoryGroupTypeEnum::SAVING],
            ['name' => 'Trasferimento interno', 'direction_type' => CategoryDirectionTypeEnum::TRANSFER, 'group_type' => CategoryGroupTypeEnum::TRANSFER],
        ];

        foreach ($categories as $index => $category) {
            Category::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'slug' => Str::slug($category['name']),
                ],
                [
                    'name' => $category['name'],
                    'direction_type' => $category['direction_type'],
                    'group_type' => $category['group_type'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
