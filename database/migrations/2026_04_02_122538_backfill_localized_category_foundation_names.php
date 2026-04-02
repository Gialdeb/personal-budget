<?php

use App\Models\Account;
use App\Models\User;
use App\Services\Categories\CategoryFoundationService;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categoryFoundationService = app(CategoryFoundationService::class);
        $sharedAccountCategoryTaxonomyService = app(SharedAccountCategoryTaxonomyService::class);

        User::query()
            ->select(['id', 'locale'])
            ->cursor()
            ->each(function (User $user) use ($categoryFoundationService): void {
                $categoryFoundationService->backfillLocalizedDefaultsForUser($user);
            });

        Account::query()
            ->whereHas('categories')
            ->with('user:id,locale')
            ->select(['id', 'user_id'])
            ->cursor()
            ->each(function (Account $account) use ($sharedAccountCategoryTaxonomyService): void {
                $sharedAccountCategoryTaxonomyService->backfillLocalizedDefaultsForAccount($account);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Forward-only data backfill.
    }
};
