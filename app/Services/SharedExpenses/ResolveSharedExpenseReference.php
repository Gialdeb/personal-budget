<?php

namespace App\Services\SharedExpenses;

use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartySourceLink;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Counterparties\SharedCounterpartyShadowService;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveSharedExpenseReference
{
    public function __construct(private readonly AccessibleAccountsQuery $accounts, private readonly SharedCounterpartyShadowService $shadow) {}

    public function resolve(User $user, string $kind, string $uuid, string $referenceUuid): void
    {
        abort_unless(in_array($kind, ['transaction', 'ledger'], true), 404);
        DB::transaction(function () use ($user, $kind, $uuid, $referenceUuid): void {
            $query = $kind === 'transaction' ? Transaction::query()->whereIn('account_id', $this->accounts->editable($user)->pluck('accounts.id')) : SharedExpenseLedgerEntry::query()->where('user_id', $user->id);
            $source = $query->where('uuid', $uuid)->firstOrFail();
            $field = $kind === 'transaction' ? 'shared_expense_counterparty_id' : 'counterparty_id';
            $party = TrackedItem::query()->ownedBy($user->id)->where('is_active', true)->where('uuid', $referenceUuid)->firstOrFail();
            $expectedPartyId = $source->$field;
            TrackedItem::query()->whereIn('id', array_filter([$source->$field, $party->id]))->orderBy('id')->lockForUpdate()->get();
            $source = $query->lockForUpdate()->firstOrFail();
            if ($expectedPartyId !== $source->$field && $party->id !== $source->$field) {
                throw ValidationException::withMessages(['reference_uuid' => 'Il movimento è stato modificato nel frattempo. Riaprilo prima di confermare il riferimento.']);
            }
            abort_if($source instanceof Transaction && $source->shared_expense_paid_by === null, 404);
            if ($source instanceof Transaction) {
                app(TransactionMutationService::class)->ensureNoLinkedObligation($source);
            }
            $before = $source->$field;
            if ($before !== $party->id) {
                $type = $kind === 'transaction' ? 'shared_transaction_share' : 'shared_ledger_share';
                $captured = CounterpartySourceLink::where('source_type', $type)->where('source_identity', $uuid)->exists()
                    || CounterpartyLedgerEntry::where('source_type', $type)->where('source_identity', $uuid)->exists();
                $payments = $before !== null && SharedExpenseSettlement::where('user_id', $user->id)->where('counterparty_id', $before)->exists();
                if ($captured || $payments) {
                    throw ValidationException::withMessages(['reference_uuid' => 'Questo movimento ha pagamenti o uno storico già collegato. Il riferimento non può essere cambiato: verifica prima i collegamenti nella situazione completa.']);
                }
                if ($source->recurring_entry_occurrence_id !== null) {
                    throw ValidationException::withMessages(['reference_uuid' => 'Il riferimento proviene da una ricorrenza. Correggilo nel piano prima di registrare nuovamente il movimento.']);
                }
            }
            if (! $party->is_shared_expense_counterpart) {
                $party->update(['is_shared_expense_counterpart' => true]);
            }
            if ($before === $party->id) {
                return;
            }
            $source->$field = $party->id;
            $source->save();
            $this->shadow->changed($source);
            activity('shared_expenses')->causedBy($user)->performedOn($source)->event('shared_reference_confirmed')
                ->withProperties(['before' => $before, 'after' => $party->id, 'source_uuid' => $uuid])->log('shared_expense.reference_confirmed');
        });
    }
}
