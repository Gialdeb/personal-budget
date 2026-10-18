<?php

namespace App\Services\TrackedItems;

use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\CreditDebtItem;
use App\Models\RecurringEntryOccurrence;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\CreditDebts\ManualEconomicSubjectResolver;
use App\Services\SharedExpenses\SharedOccurrenceCounterpartyResolver;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackedItemMovementGraph
{
    public function __construct(
        private readonly AccessibleAccountsQuery $accounts,
        private readonly OperationalTransactionCategoryResolver $categories,
        private readonly SharedOccurrenceCounterpartyResolver $occurrences,
        private readonly ManualEconomicSubjectResolver $subjects,
    ) {}

    /** @return array<string, list<string>> */
    public function fields(): array
    {
        return [
            'import_rows' => ['normalized_payload'],
            'transactions' => ['tracked_item_id', 'shared_expense_counterparty_id'],
            'recurring_entries' => ['tracked_item_id', 'shared_expense_counterparty_id'],
            'scheduled_entries' => ['tracked_item_id'],
            'budgets' => ['tracked_item_id'],
            'transaction_templates' => ['tracked_item_id', 'shared_counterparty_id'],
            'credit_debt_items' => ['reference_id', 'counterparty_id'],
            'shared_expense_ledger_entries' => ['counterparty_id'],
            'shared_expense_settlements' => ['counterparty_id'],
            'counterparty_relations' => ['counterparty_id'],
            'counterparty_sync_issues' => ['counterparty_id'],
            'tracked_items' => ['parent_id'],
            'tracked_item_aliases' => ['source_tracked_item_id', 'canonical_tracked_item_id'],
        ];
    }

    /** @return array<string, Collection<int, object>> */
    public function collect(TrackedItem $source, bool $lock = false): array
    {
        $sets = [];
        foreach ($this->fields() as $table => $fields) {
            if ($table === 'import_rows') {
                $sets[$table] = DB::table('import_rows')->whereNull('transaction_id')
                    ->whereNotIn('status', ['imported', 'already_imported', 'skipped'])
                    ->where(fn (QueryBuilder $query) => $query->where('normalized_payload->tracked_item_id', $source->id)->orWhere('normalized_payload->tracked_item_uuid', $source->uuid))
                    ->orderBy('id')->when($lock, fn (QueryBuilder $query) => $query->lockForUpdate())->get();

                continue;
            }
            $sets[$table] = DB::table($table)->where(function (QueryBuilder $query) use ($fields, $source): void {
                foreach ($fields as $field) {
                    $query->orWhere($field, $source->id);
                }
            })->orderBy('id')->when($lock, fn (QueryBuilder $query) => $query->lockForUpdate())->get();
        }

        return $sets;
    }

    /** @param array<string, Collection<int, object>> $sets */
    public function authorize(User $actor, array $sets, bool $lock): void
    {
        $importAccountIds = [];
        foreach ($sets['import_rows'] as $row) {
            $import = DB::table('imports')->where('id', $row->import_id)->when($lock, fn (QueryBuilder $query) => $query->lockForUpdate())->firstOrFail();
            if ($import->user_id !== $actor->id) {
                $this->invalid('Un’importazione collegata non è modificabile con le tue autorizzazioni.');
            }
            $payload = json_decode($row->normalized_payload, true, flags: JSON_THROW_ON_ERROR);
            $importAccountIds[] = $payload['account_id'] ?? $import->account_id;
        }
        $accountIds = collect($sets)->flatMap(fn (Collection $rows) => $rows->pluck('account_id'))->merge($importAccountIds)->filter()->unique()->sort()->values();
        if ($lock) {
            Account::query()->whereIn('id', $accountIds)->orderBy('id')->lockForUpdate()->get();
            AccountMembership::query()->whereIn('account_id', $accountIds)->where('user_id', $actor->id)->orderBy('id')->lockForUpdate()->get();
        }
        $editable = $this->accounts->editableIds($actor);
        if (array_diff(array_filter($importAccountIds), $editable) !== []) {
            $this->invalid('Il conto di un’importazione non è modificabile.');
        }
        foreach ($sets as $table => $rows) {
            foreach ($rows as $row) {
                if (isset($row->account_id)) {
                    if (! in_array($row->account_id, $editable, true)) {
                        $this->invalid('Un collegamento si trova in un conto non modificabile: nessuno spostamento parziale è consentito.');
                    }
                } elseif (isset($row->user_id) && $row->user_id !== $actor->id && ! in_array($table, ['tracked_item_aliases'], true)) {
                    $this->invalid('Non hai i permessi per modificare tutti i collegamenti.');
                }
            }
        }
    }

    /** @param array<string, Collection<int, object>> $sets */
    public function blockReason(TrackedItem $source, TrackedItem $destination, array $sets, bool $lock = false): ?string
    {
        foreach (['shared_expense_settlements' => 'regolazioni già registrate', 'counterparty_relations' => 'rapporti economici con una controparte e una valuta proprie'] as $table => $description) {
            if ($sets[$table]->isNotEmpty()) {
                return 'Il riferimento di origine ha '.$sets[$table]->count().' '.$description.'. Spostarli cambierebbe la controparte dello storico economico, che deve restare invariata. L’intera operazione è bloccata per evitare uno spostamento parziale.';
            }
        }
        if ($sets['counterparty_sync_issues']->contains(fn (object $row): bool => $row->resolved_at === null)) {
            return 'Una segnalazione di sincronizzazione è ancora aperta. Verifica il problema indicato nella gestione della controparte prima di spostare i collegamenti. Non è un blocco dello storico immutabile.';
        }
        if ($sets['tracked_item_aliases']->isNotEmpty() || $sets['tracked_items']->isNotEmpty()) {
            return 'Il riferimento ha alias permanenti o riferimenti figli: risolvi prima questi collegamenti. Nessuna modifica verrà applicata.';
        }
        try {
            $shared = $sets['transactions']->contains('shared_expense_counterparty_id', $source->id)
                || $sets['recurring_entries']->contains('shared_expense_counterparty_id', $source->id)
                || $sets['transaction_templates']->contains('shared_counterparty_id', $source->id)
                || $sets['shared_expense_ledger_entries']->isNotEmpty();
            if ($shared && (! $destination->is_shared_expense_counterpart || $destination->account_id !== null)) {
                return 'La destinazione deve essere una controparte personale abilitata alle spese condivise.';
            }
            $economic = $sets['transactions']->contains('shared_expense_counterparty_id', $source->id)
                || $sets['shared_expense_ledger_entries']->isNotEmpty() || $sets['credit_debt_items']->isNotEmpty();
            if ($economic && DB::table('counterparty_relations')->whereIn('counterparty_id', [$source->id, $destination->id])->exists()) {
                return 'La destinazione ha un rapporto economico protetto: lo spostamento richiederebbe una rettifica del ledger.';
            }
            foreach ($sets as $table => $rows) {
                foreach ($rows as $row) {
                    if (isset($row->account_id) && in_array('tracked_item_id', $this->fields()[$table], true) && (int) $row->tracked_item_id === $source->id) {
                        $account = Account::findOrFail($row->account_id);
                        if ($this->categories->findTrackedItemForAccount($account, $destination->id) === null) {
                            return 'La destinazione non è compatibile con uno dei conti interessati.';
                        }
                    }
                }
            }
            foreach ($sets['import_rows'] as $row) {
                $payload = json_decode($row->normalized_payload, true, flags: JSON_THROW_ON_ERROR);
                if ((! empty($payload['tracked_item_id']) && (int) $payload['tracked_item_id'] !== $source->id)
                    || (! empty($payload['tracked_item_uuid']) && $payload['tracked_item_uuid'] !== $source->uuid)) {
                    return 'Un’importazione ha riferimenti discordanti: correggila prima dello spostamento.';
                }
                $import = DB::table('imports')->find($row->import_id);
                $accountId = $payload['account_id'] ?? $import->account_id;
                if ($accountId !== null && $this->categories->findTrackedItemForAccount(Account::findOrFail($accountId), $destination->id) === null) {
                    return 'La destinazione non è compatibile con un’importazione in attesa.';
                }
            }
            foreach ($sets['transactions'] as $row) {
                $transaction = Transaction::withTrashed()->findOrFail($row->id);
                if ((int) $row->shared_expense_counterparty_id === $source->id) {
                    app(TransactionMutationService::class)->ensureNoLinkedObligation($transaction);
                    if (DB::table('counterparty_payments')->where('transaction_id', $row->id)->exists()) {
                        return 'Questo pagamento conserva la controparte economica originale: non è possibile spostarla insieme agli altri collegamenti.';
                    }
                }
            }
            foreach ($sets['credit_debt_items'] as $row) {
                $item = CreditDebtItem::withTrashed()->findOrFail($row->id);
                $this->subjects->assertConsistent($item);
                if ($this->subjects->confirmationBlockReason($item) !== null) {
                    return 'Un credito o debito contiene pagamenti o storico protetto: nessuno spostamento parziale è consentito.';
                }
                $item->reference_id = $destination->id;
                $item->counterparty_id = $destination->id;
                $this->subjects->normalize($item);
            }
            $planIds = $sets['recurring_entries']->pluck('id');
            $transactionIds = $sets['transactions']->pluck('id');
            $occurrenceIds = $sets['transactions']->concat($sets['shared_expense_ledger_entries'])->pluck('recurring_entry_occurrence_id')->filter();
            $occurrences = RecurringEntryOccurrence::query()->where(fn (Builder $query) => $query->whereIn('recurring_entry_id', $planIds)->orWhereIn('id', $occurrenceIds)->orWhereIn('converted_transaction_id', $transactionIds)->orWhereIn('matched_transaction_id', $transactionIds))->orderBy('id')->when($lock, fn (Builder $query) => $query->lockForUpdate())->get();
            $changedPlans = $sets['recurring_entries']->where('shared_expense_counterparty_id', $source->id)->pluck('id');
            $changedTransactions = $sets['transactions']->where('shared_expense_counterparty_id', $source->id)->pluck('id');
            foreach ($occurrences as $occurrence) {
                $history = $this->occurrences->historicalIdentity($occurrence, $lock);
                if ($history !== null && ($changedPlans->contains($occurrence->recurring_entry_id)
                    || $changedTransactions->contains($occurrence->converted_transaction_id) || $changedTransactions->contains($occurrence->matched_transaction_id)
                    || $sets['transactions']->where('shared_expense_counterparty_id', $source->id)->contains('recurring_entry_occurrence_id', $occurrence->id)
                    || $sets['shared_expense_ledger_entries']->contains('recurring_entry_occurrence_id', $occurrence->id))) {
                    return 'Una spesa condivisa appartiene a un’occorrenza già avviata: la fase 3A impone di conservare la controparte economica originale.';
                }
            }
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->join(' ');
        }

        return null;
    }

    /** @param array<string, Collection<int, object>> $sets
     * @return array<int, array{key: string, label: string, count: int, items: array<int, array<string, mixed>>}>
     */
    public function groups(array $sets): array
    {
        $labels = ['import_rows' => 'Importazioni in attesa', 'transactions' => 'Transazioni', 'recurring_entries' => 'Ricorrenze e piani', 'shared' => 'Spese condivise', 'scheduled_entries' => 'Movimenti pianificati', 'budgets' => 'Budget', 'transaction_templates' => 'Template', 'credit_debt_items' => 'Crediti e debiti', 'diagnostics' => 'Segnalazioni di sincronizzazione', 'protected' => 'Collegamenti protetti'];
        $groups = [];
        foreach ($labels as $key => $label) {
            $groups[$key] = ['key' => $key, 'label' => $label, 'count' => 0, 'items' => []];
        }
        foreach ($sets as $table => $rows) {
            foreach ($rows as $row) {
                $key = match ($table) {
                    'shared_expense_ledger_entries' => 'shared',
                    'transactions' => $row->shared_expense_counterparty_id !== null ? 'shared' : 'transactions',
                    'shared_expense_settlements', 'counterparty_relations', 'tracked_items', 'tracked_item_aliases' => 'protected',
                    'counterparty_sync_issues' => 'diagnostics',
                    default => $table,
                };
                $groups[$key]['items'][] = ['key' => $table.':'.$row->id, 'title' => match ($table) {
                    'counterparty_relations' => 'Rapporto economico · '.$row->currency_code,
                    'shared_expense_settlements' => 'Regolazione registrata',
                    'counterparty_sync_issues' => $row->resolved_at === null ? 'Segnalazione da verificare' : 'Segnalazione già risolta',
                    default => $row->description ?? $row->title ?? $row->name ?? $row->raw_description ?? $labels[$key],
                },
                    'date' => $row->transaction_date ?? $row->entry_date ?? $row->start_date ?? $row->scheduled_date ?? $row->due_date ?? null,
                    'archived' => ($row->deleted_at ?? null) !== null];
                $groups[$key]['count']++;
            }
        }

        return array_values(array_filter($groups, fn (array $group): bool => $group['count'] > 0));
    }

    /** @return array<string, mixed> */
    public function changes(string $table, object $row, int $sourceId, int $destinationId): array
    {
        if ($table === 'import_rows') {
            $payload = json_decode($row->normalized_payload, true, flags: JSON_THROW_ON_ERROR);
            $destination = TrackedItem::findOrFail($destinationId);
            if (array_key_exists('tracked_item_id', $payload)) {
                $payload['tracked_item_id'] = $destinationId;
            }
            if (array_key_exists('tracked_item_uuid', $payload)) {
                $payload['tracked_item_uuid'] = $destination->uuid;
            }

            return ['normalized_payload' => json_encode($payload, JSON_THROW_ON_ERROR)];
        }
        $changes = [];
        foreach ($this->fields()[$table] as $field) {
            if ((int) $row->$field === $sourceId) {
                $changes[$field] = $destinationId;
            }
        }

        return $changes;
    }

    public function contextFingerprint(string $table, object $row): ?string
    {
        if ($table !== 'recurring_entries') {
            return null;
        }
        $occurrences = DB::table('recurring_entry_occurrences')->where('recurring_entry_id', $row->id)->orderBy('id')->get()->all();
        $periods = DB::table('recurring_entry_periods')->where('recurring_entry_id', $row->id)->orderBy('id')->get()->all();

        return hash('sha256', json_encode([$occurrences, $periods], JSON_THROW_ON_ERROR));
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['movements' => $message]);
    }
}
