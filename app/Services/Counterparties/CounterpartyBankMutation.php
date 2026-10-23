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
        return in_array((int) $transaction->id, $this->ownedTransactionIds([(int) $transaction->id]), true);
    }

    /**
     * @param  array<int, int>  $transactionIds
     * @return array<int, int>
     */
    public function ownedTransactionIds(array $transactionIds): array
    {
        if ($transactionIds === [] || ! Schema::hasTable('counterparty_payments')) {
            return [];
        }
        $activeColumn = Schema::hasColumn('counterparty_relations', 'activated_at');

        return CounterpartyPayment::query()->whereIn('transaction_id', $transactionIds)->where(function ($query) use ($activeColumn): void {
            $query->whereNull('legacy_source_type');
            if ($activeColumn) {
                $query->orWhereHas('relation', fn ($relation) => $relation->whereNotNull('activated_at'));
            }
        })->distinct()->pluck('transaction_id')->map(fn ($id): int => (int) $id)->all();
    }

    public function assertGenericMutationAllowed(Transaction $transaction): void
    {
        if (! $this->permitted($transaction) && $this->owns($transaction)) {
            throw ValidationException::withMessages(['transaction' => __('form_errors.this_transaction_belongs_to_a_counterparty_payment_use_its_history_to_edit')]);
        }
    }
}
