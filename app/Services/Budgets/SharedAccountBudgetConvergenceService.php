<?php

namespace App\Services\Budgets;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use Illuminate\Support\Facades\DB;

class SharedAccountBudgetConvergenceService
{
    public function __construct(
        protected SharedAccountCategoryTaxonomyService $sharedAccountCategoryTaxonomyService,
    ) {}

    public function ensureForAccount(Account $account): void
    {
        if (! $this->sharedAccountCategoryTaxonomyService->usesAccountScopedCatalog($account)) {
            return;
        }

        DB::transaction(function () use ($account): void {
            Budget::query()
                ->with('category')
                ->whereHas('category', function ($query) use ($account): void {
                    $query->where('account_id', $account->id);
                })
                ->get()
                ->each(function (Budget $budget) use ($account): void {
                    $sharedCategory = $budget->category;

                    if (! $sharedCategory instanceof Category) {
                        return;
                    }

                    $sourceCategory = $this->sharedAccountCategoryTaxonomyService
                        ->findSourceCategoryForSharedCategory($account, $sharedCategory);

                    if (! $sourceCategory instanceof Category) {
                        return;
                    }

                    $targetAttributes = [
                        'user_id' => $account->user_id,
                        'scope_id' => $budget->scope_id,
                        'tracked_item_id' => $budget->tracked_item_id,
                        'category_id' => $sourceCategory->id,
                        'year' => $budget->year,
                        'month' => $budget->month,
                        'budget_type' => $budget->budget_type?->value ?? $budget->budget_type,
                    ];

                    $existingBudget = Budget::query()
                        ->where($targetAttributes)
                        ->first();

                    if ($existingBudget instanceof Budget && (int) $existingBudget->id !== (int) $budget->id) {
                        $existingBudget->forceFill([
                            'amount' => round((float) $existingBudget->amount + (float) $budget->amount, 2),
                            'notes' => $existingBudget->notes ?: $budget->notes,
                        ])->save();

                        $budget->delete();

                        return;
                    }

                    $budget->forceFill([
                        'user_id' => $account->user_id,
                        'category_id' => $sourceCategory->id,
                    ])->save();
                });
        });
    }
}
