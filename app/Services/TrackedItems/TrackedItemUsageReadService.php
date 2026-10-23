<?php

namespace App\Services\TrackedItems;

use App\Models\RecurringOccurrencePaymentAllocation;
use App\Models\TrackedItem;
use App\Services\Accounts\AccessibleAccountsQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrackedItemUsageReadService
{
    public function __construct(private AccessibleAccountsQuery $accounts, private TrackedItemMovementGraph $graph) {}

    /**
     * @param  Collection<int, TrackedItem>  $references
     * @return array<int, array<string, mixed>>
     */
    public function counts(int $userId, Collection $references): array
    {
        if ($references->isEmpty()) {
            return [];
        }
        $ids = $references->pluck('id')->all();
        $byUuid = $references->pluck('id', 'uuid');
        $accounts = $this->accounts->ids($userId);
        $entities = array_fill_keys($ids, []);
        $sets = [];
        foreach ($this->graph->fields() as $table => $fields) {
            if ($table === 'import_rows') {
                $rows = DB::table($table)->join('imports', 'imports.id', '=', 'import_rows.import_id')
                    ->where('imports.user_id', $userId)->whereNull('import_rows.transaction_id')
                    ->whereNotIn('import_rows.status', ['imported', 'already_imported', 'skipped'])
                    ->where(function (Builder $query) use ($ids, $byUuid): void {
                        $query->whereIn('normalized_payload->tracked_item_id', $ids)->orWhereIn('normalized_payload->tracked_item_uuid', $byUuid->keys()->all());
                    })->select('import_rows.id', 'import_rows.normalized_payload', 'imports.account_id as usage_import_account_id', 'imports.deleted_at')->get();
            } else {
                $rows = DB::table($table)->where(function (Builder $query) use ($fields, $ids): void {
                    foreach ($fields as $field) {
                        $query->orWhereIn($field, $ids);
                    }
                })->get($this->columns($table, $fields));
            }
            $sets[$table] = $rows->filter(fn (object $row): bool => $this->visible($row, $userId, $accounts));
            foreach ($sets[$table] as $row) {
                $linked = $table === 'import_rows'
                    ? $this->importReferences($row, $byUuid)
                    : array_map(fn (string $field): ?int => isset($row->$field) ? (int) $row->$field : null, $fields);
                foreach (array_intersect($ids, array_unique($linked)) as $id) {
                    $entities[$id][$table.':'.$row->id] = $this->entity($table, $row, true);
                }
            }
        }
        $planRefs = $this->referencesByRecord($entities, 'recurring_entries');
        $transactionRefs = $this->referencesByRecord($entities, 'transactions');
        $occurrenceRefs = [];
        foreach (['transactions', 'shared_expense_ledger_entries'] as $table) {
            $recordRefs = $this->referencesByRecord($entities, $table);
            foreach ($sets[$table] as $row) {
                if ($row->recurring_entry_occurrence_id !== null) {
                    $occurrenceRefs[$row->recurring_entry_occurrence_id] = array_unique([
                        ...($occurrenceRefs[$row->recurring_entry_occurrence_id] ?? []), ...($recordRefs[$row->id] ?? []),
                    ]);
                }
            }
        }
        $occurrences = DB::table('recurring_entry_occurrences')->where(function (Builder $query) use ($planRefs, $transactionRefs, $occurrenceRefs): void {
            $query->whereIn('recurring_entry_id', array_keys($planRefs))->orWhereIn('id', array_keys($occurrenceRefs))
                ->orWhereIn('converted_transaction_id', array_keys($transactionRefs))->orWhereIn('matched_transaction_id', array_keys($transactionRefs));
        })->get(['id', 'account_id', 'recurring_entry_id', 'converted_transaction_id', 'matched_transaction_id'])->filter(fn (object $row): bool => $this->visible($row, $userId, $accounts));
        foreach ($occurrences as $row) {
            $occurrenceRefs[$row->id] = array_unique([
                ...($occurrenceRefs[$row->id] ?? []), ...($planRefs[$row->recurring_entry_id] ?? []),
                ...($transactionRefs[$row->converted_transaction_id] ?? []), ...($transactionRefs[$row->matched_transaction_id] ?? []),
            ]);
            foreach ($occurrenceRefs[$row->id] as $id) {
                $entities[$id]['recurring_entry_occurrences:'.$row->id] = $this->entity('recurring_entry_occurrences', $row, false);
            }
        }
        $allocations = RecurringOccurrencePaymentAllocation::query()->valid()->whereIn('occurrence_id', $occurrences->pluck('id'))->get(['occurrence_id', 'transaction_id']);
        $transactionIds = $occurrences->pluck('converted_transaction_id')->merge($occurrences->pluck('matched_transaction_id'))->merge($allocations->pluck('transaction_id'))->filter()->unique()->all();
        $transactions = DB::table('transactions')->where(function (Builder $query) use ($transactionIds, $occurrences): void {
            $query->whereIn('id', $transactionIds)->orWhereIn('recurring_entry_occurrence_id', $occurrences->pluck('id')->all());
        })->get($this->columns('transactions', ['tracked_item_id', 'shared_expense_counterparty_id']))->filter(fn (object $row): bool => $this->visible($row, $userId, $accounts));
        foreach ($transactions as $row) {
            $linkedOccurrences = $occurrences->filter(fn (object $occurrence): bool => (int) $row->recurring_entry_occurrence_id === $occurrence->id
                || $row->id === $occurrence->converted_transaction_id || $row->id === $occurrence->matched_transaction_id
                || $allocations->contains(fn ($allocation): bool => $allocation->transaction_id === $row->id && $allocation->occurrence_id === $occurrence->id));
            foreach ($linkedOccurrences as $occurrence) {
                foreach ($occurrenceRefs[$occurrence->id] as $id) {
                    if ($row->tracked_item_id !== null && (int) $row->tracked_item_id !== $id) {
                        continue;
                    }
                    $entities[$id]['transactions:'.$row->id] ??= $this->entity('transactions', $row, false);
                }
            }
        }
        $originalEntities = $entities;
        $aliases = $sets['tracked_item_aliases']->pluck('canonical_tracked_item_id', 'source_tracked_item_id')->all();
        foreach ($aliases as $sourceId => $targetId) {
            $seen = [$sourceId];
            while (isset($aliases[$targetId]) && ! in_array($targetId, $seen, true)) {
                $seen[] = $targetId;
                $targetId = $aliases[$targetId];
            }
            if (! isset($entities[$targetId]) || in_array($targetId, $seen, true)) {
                continue;
            }
            foreach ($originalEntities[$sourceId] ?? [] as $key => $link) {
                $entities[$targetId][$key] ??= [...$link, 'direct' => false];
            }
        }
        $result = [];
        $order = array_flip(['transactions', 'recurring_entries', 'occurrences', 'shared']);
        foreach ($entities as $id => $links) {
            $groups = [];
            foreach ($links as $link) {
                $key = $link['group'];
                $groups[$key] ??= ['key' => $key, 'direct' => 0, 'indirect' => 0, 'archived' => 0, 'total' => 0, 'types' => []];
                $groups[$key][$link['direct'] ? 'direct' : 'indirect']++;
                $groups[$key]['total']++;
                $groups[$key]['types'][$link['table']] = ($groups[$key]['types'][$link['table']] ?? 0) + 1;
                $groups[$key]['archived'] += (int) $link['archived'];
            }
            uksort($groups, fn (string $first, string $second): int => ($order[$first] ?? 4) <=> ($order[$second] ?? 4));
            $direct = count(array_filter($links, fn (array $link): bool => $link['direct']));
            $result[$id] = ['link_counts' => ['total' => count($links), 'direct' => $direct, 'indirect' => count($links) - $direct, 'groups' => array_values($groups)], 'usage_count' => count($links), 'used' => $links !== []];
        }

        return $result;
    }

    /** @param list<string> $fields
     * @return list<string>
     */
    private function columns(string $table, array $fields): array
    {
        $scope = match ($table) {
            'transactions' => ['user_id', 'account_id', 'deleted_at', 'recurring_entry_occurrence_id'],
            'recurring_entries', 'scheduled_entries', 'transaction_templates', 'tracked_items' => ['user_id', 'account_id'],
            'credit_debt_items' => ['user_id', 'account_id', 'deleted_at'],
            'shared_expense_ledger_entries' => ['user_id', 'recurring_entry_occurrence_id'],
            'tracked_item_aliases' => [],
            default => ['user_id'],
        };

        return array_values(array_unique(['id', ...$fields, ...$scope]));
    }

    /** @param array<int, int> $accountIds */
    private function visible(object $row, int $userId, array $accountIds): bool
    {
        if (property_exists($row, 'usage_import_account_id')) {
            $payload = json_decode($row->normalized_payload, true);
            $accountId = $payload['account_id'] ?? $row->usage_import_account_id;

            return $accountId === null || in_array((int) $accountId, $accountIds, true);
        }

        return isset($row->account_id) ? in_array((int) $row->account_id, $accountIds, true) : (! isset($row->user_id) || (int) $row->user_id === $userId);
    }

    /** @return array{group: string, table: string, direct: bool, archived: bool} */
    private function entity(string $table, object $row, bool $direct): array
    {
        return ['table' => $table, 'group' => match ($table) {
            'transactions' => $row->shared_expense_counterparty_id !== null ? 'shared' : 'transactions',
            'shared_expense_ledger_entries', 'shared_expense_settlements' => 'shared',
            'recurring_entries' => 'recurring_entries',
            'recurring_entry_occurrences' => 'occurrences',
            default => $table,
        }, 'direct' => $direct, 'archived' => ($row->deleted_at ?? null) !== null];
    }

    /** @return array<int, int|null> */
    private function importReferences(object $row, Collection $byUuid): array
    {
        $payload = json_decode($row->normalized_payload, true);

        return [$payload['tracked_item_id'] ?? null, $byUuid->get($payload['tracked_item_uuid'] ?? '')];
    }

    /** @param array<int, array<string, array<string, mixed>>> $entities
     * @return array<int, list<int>>
     */
    private function referencesByRecord(array $entities, string $table): array
    {
        $result = [];
        foreach ($entities as $id => $links) {
            foreach (array_keys($links) as $key) {
                if (str_starts_with($key, $table.':')) {
                    $result[(int) substr($key, strlen($table) + 1)][] = $id;
                }
            }
        }

        return $result;
    }
}
