<?php

namespace Database\Seeders;

use App\Enums\BudgetTypeEnum;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Scope;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FakeBudgetSeeder extends Seeder
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

        $personal = Scope::where('user_id', $user->id)->where('name', 'Personale')->first();
        $home1 = Scope::where('user_id', $user->id)->where('name', 'Casa 1')->first();

        $categories = Category::where('user_id', $user->id)
            ->whereIn('name', [
                'Alimentari',
                'Auto',
                'Salute',
                'Extra',
                'Luce',
                'Acqua',
                'Condominio',
            ])
            ->get()
            ->keyBy('name');

        $budgetMap = [
            'Alimentari' => ['scope' => $personal?->id, 'amount' => 350],
            'Auto' => ['scope' => $personal?->id, 'amount' => 120],
            'Salute' => ['scope' => $personal?->id, 'amount' => 80],
            'Extra' => ['scope' => $personal?->id, 'amount' => 150],
            'Luce' => ['scope' => $home1?->id, 'amount' => 90],
            'Acqua' => ['scope' => $home1?->id, 'amount' => 45],
            'Condominio' => ['scope' => $home1?->id, 'amount' => 110],
        ];

        foreach (range(1, 12) as $month) {
            foreach ($budgetMap as $categoryName => $config) {
                $category = $categories[$categoryName] ?? null;

                if (! $category) {
                    continue;
                }

                Budget::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'scope_id' => $config['scope'],
                        'category_id' => $category->id,
                        'year' => 2025,
                        'month' => $month,
                        'budget_type' => BudgetTypeEnum::TARGET,
                    ],
                    [
                        'amount' => $config['amount'],
                        'notes' => 'Budget seed 2025',
                    ]
                );
            }
        }
    }
}
