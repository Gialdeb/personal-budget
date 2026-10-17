<?php

namespace App\Services\Categories;

use App\Models\Budget;
use App\Models\Category;

class CategoryBudgetTransferService
{
    public function transfer(Category $from, Category $to): void
    {
        if ($from->id === $to->id) {
            return;
        }

        $budgets = Budget::query()
            ->where('category_id', $from->id)
            ->get();

        foreach ($budgets as $budget) {
            $existingBudget = Budget::query()
                ->where('user_id', $budget->user_id)
                ->where('scope_id', $budget->scope_id)
                ->where('tracked_item_id', $budget->tracked_item_id)
                ->where('category_id', $to->id)
                ->where('year', $budget->year)
                ->where('month', $budget->month)
                ->where('budget_type', $budget->budget_type)
                ->first();

            if ($existingBudget !== null) {
                $existingBudget->update([
                    'amount' => round((float) $existingBudget->amount + (float) $budget->amount, 2),
                ]);

                $budget->delete();

                continue;
            }

            $budget->update([
                'category_id' => $to->id,
            ]);
        }
    }
}
