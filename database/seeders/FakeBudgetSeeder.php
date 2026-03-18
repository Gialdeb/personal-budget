<?php

namespace Database\Seeders;

use App\Enums\BudgetTypeEnum;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Scope;
use App\Models\User;
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
                'Cane',
                'Extra',
                'Tempo libero',
                'Luce',
                'Gas',
                'Acqua',
                'Internet',
                'Condominio',
            ])
            ->get()
            ->keyBy('name');

        $budgetMap = [
            2024 => [
                'Alimentari' => ['scope' => $personal?->id, 'base' => 320.00],
                'Auto' => ['scope' => $personal?->id, 'base' => 110.00],
                'Salute' => ['scope' => $personal?->id, 'base' => 70.00],
                'Extra' => ['scope' => $personal?->id, 'base' => 120.00],
                'Tempo libero' => ['scope' => $personal?->id, 'base' => 90.00],
                'Luce' => ['scope' => $home1?->id, 'base' => 85.00],
                'Gas' => ['scope' => $home1?->id, 'base' => 78.00],
                'Acqua' => ['scope' => $home1?->id, 'base' => 40.00],
                'Internet' => ['scope' => $home1?->id, 'base' => 28.00],
                'Condominio' => ['scope' => $home1?->id, 'base' => 100.00],
            ],
            2025 => [
                'Alimentari' => ['scope' => $personal?->id, 'base' => 385.00],
                'Auto' => ['scope' => $personal?->id, 'base' => 135.00],
                'Salute' => ['scope' => $personal?->id, 'base' => 90.00],
                'Cane' => ['scope' => $personal?->id, 'base' => 70.00],
                'Extra' => ['scope' => $personal?->id, 'base' => 160.00],
                'Tempo libero' => ['scope' => $personal?->id, 'base' => 120.00],
                'Luce' => ['scope' => $home1?->id, 'base' => 98.00],
                'Gas' => ['scope' => $home1?->id, 'base' => 88.00],
                'Acqua' => ['scope' => $home1?->id, 'base' => 47.00],
                'Internet' => ['scope' => $home1?->id, 'base' => 30.00],
                'Condominio' => ['scope' => $home1?->id, 'base' => 112.00],
            ],
        ];

        $yearFactors = [
            2024 => [1 => 1.00, 2 => 0.98, 3 => 1.02, 4 => 1.01, 5 => 1.05, 6 => 1.04, 7 => 1.07, 8 => 1.08, 9 => 1.01, 10 => 1.03, 11 => 1.00, 12 => 1.12],
            2025 => [1 => 1.02, 2 => 1.01, 3 => 1.04, 4 => 1.03, 5 => 1.06, 6 => 1.08, 7 => 1.10, 8 => 1.12, 9 => 1.05, 10 => 1.06, 11 => 1.07, 12 => 1.18],
        ];

        foreach ($budgetMap as $year => $categoryBudgets) {
            foreach (range(1, 12) as $month) {
                foreach ($categoryBudgets as $categoryName => $config) {
                    $category = $categories[$categoryName] ?? null;

                    if (! $category) {
                        continue;
                    }

                    Budget::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'scope_id' => $config['scope'],
                            'category_id' => $category->id,
                            'year' => $year,
                            'month' => $month,
                            'budget_type' => BudgetTypeEnum::TARGET,
                        ],
                        [
                            'amount' => $this->amountForMonth($categoryName, (float) $config['base'], $yearFactors[$year][$month], $month),
                            'notes' => sprintf('Budget seed %d', $year),
                        ]
                    );
                }
            }
        }
    }

    private function amountForMonth(string $categoryName, float $baseAmount, float $yearFactor, int $month): float
    {
        $categoryFactor = match ($categoryName) {
            'Gas' => in_array($month, [1, 2, 3, 11, 12], true) ? 1.30 : 0.60,
            'Luce' => in_array($month, [7, 8], true) ? 1.15 : 1.00,
            'Tempo libero' => in_array($month, [5, 8, 12], true) ? 1.20 : 0.95,
            'Cane' => in_array($month, [1, 4, 9, 12], true) ? 1.12 : 0.98,
            'Alimentari' => in_array($month, [6, 7, 8, 12], true) ? 1.08 : 1.00,
            'Condominio' => in_array($month, [1, 4, 8, 12], true) ? 1.00 : 0.90,
            default => 1.00,
        };

        return round($baseAmount * $yearFactor * $categoryFactor, 2);
    }
}
