<?php

namespace App\Services\TrackedItems;

use App\Models\Account;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SharedAccountTrackedItemCatalogService
{
    public function __construct(
        protected SharedAccountCategoryTaxonomyService $sharedAccountCategoryTaxonomyService,
    ) {}

    public function usesAccountScopedCatalog(Account $account): bool
    {
        if ($this->sharedAccountCategoryTaxonomyService->isSharedAccount($account)) {
            return true;
        }

        return TrackedItem::query()
            ->where('account_id', $account->id)
            ->exists();
    }

    public function ensureForAccount(Account $account): void
    {
        if (! $this->usesAccountScopedCatalog($account)) {
            return;
        }

        DB::transaction(function () use ($account): void {
            Transaction::query()
                ->where('account_id', $account->id)
                ->whereNotNull('tracked_item_id')
                ->with([
                    'trackedItem.compatibleCategories:id,uuid,user_id,account_id,parent_id,is_active',
                ])
                ->get()
                ->each(function (Transaction $transaction) use ($account): void {
                    $trackedItem = $transaction->trackedItem;

                    if (! $trackedItem instanceof TrackedItem) {
                        return;
                    }

                    if ((int) ($trackedItem->account_id ?? 0) === (int) $account->id) {
                        return;
                    }

                    $sharedTrackedItem = $this->materializeSourceTrackedItemForAccount(
                        $account,
                        $trackedItem,
                        $transaction->category_id !== null ? [(int) $transaction->category_id] : [],
                    );

                    if (! $sharedTrackedItem instanceof TrackedItem) {
                        return;
                    }

                    if ((int) $sharedTrackedItem->id === (int) $transaction->tracked_item_id) {
                        return;
                    }

                    $transaction->forceFill([
                        'tracked_item_id' => $sharedTrackedItem->id,
                    ])->save();
                });
        });
    }

    public function findExistingTrackedItemForSourceTrackedItem(Account $account, TrackedItem $sourceTrackedItem): ?TrackedItem
    {
        return TrackedItem::query()
            ->where('account_id', $account->id)
            ->where('slug', $sourceTrackedItem->slug)
            ->first();
    }

    /**
     * @param  array<int, int>  $fallbackCategoryIds
     */
    public function materializeSourceTrackedItemForAccount(
        Account $account,
        TrackedItem $sourceTrackedItem,
        array $fallbackCategoryIds = [],
    ): ?TrackedItem {
        $sourceTrackedItem->loadMissing('compatibleCategories:id,uuid,user_id,account_id,parent_id,is_active');

        $mappedCategoryIds = array_values(array_unique([
            ...$this->mappedAccountCategoryIds($account, $sourceTrackedItem, true),
            ...$this->mappedFallbackCategoryIds($account, $fallbackCategoryIds),
        ]));

        if ($mappedCategoryIds === []) {
            return null;
        }

        $sharedTrackedItem = $this->findExistingTrackedItemForSourceTrackedItem($account, $sourceTrackedItem)
            ?? TrackedItem::query()->create([
                'user_id' => $account->user_id,
                'account_id' => $account->id,
                'parent_id' => null,
                'name' => $sourceTrackedItem->name,
                'slug' => $sourceTrackedItem->slug,
                'type' => $sourceTrackedItem->type,
                'is_active' => $sourceTrackedItem->is_active,
                'settings' => $this->sharedTrackedItemSettings($account, $sourceTrackedItem, $mappedCategoryIds),
            ]);

        $sharedTrackedItem->forceFill([
            'settings' => $this->sharedTrackedItemSettings($account, $sourceTrackedItem, $mappedCategoryIds),
            'is_active' => $sourceTrackedItem->is_active,
        ])->save();

        $existingCategoryIds = $sharedTrackedItem->compatibleCategories()
            ->pluck('categories.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $sharedTrackedItem->compatibleCategories()->sync(
            array_values(array_unique([...$existingCategoryIds, ...$mappedCategoryIds]))
        );

        return $sharedTrackedItem->fresh(['compatibleCategories']);
    }

    /**
     * @return Collection<int, TrackedItem>
     */
    public function sourceTrackedItemsForAccount(Account $account, int $userId): Collection
    {
        return TrackedItem::query()
            ->ownedBy($userId)
            ->withCount('children')
            ->with('compatibleCategories:id,uuid,parent_id,user_id,account_id,is_active')
            ->where('is_active', true)
            ->whereDoesntHave('children')
            ->orderBy('name')
            ->get()
            ->filter(function (TrackedItem $trackedItem) use ($account): bool {
                if ($this->findExistingTrackedItemForSourceTrackedItem($account, $trackedItem) instanceof TrackedItem) {
                    return false;
                }

                return $this->mappedAccountCategoryIds($account, $trackedItem, false) !== [];
            })
            ->values();
    }

    /**
     * @param  array<int, int>  $mappedCategoryIds
     * @return array<string, mixed>
     */
    protected function sharedTrackedItemSettings(Account $account, TrackedItem $sourceTrackedItem, array $mappedCategoryIds): array
    {
        $settings = is_array($sourceTrackedItem->settings) ? $sourceTrackedItem->settings : [];

        $mappedCategoryUuids = Category::query()
            ->whereIn('id', $mappedCategoryIds)
            ->pluck('uuid')
            ->filter(fn ($uuid): bool => is_string($uuid) && $uuid !== '')
            ->values()
            ->all();

        return [
            ...$settings,
            'transaction_category_uuids' => $mappedCategoryUuids,
            'source_personal_tracked_item_uuid' => $sourceTrackedItem->uuid,
            'shared_account_uuid' => $account->uuid,
        ];
    }

    /**
     * @return array<int, int>
     */
    protected function mappedAccountCategoryIds(
        Account $account,
        TrackedItem $sourceTrackedItem,
        bool $allowMaterialization,
    ): array {
        $compatibleCategories = $sourceTrackedItem->relationLoaded('compatibleCategories')
            ? $sourceTrackedItem->compatibleCategories
            : $sourceTrackedItem->compatibleCategories()->get();

        return $compatibleCategories
            ->filter(fn (Category $category): bool => $category->is_active)
            ->map(fn (Category $category): ?Category => $allowMaterialization
                ? $this->sharedAccountCategoryTaxonomyService->findCategoryForAccount($account, $category->id)
                : $this->sharedAccountCategoryTaxonomyService->findExistingCategoryForSourceCategory($account, $category))
            ->filter(fn (?Category $category): bool => $category instanceof Category)
            ->map(fn (Category $category): int => (int) $category->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     */
    protected function mappedFallbackCategoryIds(Account $account, array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $categoryIds)
            ->get(['id', 'account_id'])
            ->map(function (Category $category) use ($account): ?int {
                if ((int) ($category->account_id ?? 0) === (int) $account->id) {
                    return (int) $category->id;
                }

                return $this->sharedAccountCategoryTaxonomyService
                    ->findCategoryForAccount($account, $category->id)?->id;
            })
            ->filter(fn (?int $categoryId): bool => $categoryId !== null)
            ->values()
            ->all();
    }
}
