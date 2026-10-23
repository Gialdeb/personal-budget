<?php

namespace App\Services\TrackedItems;

use App\Models\Account;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackedItemResolver
{
    public static function normalizeName(string $name): string
    {
        return mb_strtolower(Str::ascii(Str::squish($name)));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function inCatalog(int $userId, ?int $accountId, Closure $callback): mixed
    {
        return DB::transaction(function () use ($userId, $accountId, $callback): mixed {
            $catalog = $accountId === null
                ? User::query()->whereKey($userId)
                : Account::query()->whereKey($accountId)->where('user_id', $userId);

            if (DB::getDriverName() === 'sqlite') {
                $catalog->toBase()->update(['id' => DB::raw('id')]);
            }

            $catalog->lockForUpdate()->firstOrFail();

            return $callback();
        }, 5);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function forAccount(User $actor, Account $account, ?string $name, ?string $uuid, Category $category, string $groupKey, array $attributes = [], bool $personalCatalog = false): TrackedItem
    {
        $accountId = ! $personalCatalog && app(SharedAccountTrackedItemCatalogService::class)->usesAccountScopedCatalog($account)
            ? $account->id : null;

        return $this->resolve(
            userId: $accountId === null ? $actor->id : $account->user_id,
            accountId: $accountId,
            name: $name,
            uuid: $uuid,
            attributes: $attributes,
            category: $category,
            groupKey: $groupKey,
        );
    }

    /**
     * @return array<int, array{value: string, label: string, reference_name: string, logo_url: ?string, search_terms?: list<string>}>
     */
    public function counterpartyOptions(int $userId): array
    {
        $items = TrackedItem::query()->ownedBy($userId)->where('is_active', true)->with('websiteIdentity')->orderBy('name')->get(['id', 'user_id', 'account_id', 'uuid', 'name', 'slug', 'settings', 'website_identity_id']);
        $searchTerms = app(TrackedItemIdentityService::class)->searchTermsFor($items);
        $counts = $items->countBy(fn (TrackedItem $item): string => self::normalizeName($item->name));

        return $items->map(fn (TrackedItem $item): array => [
            'value' => $item->uuid,
            'reference_name' => $item->name,
            'logo_url' => $item->logoUrl(),
            'label' => $item->name.($counts[self::normalizeName($item->name)] > 1 ? ' · '.$item->slug : ''),
            ...(isset($searchTerms[$item->id]) ? ['search_terms' => $searchTerms[$item->id]] : []),
        ])->all();
    }

    public function matchingName(int $userId, ?int $accountId, string $name, ?int $ignoreId = null): Builder
    {
        $normalized = self::normalizeName($name);
        $ids = TrackedItem::query()->inCatalog($userId, $accountId)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->when(DB::transactionLevel() > 0, fn (Builder $query) => $query->lockForUpdate())
            ->get(['id', 'name'])
            ->filter(fn (TrackedItem $item): bool => self::normalizeName($item->name) === $normalized)
            ->modelKeys();

        return TrackedItem::query()->whereIn('id', $ids);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resolve(
        int $userId,
        ?int $accountId,
        ?string $name,
        ?string $uuid = null,
        array $attributes = [],
        ?Category $category = null,
        ?string $groupKey = null,
        bool $counterparty = false,
    ): TrackedItem {
        return $this->inCatalog($userId, $accountId, function () use ($userId, $accountId, $name, $uuid, $attributes, $category, $groupKey, $counterparty): TrackedItem {
            $items = TrackedItem::query()->inCatalog($userId, $accountId)->lockForUpdate()->get();

            $resolvedFromAlias = false;
            if ($uuid !== null) {
                $item = $items->firstWhere('uuid', $uuid);
                if ($item instanceof TrackedItem) {
                    $item = app(TrackedItemIdentityService::class)->canonicalFor($item);
                }
                if (! $item instanceof TrackedItem || ! $item->is_active) {
                    throw ValidationException::withMessages(['tracked_item_uuid' => __('form_errors.the_selected_reference_is_unavailable_in_this_catalog')]);
                }
            } else {
                $normalized = self::normalizeName((string) $name);
                if ($normalized === '') {
                    throw ValidationException::withMessages(['name' => __('form_errors.enter_the_reference_name')]);
                }
                $sourceUuid = $attributes['settings']['source_personal_tracked_item_uuid'] ?? null;
                $matches = $sourceUuid === null ? collect() : $items->filter(
                    fn (TrackedItem $candidate): bool => ($candidate->settings['source_personal_tracked_item_uuid'] ?? null) === $sourceUuid,
                );
                if ($matches->isEmpty()) {
                    $matches = $items->filter(fn (TrackedItem $candidate): bool => self::normalizeName($candidate->name) === $normalized);
                }
                $resolvedFromAlias = $matches->contains(fn (TrackedItem $match): bool => $match->identityAlias()->exists());
                $matches = $matches->map(fn (TrackedItem $match): TrackedItem => app(TrackedItemIdentityService::class)->canonicalFor($match))->unique('id');
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages(['name' => __('form_errors.multiple_references_have_this_name_find_and_select_the_existing_reference_before')]);
                }
                $item = $matches->first();
                if (! $item instanceof TrackedItem) {
                    $baseSlug = $attributes['slug'] ?? (($counterparty ? 'shared-expense-' : '').(Str::slug((string) $name) ?: 'riferimento'));
                    $slug = $baseSlug;
                    for ($suffix = 2; $items->contains('slug', $slug); $suffix++) {
                        $slug = $baseSlug.'-'.$suffix;
                    }
                    $item = TrackedItem::query()->create([
                        'parent_id' => null,
                        'type' => null,
                        'is_active' => true,
                        'settings' => [],
                        ...$attributes,
                        'user_id' => $userId,
                        'account_id' => $accountId,
                        'name' => Str::squish((string) $name),
                        'slug' => $slug,
                    ]);
                }
            }

            if (! $item->is_active && $resolvedFromAlias) {
                throw ValidationException::withMessages(['name' => __('form_errors.the_canonical_reference_is_archived_reactivate_it_explicitly_in_settings')]);
            }

            if (! $item->is_active && $item->parent_id !== null && ! $items->firstWhere('id', $item->parent_id)?->is_active) {
                throw ValidationException::withMessages(['name' => __('form_errors.the_existing_reference_has_an_archived_parent_reactivate_the_parent_in_settings')]);
            }

            $settings = $item->settings ?? [];
            if ($groupKey !== null) {
                $settings['transaction_group_keys'] = array_values(array_unique([...($settings['transaction_group_keys'] ?? []), $groupKey]));
            }
            if ($category !== null) {
                $settings['transaction_category_uuids'] = array_values(array_unique([...($settings['transaction_category_uuids'] ?? []), $category->uuid]));
                $item->compatibleCategories()->syncWithoutDetaching([$category->id]);
            }
            if ($accountId !== null) {
                $settings['shared_account_uuid'] = Account::query()->whereKey($accountId)->value('uuid');
            }
            if (($attributes['settings']['economic_subject'] ?? false) === true) {
                $settings['economic_subject'] = true;
            }
            $item->fill(['is_active' => true, 'settings' => $settings]);
            foreach (['website_url', 'website_identity_id'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $item->{$field} = $attributes[$field];
                }
            }
            if ($counterparty) {
                $item->is_shared_expense_counterpart = true;
            }
            if ($item->isDirty()) {
                $item->save();
            }

            return $item->fresh(['compatibleCategories', 'websiteIdentity']);
        });
    }
}
