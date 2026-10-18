<?php

namespace App\Services\TrackedItems;

use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MoveTrackedItemMovementsService
{
    public function __construct(
        private readonly TrackedItemMovementGraph $graph,
        private readonly TrackedItemResolver $catalogs,
        private readonly AccessibleAccountsQuery $accounts,
        private readonly OperationalTransactionCategoryResolver $categories,
    ) {}

    public function accessible(User $actor, string $uuid, bool $lock = false): TrackedItem
    {
        return TrackedItem::query()->where('uuid', $uuid)->where(function (Builder $query) use ($actor): void {
            $query->ownedBy($actor->id)->orWhereIn('account_id', $this->accounts->editable($actor)->pluck('accounts.id'));
        })->when($lock, fn (Builder $query) => $query->lockForUpdate())->firstOrFail();
    }

    /** @return array<int, array<string, mixed>> */
    public function options(User $actor): array
    {
        $items = TrackedItem::query()->where(function (Builder $query) use ($actor): void {
            $query->ownedBy($actor->id)->orWhereIn('account_id', $this->accounts->editable($actor)->pluck('accounts.id'));
        })->with(['compatibleCategories', 'identityAlias'])->orderBy('name')->get();
        $byId = $items->keyBy('id');

        $categories = Category::query()->where(function (Builder $query) use ($actor): void {
            $query->ownedBy($actor->id)->orWhereIn('account_id', $this->accounts->editable($actor)->pluck('accounts.id'));
        })->get()->keyBy('id');

        $available = $items->filter(fn (TrackedItem $item): bool => $item->is_active && $item->identityAlias === null)->values();
        $names = $available->countBy(fn (TrackedItem $item): string => ($item->account_id ?? 'personal').':'.TrackedItemResolver::normalizeName($item->name));

        return $available->map(function (TrackedItem $item) use ($byId, $categories, $names): array {
            return [
                'value' => $item->uuid,
                'label' => $item->name,
                'full_path' => $this->hierarchyPath($item, $byId).' · '.($item->compatibleCategories->map(fn (Category $category): string => $this->hierarchyPath($category, $categories))->join(', ') ?: 'Senza categoria'),
                'catalog' => $item->account_id === null ? 'personal' : (string) $item->account_id,
                ...($names[($item->account_id ?? 'personal').':'.TrackedItemResolver::normalizeName($item->name)] > 1 ? ['disambiguation' => $item->slug] : []),
            ];
        })->all();
    }

    private function hierarchyPath(TrackedItem|Category $item, Collection $byId): string
    {
        $path = [$item->name];
        $seen = [$item->id];
        $parentId = $item->parent_id;
        while ($parentId !== null && ! in_array($parentId, $seen, true) && ($parent = $byId->get($parentId)) !== null) {
            if ($parent->account_id !== $item->account_id || ($item->account_id === null && $parent->user_id !== $item->user_id)) {
                break;
            }
            array_unshift($path, $parent->name);
            $seen[] = $parentId;
            $parentId = $parent->parent_id;
        }

        return implode(' › ', $path);
    }

    /** @return array<string, mixed> */
    public function preview(User $actor, string $sourceUuid, string $destinationUuid, bool $lock = false): array
    {
        [$source, $destination] = $this->references($actor, $sourceUuid, $destinationUuid, $lock);
        $sets = $this->graph->collect($source, $lock);
        $this->graph->authorize($actor, $sets, $lock);
        $reason = $this->graph->blockReason($source, $destination, $sets, $lock);
        if (! Schema::hasTable('tracked_item_move_records')) {
            $reason ??= 'È necessario aggiornare il registro degli spostamenti prima di procedere.';
        }
        $groups = $this->graph->groups($sets);

        return ['count' => array_sum(array_column($groups, 'count')), 'groups' => $groups, 'block_reason' => $reason,
            'preview_token' => $this->graphSignature($actor, $source, $destination, $sets)];
    }

    public function confirm(User $actor, string $sourceUuid, string $destinationUuid, string $token): void
    {
        $this->requireRegistry();
        if (! Schema::hasTable('tracked_item_move_records')) {
            $this->invalid('È necessario aggiornare il registro degli spostamenti prima di procedere.');
        }
        $this->inCatalog($actor, $sourceUuid, function () use ($actor, $sourceUuid, $destinationUuid, $token): void {
            [$source, $destination] = $this->references($actor, $sourceUuid, $destinationUuid, true);
            $sets = $this->graph->collect($source, true);
            $this->graph->authorize($actor, $sets, true);
            $reason = $this->graph->blockReason($source, $destination, $sets, true);
            if ($reason !== null) {
                $this->invalid($reason);
            }
            if (! hash_equals($this->graphSignature($actor, $source, $destination, $sets), $token)) {
                $this->invalid('I collegamenti sono cambiati. Seleziona nuovamente la destinazione e conferma la nuova anteprima.');
            }
            $groups = $this->graph->groups($sets);
            $count = array_sum(array_column($groups, 'count'));
            if ($count === 0) {
                $this->invalid('Non ci sono collegamenti da spostare.');
            }
            $date = CarbonImmutable::now();
            $moveId = DB::table('tracked_item_transaction_moves')->insertGetId([
                'uuid' => (string) Str::uuid(), 'catalog_user_id' => $source->user_id, 'catalog_account_id' => $source->account_id,
                'source_id' => $source->id, 'source_uuid' => $source->uuid, 'source_name' => $source->name,
                'destination_id' => $destination->id, 'destination_uuid' => $destination->uuid, 'destination_name' => $destination->name,
                'actor_id' => $actor->id, 'actor_name' => $actor->name, 'transaction_count' => $sets['transactions']->count(),
                'created_at' => $date, 'expires_at' => $date->addDays(30), 'scope_version' => 2, 'record_count' => $count,
                'movement_counts' => json_encode(array_map(fn (array $group): array => ['label' => $group['label'], 'count' => $group['count']], $groups), JSON_THROW_ON_ERROR),
            ]);
            foreach ($sets as $table => $rows) {
                foreach ($rows as $row) {
                    $changes = $this->graph->changes($table, $row, $source->id, $destination->id);
                    $before = array_intersect_key((array) $row, $changes);
                    $this->changeFields($table, $row, $changes);
                    $after = DB::table($table)->where('id', $row->id)->firstOrFail();
                    DB::table('tracked_item_move_records')->insert([
                        'uuid' => (string) Str::uuid(), 'move_id' => $moveId, 'entity_type' => $table, 'entity_id' => $row->id,
                        'entity_uuid' => $row->uuid ?? null, 'before_values' => json_encode($before, JSON_THROW_ON_ERROR),
                        'after_values' => json_encode($changes, JSON_THROW_ON_ERROR), 'after_fingerprint' => $this->fingerprint($after),
                        'context_fingerprint' => $this->graph->contextFingerprint($table, $row),
                    ]);
                    if ($table === 'transactions' && array_key_exists('tracked_item_id', $changes)) {
                        DB::table('tracked_item_transaction_move_entries')->insert([
                            'uuid' => (string) Str::uuid(), 'move_id' => $moveId, 'transaction_id' => $row->id, 'transaction_uuid' => $row->uuid,
                            'previous_tracked_item_id' => $row->tracked_item_id, 'applied_tracked_item_id' => $destination->id, 'after_fingerprint' => $this->fingerprint($after),
                        ]);
                    }
                }
            }
        });
    }

    public function restore(User $actor, string $uuid): void
    {
        $this->requireRegistry();
        $move = $this->visibleMoves($actor)->where('uuid', $uuid)->firstOrFail();
        $this->inCatalog($actor, $move->source_uuid, function () use ($actor, $uuid): void {
            $move = $this->visibleMoves($actor)->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            $reason = $this->restoreBlockReason($actor, $move, true);
            if ($reason !== null) {
                $this->invalid($reason);
            }
            if ((int) ($move->scope_version ?? 1) === 2) {
                foreach (DB::table('tracked_item_move_records')->where('move_id', $move->id)->orderBy('id')->get() as $record) {
                    $row = DB::table($record->entity_type)->where('id', $record->entity_id)->firstOrFail();
                    $this->changeFields($record->entity_type, $row, json_decode($record->before_values, true, flags: JSON_THROW_ON_ERROR));
                }
            } else {
                $entries = DB::table('tracked_item_transaction_move_entries')->where('move_id', $move->id)->orderBy('transaction_id')->get();
                foreach ($entries as $entry) {
                    $row = DB::table('transactions')->where('id', $entry->transaction_id)->firstOrFail();
                    $this->changeReference($row, $entry->previous_tracked_item_id);
                }
            }
            DB::table('tracked_item_transaction_moves')->where('id', $move->id)->update([
                'restored_at' => CarbonImmutable::now(), 'restored_by_id' => $actor->id, 'restored_by_name' => $actor->name,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function history(User $actor, int $page = 1, ?string $status = null): array
    {
        if (! Schema::hasTable('tracked_item_transaction_moves') || ! Schema::hasTable('tracked_item_transaction_move_entries')) {
            return ['data' => [], 'current_page' => 1, 'last_page' => 1, 'has_restorable' => false, 'unavailable_reason' => 'Lo storico sarà disponibile dopo l’aggiornamento del database.'];
        }
        $restorableIds = [];
        foreach ($this->visibleMoves($actor)->whereNull('restored_at')->where('expires_at', '>', CarbonImmutable::now())->orderByDesc('id')->lazy() as $candidate) {
            if ($this->restoreBlockReason($actor, $candidate) === null) {
                $restorableIds[] = $candidate->id;
            }
        }
        $hasRestorable = $restorableIds !== [];
        $moves = $this->visibleMoves($actor)
            ->when($status === 'pending', fn (QueryBuilder $query) => $query->whereNull('restored_at')->whereIn('id', $restorableIds))
            ->when($status === 'blocked', fn (QueryBuilder $query) => $query->whereNull('restored_at')->whereNotIn('id', $restorableIds))
            ->when($status === 'restored', fn (QueryBuilder $query) => $query->whereNotNull('restored_at'))
            ->orderByDesc('id')->paginate(10, ['*'], 'page', $page);

        return ['data' => $moves->getCollection()->map(function (object $move) use ($actor): array {
            $reason = $this->restoreBlockReason($actor, $move);

            return ['uuid' => $move->uuid, 'source_name' => $move->source_name, 'destination_name' => $move->destination_name,
                'actor_name' => $move->actor_name, 'created_at' => CarbonImmutable::parse($move->created_at)->toIso8601String(),
                'expires_at' => CarbonImmutable::parse($move->expires_at)->toIso8601String(), 'count' => $move->record_count ?? $move->transaction_count, 'groups' => isset($move->movement_counts) ? json_decode($move->movement_counts, true, flags: JSON_THROW_ON_ERROR) : [['label' => 'Transazioni', 'count' => $move->transaction_count]],
                'restored_at' => $move->restored_at === null ? null : CarbonImmutable::parse($move->restored_at)->toIso8601String(), 'restored_by_name' => $move->restored_by_name,
                'can_restore' => $reason === null, 'block_reason' => $reason];
        })->all(), 'current_page' => $moves->currentPage(), 'last_page' => $moves->lastPage(), 'has_restorable' => $hasRestorable];
    }

    private function visibleMoves(User $actor): QueryBuilder
    {
        return DB::table('tracked_item_transaction_moves')->where(function (QueryBuilder $query) use ($actor): void {
            $query->where(fn (QueryBuilder $query) => $query->whereNull('catalog_account_id')->where('catalog_user_id', $actor->id))
                ->orWhereIn('catalog_account_id', $this->accounts->ids($actor));
        });
    }

    private function restoreBlockReason(User $actor, object $move, bool $lock = false): ?string
    {
        if ($move->restored_at !== null) {
            return 'Spostamento già ripristinato.';
        }
        if (CarbonImmutable::now()->greaterThanOrEqualTo(CarbonImmutable::parse($move->expires_at))) {
            return 'Sono trascorsi i 30 giorni previsti per il ripristino. Lo spostamento resta nello storico, ma non può più essere annullato.';
        }
        foreach (['source' => 'origine', 'destination' => 'destinazione'] as $field => $label) {
            if (! TrackedItem::query()->where('uuid', $move->{$field.'_uuid'})->exists()) {
                return 'Il riferimento di '.$label.' «'.$move->{$field.'_name'}.'» è stato eliminato. Non è possibile ripristinare lo spostamento perché servono entrambi i riferimenti originali. I collegamenti attuali restano invariati.';
            }
        }
        try {
            [$source, $destination] = $this->references($actor, $move->source_uuid, $move->destination_uuid, $lock);
            if ($source->id !== $move->source_id || $destination->id !== $move->destination_id || $source->user_id !== $move->catalog_user_id || $source->account_id !== $move->catalog_account_id) {
                return 'Il catalogo o i riferimenti originali sono cambiati.';
            }
            if ((int) ($move->scope_version ?? 1) === 2) {
                return $this->graphRestoreReason($actor, $move, $source, $destination, $lock);
            }
            $entries = DB::table('tracked_item_transaction_move_entries')->where('move_id', $move->id)->orderBy('transaction_id')->get();
            $rows = DB::table('transactions')->whereIn('id', $entries->pluck('transaction_id'))->orderBy('id')
                ->when($lock, fn (QueryBuilder $query) => $query->lockForUpdate())->get();
            if ($rows->count() !== $move->transaction_count || $entries->count() !== $move->transaction_count) {
                return 'Una transazione registrata non è più disponibile.';
            }
            $byId = $rows->keyBy('id');
            foreach ($entries as $entry) {
                $row = $byId->get($entry->transaction_id);
                if ($row->uuid !== $entry->transaction_uuid || $row->tracked_item_id !== $entry->applied_tracked_item_id || ! hash_equals($entry->after_fingerprint, $this->fingerprint($row))) {
                    return 'Una transazione è stata modificata dopo lo spostamento.';
                }
            }
            if (DB::table('tracked_item_transaction_move_entries')->whereIn('transaction_id', $entries->pluck('transaction_id'))->where('move_id', '>', $move->id)->exists()) {
                return 'Una transazione è coinvolta in uno spostamento successivo.';
            }
            $this->validateTransactions($actor, $source, $rows, $lock);
        } catch (ModelNotFoundException) {
            return 'Riferimento non disponibile o autorizzazioni insufficienti.';
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->join(' ');
        }

        return null;
    }

    /** @return array{TrackedItem, TrackedItem} */
    private function references(User $actor, string $sourceUuid, string $destinationUuid, bool $lock): array
    {
        if ($lock) {
            $this->accessible($actor, $sourceUuid);
            $this->accessible($actor, $destinationUuid);
            TrackedItem::query()->whereIn('uuid', [$sourceUuid, $destinationUuid])->orderBy('id')->lockForUpdate()->get();
        }
        $source = $this->accessible($actor, $sourceUuid);
        $destination = $this->accessible($actor, $destinationUuid);
        if ($source->id === $destination->id || $source->account_id !== $destination->account_id || ($source->account_id === null && $source->user_id !== $destination->user_id)) {
            $this->invalid('Seleziona due riferimenti diversi dello stesso catalogo.');
        }
        if (! $source->is_active || ! $destination->is_active || $source->identityAlias()->exists() || $destination->identityAlias()->exists()) {
            $this->invalid('Un riferimento non è più disponibile per questa operazione.');
        }

        return [$source, $destination];
    }

    private function inCatalog(User $actor, string $sourceUuid, Closure $operation): void
    {
        $source = $this->accessible($actor, $sourceUuid);
        $ownerId = $source->account_id === null ? $source->user_id : Account::query()->findOrFail($source->account_id)->user_id;
        $this->catalogs->inCatalog($ownerId, $source->account_id, function () use ($actor, $source, $operation): void {
            if ($source->account_id !== null) {
                AccountMembership::query()->where('account_id', $source->account_id)->where('user_id', $actor->id)->lockForUpdate()->get();
            }
            $operation();
        });
    }

    private function validateTransactions(User $actor, TrackedItem $destination, Collection $rows, bool $lock): void
    {
        $accountIds = $rows->pluck('account_id')->unique()->sort()->values();
        $accounts = Account::query()->whereIn('id', $accountIds)->orderBy('id')->when($lock, fn (Builder $query) => $query->lockForUpdate())->get()->keyBy('id');
        if ($lock) {
            AccountMembership::query()->whereIn('account_id', $accountIds)->where('user_id', $actor->id)->orderBy('id')->lockForUpdate()->get();
        }
        $editable = $this->accounts->editableIds($actor);
        foreach ($rows as $row) {
            if ($row->deleted_at !== null || ! in_array($row->account_id, $editable, true) || ! $accounts->has($row->account_id)) {
                $this->invalid('Una transazione non è più disponibile o modificabile con le tue autorizzazioni.');
            }
            if ($this->categories->findTrackedItemForAccount($accounts->get($row->account_id), $destination->id) === null) {
                $this->invalid('Il riferimento di destinazione non è compatibile con uno dei conti interessati.');
            }
        }
    }

    /** @param array<string, Collection<int, object>> $sets */
    private function graphSignature(User $actor, TrackedItem $source, TrackedItem $destination, array $sets): string
    {
        $contexts = [];
        foreach ($sets['recurring_entries'] as $row) {
            $contexts[$row->id] = $this->graph->contextFingerprint('recurring_entries', $row);
        }
        $legacy = Schema::hasTable('tracked_item_transaction_move_entries')
            ? DB::table('tracked_item_transaction_move_entries')->whereIn('transaction_id', $sets['transactions']->pluck('id'))->orderBy('id')->get()->all() : [];
        $records = Schema::hasTable('tracked_item_move_records') ? DB::table('tracked_item_move_records')->where(function (QueryBuilder $query) use ($sets): void {
            foreach ($sets as $table => $rows) {
                $query->orWhere(fn (QueryBuilder $query) => $query->where('entity_type', $table)->whereIn('entity_id', $rows->pluck('id')));
            }
        })->orderBy('id')->get()->all() : [];

        return hash_hmac('sha256', json_encode([$actor->id, $source->getAttributes(), $destination->getAttributes(), $sets, $contexts, $legacy, $records], JSON_THROW_ON_ERROR), config('app.key'));
    }

    private function graphRestoreReason(User $actor, object $move, TrackedItem $source, TrackedItem $destination, bool $lock): ?string
    {
        $records = DB::table('tracked_item_move_records')->where('move_id', $move->id)->orderBy('entity_type')->orderBy('entity_id')->get();
        if ($records->count() !== $move->record_count) {
            return 'Il registro dei collegamenti è incompleto.';
        }
        $sets = [];
        foreach (array_keys($this->graph->fields()) as $table) {
            $sets[$table] = collect();
        }
        foreach ($records as $record) {
            if (! array_key_exists($record->entity_type, $sets)) {
                return 'Il registro contiene un tipo di collegamento non supportato.';
            }
            $row = DB::table($record->entity_type)->where('id', $record->entity_id)->when($lock, fn (QueryBuilder $query) => $query->lockForUpdate())->first();
            if ($row === null || ($row->uuid ?? null) !== $record->entity_uuid || ! hash_equals($record->after_fingerprint, $this->fingerprint($row))) {
                return 'Un collegamento è stato modificato o eliminato dopo lo spostamento.';
            }
            if ($record->context_fingerprint !== $this->graph->contextFingerprint($record->entity_type, $row)) {
                return 'Una ricorrenza ha nuove scadenze o modifiche successive: ripristinarla coinvolgerebbe movimenti creati dopo lo spostamento.';
            }
            if (DB::table('tracked_item_move_records')->where('entity_type', $record->entity_type)->where('entity_id', $record->entity_id)->where('move_id', '>', $move->id)->exists()) {
                return 'Un collegamento è coinvolto in uno spostamento successivo.';
            }
            $sets[$record->entity_type] = $sets[$record->entity_type]->push($row);
        }
        if (DB::table('tracked_item_transaction_move_entries')->whereIn('transaction_id', $sets['transactions']->pluck('id'))->where('move_id', '>', $move->id)->exists()) {
            return 'Una transazione è coinvolta in uno spostamento successivo.';
        }
        $this->graph->authorize($actor, $sets, $lock);

        return $this->graph->blockReason($destination, $source, $sets, $lock);
    }

    /** @param array<string, mixed> $changes */
    private function changeFields(string $table, object $row, array $changes): void
    {
        $allowed = $this->graph->fields()[$table] ?? [];
        if ($changes === [] || array_diff(array_keys($changes), $allowed) !== []) {
            $this->invalid('Campi di riferimento non supportati.');
        }
        $query = DB::table($table)->where('id', $row->id);
        if (isset($row->uuid)) {
            $query->where('uuid', $row->uuid);
        }
        foreach ($changes as $field => $value) {
            if ($field !== 'normalized_payload') {
                $query->where($field, $row->$field);
            }
        }
        if ($query->update($changes) !== 1) {
            $this->invalid('Un collegamento è cambiato. Nessuna modifica è stata applicata.');
        }
    }

    private function fingerprint(object $row): string
    {
        $attributes = (array) $row;
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    private function changeReference(object $row, int $destinationId): void
    {
        $count = DB::table('transactions')->where('id', $row->id)->where('uuid', $row->uuid)->where('tracked_item_id', $row->tracked_item_id)->whereNull('deleted_at')->update(['tracked_item_id' => $destinationId]);
        if ($count !== 1) {
            $this->invalid('Una transazione è cambiata. Nessuna modifica è stata applicata.');
        }
    }

    private function requireRegistry(): void
    {
        if (! Schema::hasTable('tracked_item_transaction_move_entries')) {
            $this->invalid('L’aggiornamento del database per il registro degli spostamenti non è ancora disponibile.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['movements' => $message]);
    }
}
