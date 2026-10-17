<?php

namespace App\Services\TrackedItems;

use App\Models\Account;
use App\Models\TrackedItem;
use App\Models\TrackedItemAlias;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackedItemIdentityService
{
    public function __construct(
        private readonly TrackedItemResolver $catalogs,
        private readonly AccessibleAccountsQuery $accounts,
    ) {}

    public function canonicalFor(TrackedItem $reference): TrackedItem
    {
        return $this->resolveIdentity($reference->id);
    }

    public function forNewOperation(Builder $catalog, ?string $uuid = null, ?int $id = null, string $field = 'tracked_item_uuid'): ?TrackedItem
    {
        if ($uuid === null && $id === null) {
            return null;
        }
        $reference = $uuid !== null ? $catalog->where('uuid', $uuid)->first() : $catalog->find($id);
        if ($reference === null || ($uuid !== null && $id !== null && $reference->id !== $id)) {
            throw ValidationException::withMessages([$field => 'Il riferimento non è disponibile nel catalogo selezionato.']);
        }
        $canonical = $this->canonicalFor($reference);
        if (! $canonical->is_active) {
            throw ValidationException::withMessages([$field => 'Il riferimento canonico è archiviato e non può essere usato per nuove operazioni.']);
        }

        return $canonical;
    }

    /**
     * @template T
     *
     * @param  array<string, int|null>  $referenceIds  Already validated reference IDs.
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withNewReferences(array $referenceIds, Closure $callback): mixed
    {
        $references = TrackedItem::query()->whereIn('id', array_filter($referenceIds))->get();
        $catalogs = $references->map(fn (TrackedItem $item): array => [
            'account_id' => $item->account_id,
            'owner_id' => $item->account_id === null ? $item->user_id : Account::query()->findOrFail($item->account_id)->user_id,
        ])->unique(fn (array $catalog): string => $catalog['account_id'] === null ? 'user:'.$catalog['owner_id'] : 'account:'.$catalog['account_id'])
            ->sortBy(fn (array $catalog): string => sprintf('%s:%020d', $catalog['account_id'] === null ? 'user' : 'account', $catalog['account_id'] ?? $catalog['owner_id']))->values();
        $insert = function () use ($referenceIds, $references, $callback): mixed {
            foreach ($referenceIds as $field => $id) {
                if ($id === null) {
                    continue;
                }
                $original = $references->find($id);
                $canonical = $this->resolveIdentity($id, true);
                if ($original === null || $canonical->id !== $id || ! $canonical->is_active || $canonical->account_id !== $original->account_id || $canonical->user_id !== $original->user_id) {
                    throw ValidationException::withMessages([$field => 'L’identità del riferimento è cambiata. Riprova il salvataggio.']);
                }
            }

            return $callback();
        };
        foreach ($catalogs->reverse() as $catalog) {
            $next = $insert;
            $insert = fn (): mixed => $this->catalogs->inCatalog($catalog['owner_id'], $catalog['account_id'], $next);
        }

        return DB::transaction($insert, 5);
    }

    /**
     * @param  Collection<int, TrackedItem>  $references
     * @return array<int, array<int, string>>
     */
    public function searchTermsFor(Collection $references): array
    {
        if ($references->isEmpty()) {
            return [];
        }
        $aliases = $this->catalogAliases($references);
        $targets = $aliases->pluck('canonical_tracked_item_id', 'source_tracked_item_id');
        $available = $references->keyBy('id');
        $terms = [];
        foreach ($aliases as $alias) {
            $id = $alias->canonical_tracked_item_id;
            $visited = [];
            while ($targets->has($id) && ! isset($visited[$id])) {
                $visited[$id] = true;
                $id = $targets[$id];
            }
            if ($available->has($id) && $alias->source !== null) {
                $terms[$id] = array_values(array_unique([...($terms[$id] ?? []), $alias->source->name, $alias->source->slug, $alias->source->uuid]));
            }
        }

        return $terms;
    }

    /** @return Collection<int, TrackedItem> */
    public function familyMembers(TrackedItem $reference): Collection
    {
        $canonical = $this->canonicalFor($reference);
        $ids = [$canonical->id];
        do {
            $before = count($ids);
            $sources = TrackedItemAlias::query()->whereIn('canonical_tracked_item_id', $ids)
                ->whereHas('source', fn (Builder $query) => $query->inCatalog($canonical->user_id, $canonical->account_id))
                ->pluck('source_tracked_item_id')->all();
            $ids = array_values(array_unique([...$ids, ...$sources]));
        } while (count($ids) !== $before);

        return TrackedItem::query()->inCatalog($canonical->user_id, $canonical->account_id)->whereIn('id', $ids)->orderBy('id')->get();
    }

    /** @return array{uuid: string, name: string, original_uuid: string, original_name: string, label: string} */
    public function presentation(TrackedItem $reference): array
    {
        if ($reference->user_id === null) {
            $key = self::class.':reference:'.$reference->id;
            $request = request();
            $reference = $request->isMethod('GET') && $request->attributes->has($key)
                ? $request->attributes->get($key) : TrackedItem::query()->findOrFail($reference->id);
            if ($request->isMethod('GET')) {
                $request->attributes->set($key, $reference);
            }
        }
        $key = self::class.':presentation:'.($reference->account_id === null ? 'user:'.$reference->user_id : 'account:'.$reference->account_id);
        $request = request();
        if (! $request->attributes->has($key)) {
            $edges = $this->catalogAliases(collect([$reference]))->pluck('canonical_tracked_item_id', 'source_tracked_item_id');
            $targets = $edges->isEmpty() ? collect() : TrackedItem::query()->inCatalog($reference->user_id, $reference->account_id)->whereIn('id', $edges->values())->get()->keyBy('id');
            $request->attributes->set($key, [$edges, $targets]);
        }
        [$edges, $targets] = $request->attributes->get($key);
        $id = $reference->id;
        $visited = [];
        while ($edges->has($id)) {
            if (isset($visited[$id])) {
                throw ValidationException::withMessages(['identity' => 'Ciclo nelle identità dei riferimenti.']);
            }
            $visited[$id] = true;
            $id = $edges[$id];
        }
        $canonical = $id === $reference->id ? $reference : $targets->get($id);
        if ($canonical === null) {
            throw ValidationException::withMessages(['identity' => 'Identità canonica non disponibile nel catalogo.']);
        }

        return ['uuid' => $canonical->uuid, 'name' => $canonical->name, 'original_uuid' => $reference->uuid, 'original_name' => $reference->name,
            'label' => $canonical->id === $reference->id ? $reference->name : $canonical->name.' (origine: '.$reference->name.($canonical->name === $reference->name ? ' · '.substr($reference->uuid, 0, 8) : '').')'];
    }

    /**
     * @param  Collection<int, TrackedItem>  $references
     * @return Collection<int, TrackedItemAlias>
     */
    private function catalogAliases(Collection $references): Collection
    {
        $catalogKey = fn (TrackedItem $item): string => $item->account_id === null ? 'user:'.$item->user_id : 'account:'.$item->account_id;
        $key = self::class.':catalog-aliases';
        $request = request();
        $cache = $request->isMethod('GET') ? $request->attributes->get($key, []) : [];
        $catalogs = $references->unique($catalogKey);
        $missing = $catalogs->reject(fn (TrackedItem $item): bool => array_key_exists($catalogKey($item), $cache));
        if ($missing->isNotEmpty()) {
            $aliases = TrackedItemAlias::query()->whereHas('source', function (Builder $query) use ($missing): void {
                $query->where(function (Builder $query) use ($missing): void {
                    foreach ($missing as $reference) {
                        $query->orWhere(fn (Builder $scope) => $scope->inCatalog($reference->user_id, $reference->account_id));
                    }
                });
            })->with('source:id,user_id,account_id,uuid,name,slug')->get();
            foreach ($missing as $reference) {
                $cache[$catalogKey($reference)] = $aliases->filter(fn (TrackedItemAlias $alias): bool => $alias->source !== null && $catalogKey($alias->source) === $catalogKey($reference));
            }
            if ($request->isMethod('GET')) {
                $request->attributes->set($key, $cache);
            }
        }

        return $catalogs->flatMap(fn (TrackedItem $item): Collection => $cache[$catalogKey($item)])->values();
    }

    public function register(User $actor, TrackedItem $source, TrackedItem $destination): TrackedItemAlias
    {
        $source = TrackedItem::query()->findOrFail($source->id);
        $destination = TrackedItem::query()->findOrFail($destination->id);
        $this->authorize($actor, $source);
        $this->authorize($actor, $destination);
        $this->ensureSameCatalog($source, $destination);
        $ownerId = $source->account_id === null ? $source->user_id : Account::query()->findOrFail($source->account_id)->user_id;

        $catalogAccountId = $source->account_id;

        return $this->catalogs->inCatalog($ownerId, $catalogAccountId, function () use ($actor, $source, $destination, $catalogAccountId, $ownerId): TrackedItemAlias {
            $references = TrackedItem::query()->whereIn('id', [$source->id, $destination->id])->orderBy('id')->lockForUpdate()->get();
            $source = $references->findOrFail($source->id);
            $destination = $references->findOrFail($destination->id);
            if ($source->account_id !== $catalogAccountId || ($catalogAccountId === null && $source->user_id !== $ownerId)) {
                throw ValidationException::withMessages(['source_uuid' => 'Il catalogo del riferimento è cambiato. Riprova l’operazione.']);
            }
            $this->authorize($actor, $source);
            $this->authorize($actor, $destination);
            $this->ensureSameCatalog($source, $destination);
            if ($source->id === $destination->id) {
                throw ValidationException::withMessages(['destination_uuid' => 'Origine e destinazione devono essere riferimenti diversi.']);
            }

            $canonical = $this->resolveIdentity($destination->id, true);
            $this->ensureSameCatalog($source, $canonical);
            if ($source->id === $canonical->id) {
                throw ValidationException::withMessages(['destination_uuid' => 'Il collegamento creerebbe un ciclo tra riferimenti.']);
            }
            $existing = $source->identityAlias()->lockForUpdate()->first();
            if ($existing !== null) {
                if ($this->resolveIdentity($existing->canonical_tracked_item_id, true)->id !== $canonical->id) {
                    throw ValidationException::withMessages(['source_uuid' => 'Il riferimento origine ha già un’identità canonica diversa.']);
                }

                return $existing;
            }
            if (! $canonical->is_active) {
                throw ValidationException::withMessages(['destination_uuid' => 'La destinazione canonica deve essere attiva.']);
            }
            if ($source->children()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['source_uuid' => 'Il riferimento origine ha figli attivi. La gerarchia deve essere risolta prima di registrare un alias.']);
            }

            $source->update(['is_active' => false]);

            $alias = TrackedItemAlias::query()->create([
                'source_tracked_item_id' => $source->id,
                'canonical_tracked_item_id' => $canonical->id,
                'created_by_user_id' => $actor->id,
            ]);
            request()->attributes->remove(self::class.':presentation:'.($source->account_id === null ? 'user:'.$source->user_id : 'account:'.$source->account_id));

            return $alias;
        });
    }

    private function resolveIdentity(int $referenceId, bool $lock = false): TrackedItem
    {
        $visited = [];
        while (true) {
            if (isset($visited[$referenceId])) {
                throw ValidationException::withMessages(['identity' => 'È stato rilevato un ciclo nelle identità dei riferimenti.']);
            }
            $visited[$referenceId] = true;
            $reference = TrackedItem::query()->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($referenceId);
            $alias = TrackedItemAlias::query()->where('source_tracked_item_id', $referenceId)->when($lock, fn ($query) => $query->lockForUpdate())->first();
            if ($alias === null) {
                return $reference;
            }
            $destination = TrackedItem::query()->findOrFail($alias->canonical_tracked_item_id);
            $this->ensureSameCatalog($reference, $destination);
            $referenceId = $destination->id;
        }
    }

    private function ensureSameCatalog(TrackedItem $source, TrackedItem $destination): void
    {
        if ($source->account_id !== $destination->account_id || ($source->account_id === null && $source->user_id !== $destination->user_id)) {
            throw ValidationException::withMessages(['destination_uuid' => 'Origine e destinazione devono appartenere allo stesso catalogo.']);
        }
    }

    private function authorize(User $actor, TrackedItem $reference): void
    {
        $authorized = $reference->account_id === null
            ? $reference->user_id === $actor->id
            : $this->accounts->canEditAccountId($actor, $reference->account_id);
        if (! $authorized) {
            throw new AuthorizationException('Non puoi modificare l’identità di questo riferimento.');
        }
    }
}
