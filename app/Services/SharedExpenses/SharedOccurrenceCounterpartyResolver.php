<?php

namespace App\Services\SharedExpenses;

use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyRelation;
use App\Models\CounterpartySourceLink;
use App\Models\RecurringEntryOccurrence;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\TrackedItems\TrackedItemIdentityService;
use App\Services\TrackedItems\TrackedItemResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class SharedOccurrenceCounterpartyResolver
{
    public function __construct(
        private readonly TrackedItemIdentityService $identities,
        private readonly TrackedItemResolver $catalogs,
        private readonly AccessibleAccountsQuery $accounts,
    ) {}

    /** Called inside the posting transaction; catalog locks survive until its commit. */
    public function forPosting(RecurringEntryOccurrence $occurrence): ?TrackedItem
    {
        return $this->catalogs->inCatalog($occurrence->recurringEntry->user_id, null, function () use ($occurrence): ?TrackedItem {
            $occurrence = RecurringEntryOccurrence::query()->where('uuid', $occurrence->uuid)->lockForUpdate()->firstOrFail();
            $entry = $occurrence->recurringEntry()->lockForUpdate()->firstOrFail();
            $occurrence->setRelation('recurringEntry', $entry);
            $historical = $this->historicalIdentity($occurrence, lock: true);
            if ($historical !== null) {
                return $historical['counterparty_id'] === null ? null : TrackedItem::query()->lockForUpdate()->findOrFail($historical['counterparty_id']);
            }
            if ($entry->shared_expense_counterparty_id === null) {
                return null;
            }
            $party = $this->identities->forNewOperation(TrackedItem::query()->ownedBy($entry->user_id), id: $entry->shared_expense_counterparty_id, field: 'occurrence');
            if (! $party->is_shared_expense_counterpart) {
                $this->conflict();
            }

            return $party;
        });
    }

    /**
     * All components participate, including refunded and soft-deleted documents.
     * No evidence is rewritten or canonicalized by this read-only inspection.
     *
     * @return array{counterparty_id: int|null, currency: string}|null
     */
    public function historicalIdentity(RecurringEntryOccurrence $occurrence, bool $lock = false): ?array
    {
        $entry = $occurrence->recurringEntry;
        $transactions = Transaction::withTrashed()->where(function (Builder $query) use ($occurrence): void {
            $query->where('recurring_entry_occurrence_id', $occurrence->id)
                ->orWhereIn('id', array_filter([$occurrence->converted_transaction_id, $occurrence->matched_transaction_id]));
        })->orderBy('id')->when($lock, fn (Builder $query) => $query->lockForUpdate())->get();
        $external = SharedExpenseLedgerEntry::query()->where('recurring_entry_occurrence_id', $occurrence->id)
            ->orderBy('id')->when($lock, fn (Builder $query) => $query->lockForUpdate())->get();
        $economicIdentity = 'shared-occurrence:'.$occurrence->uuid;
        $documentUuids = $transactions->pluck('uuid')->merge($external->pluck('uuid'))->push($occurrence->uuid)->all();
        $sharedTypes = ['shared_transaction_share', 'shared_ledger_share'];
        $links = CounterpartySourceLink::query()->where(function (Builder $query) use ($economicIdentity, $documentUuids, $sharedTypes): void {
            $query->where('economic_identity', $economicIdentity)->orWhere(fn (Builder $query) => $query->whereIn('source_type', $sharedTypes)->whereIn('source_identity', $documentUuids));
        })->orderBy('id')->when($lock, fn (Builder $query) => $query->lockForUpdate())->get();
        $ledger = CounterpartyLedgerEntry::query()->whereIn('source_type', $sharedTypes)->whereIn('source_identity', $documentUuids)
            ->orderBy('id')->when($lock, fn (Builder $query) => $query->lockForUpdate())->get();
        $obligations = CounterpartyObligation::query()->where(function (Builder $query) use ($economicIdentity, $documentUuids, $sharedTypes, $ledger): void {
            $query->where('economic_identity', $economicIdentity)
                ->orWhereIn('id', $ledger->pluck('obligation_id')->filter())
                ->orWhere(fn (Builder $query) => $query->whereIn('source_type', $sharedTypes)->whereIn('source_identity', $documentUuids));
        })->orderBy('id')->when($lock, fn (Builder $query) => $query->lockForUpdate())->get();
        $relationIds = $links->pluck('relation_id')->merge($obligations->pluck('relation_id'))->merge($ledger->pluck('relation_id'))->unique();
        $relations = CounterpartyRelation::query()->whereIn('id', $relationIds)->orderBy('id')
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())->get();
        if ($relations->count() !== $relationIds->count()) {
            $this->conflict();
        }
        $partyIds = $transactions->pluck('shared_expense_counterparty_id')->merge($external->pluck('counterparty_id'))->merge($relations->pluck('counterparty_id'))->filter()->unique();
        if ($partyIds->isNotEmpty()) {
            $accessibleAccountIds = $this->accounts->ids($entry->user_id);
            if ($transactions->contains(fn (Transaction $source): bool => ! in_array($source->account_id, $accessibleAccountIds, true))) {
                $this->conflict();
            }
        }
        $parties = TrackedItem::query()->whereIn('id', $partyIds)->orderBy('id')
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())->get()->keyBy('id');
        foreach ($partyIds as $id) {
            $party = $parties->get($id);
            if ($party === null || $party->account_id !== null || $party->user_id !== $entry->user_id) {
                $this->conflict();
            }
        }
        $evidence = $transactions->map(fn (Transaction $source): array => ['counterparty_id' => $source->shared_expense_counterparty_id, 'currency' => $source->currency])
            ->concat($external->map(function (SharedExpenseLedgerEntry $source) use ($entry): array {
                if ($source->user_id !== $entry->user_id) {
                    $this->conflict();
                }

                return ['counterparty_id' => $source->counterparty_id, 'currency' => $source->currency];
            }))->concat($relations->map(function (CounterpartyRelation $relation) use ($entry): array {
                if ($relation->user_id !== $entry->user_id) {
                    $this->conflict();
                }

                return ['counterparty_id' => $relation->counterparty_id, 'currency' => $relation->currency_code];
            }));
        foreach ($evidence as $identity) {
            if ($identity['currency'] !== $entry->currency || $identity !== $evidence->first()) {
                $this->conflict();
            }
        }
        foreach ($ledger as $row) {
            if ($row->currency_code !== $entry->currency) {
                $this->conflict();
            }
        }
        if ($links->pluck('economic_identity')->merge($obligations->pluck('economic_identity'))->unique()->count() > 1) {
            $this->conflict();
        }

        return $evidence->first();
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages(['occurrence' => 'Le componenti storiche della scadenza hanno identità economiche o valute incompatibili. Nessuna registrazione è stata eseguita.']);
    }
}
