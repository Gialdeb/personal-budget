<?php

namespace App\Services\Counterparties;

use App\Models\CounterpartyPayment;
use App\Models\Transaction;
use Closure;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Only the typed payment lifecycle may alter its bank document; balance snapshots remain writable. */
class CounterpartyBankMutation
{
    private array $authorized = [];

    public function withPayment(CounterpartyPayment $payment, Closure $operation): mixed
    {
        $id = $payment->transaction_id;
        $this->authorized[$id] = ($this->authorized[$id] ?? 0) + 1;
        try {
            return $operation();
        } finally {
            if (--$this->authorized[$id] === 0) {
                unset($this->authorized[$id]);
            }
        }
    }

    public function permitted(Transaction $transaction): bool
    {
        return isset($this->authorized[$transaction->id]);
    }

    public function owns(Transaction $transaction): bool
    {
        if (! Schema::hasTable('counterparty_payments')) {
            return false;
        }
        $activeColumn = Schema::hasColumn('counterparty_relations', 'activated_at');

        return CounterpartyPayment::query()->where('transaction_id', $transaction->id)->where(function ($query) use ($activeColumn): void {
            $query->whereNull('legacy_source_type');
            if ($activeColumn) {
                $query->orWhereHas('relation', fn ($relation) => $relation->whereNotNull('activated_at'));
            }
        })->exists();
    }

    public function assertGenericMutationAllowed(Transaction $transaction): void
    {
        if (! $this->permitted($transaction) && $this->owns($transaction)) {
            throw ValidationException::withMessages(['transaction' => 'Questo movimento appartiene a un pagamento della controparte. Usa il suo storico per modificarlo, annullarlo o restituirlo.']);
        }
    }
}
