<?php

namespace App\Services\Categories;

use App\Enums\AccountMembershipStatusEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SharedAccountCategoryTaxonomyService
{
    public function isSharedAccount(Account $account): bool
    {
        return AccountMembership::query()
            ->where('account_id', $account->id)
            ->where('status', AccountMembershipStatusEnum::ACTIVE->value)
            ->where('user_id', '!=', $account->user_id)
            ->exists();
    }

    public function hasAccountScopedCatalog(Account $account): bool
    {
        return Category::query()
            ->sharedForAccount($account->id)
            ->exists();
    }

    public function usesAccountScopedCatalog(Account $account): bool
    {
        return $this->isSharedAccount($account) || $this->hasAccountScopedCatalog($account);
    }

    public function ensureForAccount(Account $account): void
    {
        if (! $this->usesAccountScopedCatalog($account)) {
            return;
        }

        DB::transaction(function () use ($account): void {
            $this->ensureFoundationRoots($account);
            $this->syncTransactionsToSharedTaxonomy($account);
            $this->pruneUnusedSharedCategories($account);
        });
    }

    /**
     * @param  array<int, int>  $usedCategoryIds
     * @return Collection<int, Category>
     */
    public function categoriesForAccount(Account $account, array $usedCategoryIds = []): Collection
    {
        $this->ensureForAccount($account);

        return Category::query()
            ->sharedForAccount($account->id)
            ->withCount('children')
            ->where(function ($query) use ($usedCategoryIds): void {
                $query->where('is_active', true);

                if ($usedCategoryIds !== []) {
                    $query->orWhereIn('id', $usedCategoryIds);
                }
            })
            ->where(function ($query): void {
                $query->whereNull('group_type')
                    ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'user_id',
                'account_id',
                'uuid',
                'parent_id',
                'name',
                'slug',
                'foundation_key',
                'icon',
                'color',
                'direction_type',
                'group_type',
                'sort_order',
                'is_active',
                'is_selectable',
                'is_system',
            ]);
    }

    public function findCategoryForAccount(Account $account, int $categoryId): ?Category
    {
        $this->ensureForAccount($account);

        $sharedCategory = Category::query()
            ->sharedForAccount($account->id)
            ->find($categoryId);

        if ($sharedCategory instanceof Category) {
            return $sharedCategory;
        }

        $sourceCategory = Category::query()
            ->whereKey($categoryId)
            ->whereNull('account_id')
            ->first();

        if (! $sourceCategory instanceof Category) {
            return null;
        }

        return $this->ensurePathFromSourceCategory($account, $sourceCategory);
    }

    public function findExistingCategoryForSourceCategory(Account $account, Category $sourceCategory): ?Category
    {
        $this->ensureForAccount($account);

        return $this->resolvePathFromSourceCategory($account, $sourceCategory, false);
    }

    public function findSourceCategoryForSharedCategory(Account $account, Category $sharedCategory): ?Category
    {
        if ((int) ($sharedCategory->account_id ?? 0) !== (int) $account->id) {
            return null;
        }

        $categoriesById = Category::query()
            ->sharedForAccount($account->id)
            ->get(['id', 'parent_id', 'name', 'group_type'])
            ->keyBy('id');

        if (! $categoriesById->has($sharedCategory->id)) {
            return null;
        }

        $lineage = [];
        $currentCategory = $categoriesById->get($sharedCategory->id);
        $visited = [];

        while ($currentCategory instanceof Category) {
            if (in_array($currentCategory->id, $visited, true)) {
                break;
            }

            $visited[] = $currentCategory->id;
            $lineage[] = $currentCategory;
            $currentCategory = $currentCategory->parent_id !== null
                ? $categoriesById->get((int) $currentCategory->parent_id)
                : null;
        }

        $lineage = array_reverse($lineage);
        $sharedRoot = $lineage[0] ?? null;

        if (! $sharedRoot instanceof Category || $sharedRoot->group_type === null) {
            return null;
        }

        $currentSource = Category::query()
            ->ownedBy($account->user_id)
            ->whereNull('parent_id')
            ->where('group_type', $sharedRoot->group_type->value)
            ->first();

        if (! $currentSource instanceof Category) {
            return null;
        }

        foreach (array_slice($lineage, 1) as $node) {
            $sourceSlug = $this->sourceSlugFromSharedNode($account, $node);

            $currentSource = Category::query()
                ->ownedBy($account->user_id)
                ->where('parent_id', $currentSource->id)
                ->where(function ($query) use ($node, $sourceSlug): void {
                    if ($sourceSlug !== null) {
                        $query->where('slug', $sourceSlug)
                            ->orWhere(DB::raw('LOWER(name)'), '=', mb_strtolower($node->name));

                        return;
                    }

                    $query->where(DB::raw('LOWER(name)'), '=', mb_strtolower($node->name));
                })
                ->first();

            if (! $currentSource instanceof Category) {
                return null;
            }
        }

        return $currentSource;
    }

    public function materializeSourceCategoryForAccount(
        Account $account,
        Category $sourceCategory,
        bool $preserveSourceSlug = false,
    ): ?Category {
        $this->ensureForAccount($account);

        if ($sourceCategory->account_id !== null) {
            return (int) $sourceCategory->account_id === (int) $account->id
                ? $sourceCategory
                : null;
        }

        return $this->ensurePathFromSourceCategory($account, $sourceCategory, $preserveSourceSlug);
    }

    protected function ensureFoundationRoots(Account $account): void
    {
        foreach (CategoryFoundationService::definitions() as $definition) {
            $existing = Category::query()
                ->sharedForAccount($account->id)
                ->whereNull('parent_id')
                ->where('group_type', $definition['group_type']->value)
                ->first();

            if ($existing instanceof Category) {
                $existing->forceFill([
                    'name' => $definition['name'],
                    'direction_type' => $definition['direction_type'],
                    'group_type' => $definition['group_type'],
                    'icon' => $existing->icon ?: $definition['icon'],
                    'color' => $existing->color ?: $definition['color'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                    'is_selectable' => true,
                    'is_system' => true,
                ])->save();

                continue;
            }

            Category::query()->create([
                'user_id' => $account->user_id,
                'account_id' => $account->id,
                'parent_id' => null,
                'name' => $definition['name'],
                'slug' => sprintf('shared-%d-root-%s', $account->id, $definition['foundation_key']),
                'foundation_key' => null,
                'direction_type' => $definition['direction_type'],
                'group_type' => $definition['group_type'],
                'icon' => $definition['icon'],
                'color' => $definition['color'],
                'sort_order' => $definition['sort_order'],
                'is_active' => true,
                'is_selectable' => true,
                'is_system' => true,
            ]);
        }
    }

    protected function syncTransactionsToSharedTaxonomy(Account $account): void
    {
        Transaction::query()
            ->where('account_id', $account->id)
            ->whereNotNull('category_id')
            ->with('category.parent')
            ->get()
            ->each(function (Transaction $transaction) use ($account): void {
                $category = $transaction->category;

                if (! $category instanceof Category) {
                    return;
                }

                if ((int) ($category->account_id ?? 0) === $account->id) {
                    return;
                }

                $sharedCategory = $this->ensurePathFromSourceCategory($account, $category);

                if ($sharedCategory->id === $transaction->category_id) {
                    return;
                }

                $transaction->forceFill([
                    'category_id' => $sharedCategory->id,
                ])->save();
            });
    }

    protected function ensurePathFromSourceCategory(
        Account $account,
        Category $sourceCategory,
        bool $preserveSourceSlug = false,
    ): Category {
        return $this->resolvePathFromSourceCategory($account, $sourceCategory, true, $preserveSourceSlug)
            ?? throw new \RuntimeException('Unable to materialize shared category path.');
    }

    protected function resolvePathFromSourceCategory(
        Account $account,
        Category $sourceCategory,
        bool $createMissing,
        bool $preserveSourceSlug = false,
    ): ?Category {
        $lineage = $this->sourceLineage($sourceCategory);
        $root = $this->resolveFoundationRoot($account, $lineage);
        $currentParent = $root;

        foreach ($lineage as $node) {
            if (
                $node->parent_id === null
                && ($node->foundation_key !== null || $node->is_system || ! $node->is_selectable)
            ) {
                continue;
            }

            $currentParent = $createMissing
                ? $this->firstOrCreateSharedChild($account, $currentParent, $node, $preserveSourceSlug)
                : $this->findSharedChild($account, $currentParent, $node);

            if (! $currentParent instanceof Category) {
                return null;
            }
        }

        return $currentParent;
    }

    /**
     * @return Collection<int, Category>
     */
    protected function sourceLineage(Category $category): Collection
    {
        $lineage = collect();
        $current = $category;
        $visited = [];

        while ($current instanceof Category && ! in_array($current->id, $visited, true)) {
            $visited[] = $current->id;
            $lineage->prepend($current);
            $current = $current->parent()->first();
        }

        return $lineage->values();
    }

    protected function resolveFoundationRoot(Account $account, Collection $lineage): Category
    {
        /** @var Category|null $rootNode */
        $rootNode = $lineage->first();

        $foundationGroup = $rootNode?->group_type?->value
            ?? ($rootNode?->direction_type === CategoryDirectionTypeEnum::INCOME
                ? CategoryGroupTypeEnum::INCOME->value
                : CategoryGroupTypeEnum::EXPENSE->value);

        return Category::query()
            ->sharedForAccount($account->id)
            ->whereNull('parent_id')
            ->where('group_type', $foundationGroup)
            ->firstOrFail();
    }

    protected function firstOrCreateSharedChild(
        Account $account,
        Category $parent,
        Category $source,
        bool $preserveSourceSlug = false,
    ): Category {
        $existing = $this->findSharedChild($account, $parent, $source);

        if ($existing instanceof Category) {
            return $existing;
        }

        return Category::query()->create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'parent_id' => $parent->id,
            'name' => $source->name,
            'slug' => $preserveSourceSlug
                ? $this->uniqueAccountScopedSlug($account, $source->slug ?: Str::slug($source->name))
                : $this->uniqueSharedSlug($account, $parent, $source->slug ?: Str::slug($source->name)),
            'foundation_key' => null,
            'direction_type' => $parent->direction_type,
            'group_type' => $parent->group_type,
            'icon' => $source->icon,
            'color' => $source->color,
            'sort_order' => (int) $source->sort_order,
            'is_active' => true,
            'is_selectable' => (bool) $source->is_selectable,
            'is_system' => false,
        ]);
    }

    protected function findSharedChild(Account $account, Category $parent, Category $source): ?Category
    {
        $expectedSharedSlug = $this->expectedSharedSlug($account, $parent, $source);

        return Category::query()
            ->sharedForAccount($account->id)
            ->where('parent_id', $parent->id)
            ->where(function ($query) use ($expectedSharedSlug, $source): void {
                $query->where('slug', $expectedSharedSlug)
                    ->orWhere('slug', $source->slug)
                    ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($source->name)]);
            })
            ->first();
    }

    protected function expectedSharedSlug(Account $account, Category $parent, Category $source): string
    {
        $normalizedBaseSlug = Str::slug($source->slug ?: $source->name) ?: 'categoria';

        return sprintf('shared-%d-%d-%s', $account->id, $parent->id, $normalizedBaseSlug);
    }

    /**
     * @param  array<int, int>  $keepCategoryIds
     */
    protected function pruneUnusedSharedCategories(Account $account, array $keepCategoryIds = []): void
    {
        $categories = Category::query()
            ->sharedForAccount($account->id)
            ->get(['id', 'parent_id']);

        if ($categories->isEmpty()) {
            return;
        }

        $usedCategoryIds = Transaction::query()
            ->where('account_id', $account->id)
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $keepIds = [];
        $categoriesById = $categories->keyBy('id');

        foreach ($usedCategoryIds as $categoryId) {
            $currentCategoryId = $categoryId;
            $visited = [];

            while ($currentCategoryId > 0 && $categoriesById->has($currentCategoryId)) {
                if (in_array($currentCategoryId, $visited, true)) {
                    break;
                }

                $visited[] = $currentCategoryId;
                $keepIds[] = $currentCategoryId;
                $currentCategoryId = (int) ($categoriesById[$currentCategoryId]->parent_id ?? 0);
            }
        }

        $rootIds = $categories
            ->whereNull('parent_id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($keepCategoryIds as $categoryId) {
            $currentCategoryId = $categoryId;
            $visited = [];

            while ($currentCategoryId > 0 && $categoriesById->has($currentCategoryId)) {
                if (in_array($currentCategoryId, $visited, true)) {
                    break;
                }

                $visited[] = $currentCategoryId;
                $keepIds[] = $currentCategoryId;
                $currentCategoryId = (int) ($categoriesById[$currentCategoryId]->parent_id ?? 0);
            }
        }

        $keepIds = array_values(array_unique([...$keepIds, ...$rootIds]));

        Category::query()
            ->sharedForAccount($account->id)
            ->where('slug', 'like', 'shared-%')
            ->when(
                $keepIds !== [],
                fn ($query) => $query->whereNotIn('id', $keepIds),
                fn ($query) => $query->whereNotNull('parent_id')
            )
            ->delete();
    }

    protected function uniqueSharedSlug(Account $account, Category $parent, string $baseSlug): string
    {
        $normalizedBaseSlug = Str::slug($baseSlug) ?: 'categoria';
        $slug = sprintf('shared-%d-%d-%s', $account->id, $parent->id, $normalizedBaseSlug);

        $suffix = 1;

        while (Category::query()->where('user_id', $account->user_id)->where('slug', $slug)->exists()) {
            $slug = sprintf(
                'shared-%d-%d-%s-%d',
                $account->id,
                $parent->id,
                $normalizedBaseSlug,
                $suffix++,
            );
        }

        return $slug;
    }

    protected function uniqueAccountScopedSlug(Account $account, string $baseSlug): string
    {
        $normalizedBaseSlug = Str::slug($baseSlug) ?: 'categoria';
        $slug = $normalizedBaseSlug;
        $suffix = 1;

        while (
            Category::query()
                ->sharedForAccount($account->id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = sprintf('%s-%d', $normalizedBaseSlug, $suffix++);
        }

        return $slug;
    }

    protected function sourceSlugFromSharedNode(Account $account, Category $sharedNode): ?string
    {
        $parentId = (int) ($sharedNode->parent_id ?? 0);

        if ($parentId > 0) {
            $prefix = sprintf('shared-%d-%d-', $account->id, $parentId);

            if (str_starts_with($sharedNode->slug, $prefix)) {
                $sourceSlug = substr($sharedNode->slug, strlen($prefix));

                return $sourceSlug !== false && $sourceSlug !== '' ? $sourceSlug : null;
            }
        }

        return filled($sharedNode->slug) ? $sharedNode->slug : null;
    }
}
