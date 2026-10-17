<?php

namespace App\Services\TrackedItems;

use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PendingTrackedItemService
{
    public const PREFIX = 'pending-reference:';

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function preview(User $actor, array $input, ?Account $account = null, ?Category $category = null, bool $counterparty = false): array
    {
        $personal = $counterparty || ($input['context'] ?? null) === 'credit_debt';
        $accountId = ! $personal && $account !== null && app(SharedAccountTrackedItemCatalogService::class)->usesAccountScopedCatalog($account) ? $account->id : null;
        $ownerId = $accountId === null ? $actor->id : $account->user_id;
        $resolver = app(TrackedItemResolver::class);
        $catalog = TrackedItem::query()->inCatalog($ownerId, $accountId);
        $uuid = $input['tracked_item_uuid'] ?? null;
        if ($uuid !== null) {
            $item = (clone $catalog)->where('uuid', $uuid)->first();
            if ($item === null) {
                $this->invalid();
            }
            $item = app(TrackedItemIdentityService::class)->canonicalFor($item);
        } else {
            $matches = $resolver->matchingName($ownerId, $accountId, (string) ($input['name'] ?? ''))->get()
                ->map(fn (TrackedItem $item): TrackedItem => app(TrackedItemIdentityService::class)->canonicalFor($item))->unique('id');
            if ($matches->count() > 1) {
                throw ValidationException::withMessages(['name' => 'Esistono più riferimenti con questo nome. Seleziona quello desiderato dall’elenco.']);
            }
            $item = $matches->first();
        }
        if ($item !== null && ! $item->is_active) {
            throw ValidationException::withMessages(['name' => 'Questo riferimento è archiviato. Gestiscilo nelle impostazioni prima di utilizzarlo.']);
        }
        $website = filled($input['website_url'] ?? null) ? app(WebsiteLogoService::class)->normalizeUrl($input['website_url']) : null;
        $token = self::PREFIX.Crypt::encryptString(json_encode([
            'actor_id' => $actor->id, 'owner_id' => $ownerId, 'catalog_account_id' => $accountId,
            'account_id' => $account?->id, 'category_id' => $category?->id, 'category_uuid' => $category?->uuid,
            'group_key' => $input['type_key'] ?? $input['direction'] ?? null,
            'name' => $item?->name ?? Str::squish($input['name']), 'uuid' => $item?->uuid,
            'website_url' => $website, 'counterparty' => $counterparty, 'personal' => $personal,
            'economic_subject' => ($input['context'] ?? null) === 'credit_debt',
            'expires_at' => now()->addDay()->timestamp,
        ], JSON_THROW_ON_ERROR));

        return ['value' => $token, 'uuid' => $token, 'source_uuid' => $item?->uuid, 'name' => $item?->name ?? Str::squish($input['name']),
            'label' => $item?->name ?? Str::squish($input['name']), 'owner_user_id' => $ownerId,
            'account_uuid' => $accountId === null ? null : $account?->uuid,
            'available_account_uuids' => $account === null ? [] : [$account->uuid],
            'group_keys' => array_values(array_unique([...($item?->settings['transaction_group_keys'] ?? []), ...array_filter([$input['type_key'] ?? $input['direction'] ?? null])])),
            'category_uuids' => array_values(array_unique([...($item?->compatibleCategories->pluck('uuid')->all() ?? []), ...($category === null ? [] : [$category->uuid])])),
            'is_selectable' => true, 'logo_url' => $item?->logoUrl()];
    }

    /** @param array<string, mixed> $input */
    public function materialize(User $actor, string $token, string $field, array $input, array $categoryMap = []): TrackedItem
    {
        try {
            $draft = json_decode(Crypt::decryptString(substr($token, strlen(self::PREFIX))), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $this->invalid($field);
        }
        if (($draft['actor_id'] ?? null) !== $actor->id || ($draft['expires_at'] ?? 0) <= now()->timestamp) {
            $this->invalid($field);
        }
        $partyField = in_array($field, ['counterparty_uuid', 'shared_expense_counterparty_uuid', 'shared_counterparty_uuid'], true);
        if ($partyField !== $draft['counterparty']) {
            $this->invalid($field);
        }
        $account = null;
        $category = null;
        if ($draft['category_id'] === null && isset($categoryMap[$draft['category_uuid'] ?? ''])) {
            $draft['category_id'] = Category::query()->where('uuid', $categoryMap[$draft['category_uuid']])->value('id');
        }
        if ($draft['account_id'] !== null) {
            Account::query()->whereKey($draft['account_id'])->lockForUpdate()->first();
            AccountMembership::query()->where('account_id', $draft['account_id'])->where('user_id', $actor->id)->lockForUpdate()->get();
            Category::query()->whereKey($draft['category_id'])->lockForUpdate()->first();
            $account = app(AccessibleAccountsQuery::class)->editable($actor)->where('accounts.id', $draft['account_id'])->first();
            $selectedAccount = filled($input['account_uuid'] ?? null) ? Account::query()->where('uuid', $input['account_uuid'])->value('id') : ($input['account_id'] ?? null);
            $selectedCategory = filled($input['category_uuid'] ?? null) ? Category::query()->where('uuid', $input['category_uuid'])->value('id') : ($input['category_id'] ?? null);
            if ($account === null || (int) $selectedAccount !== $account->id || (int) $selectedCategory !== $draft['category_id']) {
                $this->invalid($field);
            }
            $category = app(OperationalTransactionCategoryResolver::class)->findCategoryForAccount($account, $draft['category_id']);
            $catalogAccountId = ! $draft['personal'] && app(SharedAccountTrackedItemCatalogService::class)->usesAccountScopedCatalog($account) ? $account->id : null;
            if ($category === null || $catalogAccountId !== $draft['catalog_account_id'] || ($catalogAccountId !== null && $account->user_id !== $draft['owner_id'])) {
                $this->invalid($field);
            }
        }

        return app(TrackedItemResolver::class)->inCatalog($draft['owner_id'], $draft['catalog_account_id'], function () use ($draft, $category, $field): TrackedItem {
            $uuid = $draft['uuid'];
            if ($uuid === null) {
                $matches = app(TrackedItemResolver::class)->matchingName($draft['owner_id'], $draft['catalog_account_id'], $draft['name'])->get()
                    ->map(fn (TrackedItem $item): TrackedItem => app(TrackedItemIdentityService::class)->canonicalFor($item))->unique('id');
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages([$field => 'Esistono più riferimenti con questo nome. Seleziona quello desiderato dall’elenco.']);
                }
                $match = $matches->first();
                if ($match !== null && ! $match->is_active) {
                    throw ValidationException::withMessages([$field => 'Questo riferimento è stato archiviato. Gestiscilo nelle impostazioni prima di utilizzarlo.']);
                }
                $uuid = $match?->uuid;
            }
            $attributes = ['settings' => ['economic_subject' => $draft['economic_subject']]];
            if ($draft['website_url'] !== null) {
                $attributes += ['website_url' => $draft['website_url']];
            }

            $item = app(TrackedItemResolver::class)->resolve(
                userId: $draft['owner_id'], accountId: $draft['catalog_account_id'], name: $draft['name'], uuid: $uuid,
                attributes: $attributes, category: $category, groupKey: $draft['group_key'], counterparty: $draft['counterparty'],
            );
            app(WebsiteLogoService::class)->afterReferenceSaved($item);

            return $item;
        });
    }

    private function invalid(string $field = 'tracked_item_uuid'): never
    {
        throw ValidationException::withMessages([$field => 'La selezione del riferimento non è più valida. Cercalo e selezionalo nuovamente prima di salvare.']);
    }
}
