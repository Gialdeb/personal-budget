<?php

namespace App\Services\Counterparties;

use App\Enums\CounterpartyPaymentDirectionEnum as Direction;
use App\Models\Account;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyOperation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\TransactionExchangeSnapshotService;
use App\Services\Transactions\TransactionMutationService;
use App\Support\CurrencyMinorUnits;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** All public mutations lock counterparty, relation, documents, then accounts in ascending ID order. */
class CounterpartyPaymentService
{
    public function __construct(private readonly CounterpartyLedgerService $ledger, private readonly CounterpartyProjectionService $projections, private readonly CounterpartyAuthority $authority, private readonly CounterpartyReconciliationService $reconciliation, private readonly AccessibleAccountsQuery $accounts, private readonly TransactionMutationService $transactions, private readonly TransactionExchangeSnapshotService $exchange, private readonly CounterpartyBankMutation $bank, private readonly SharedCounterpartyShadowService $shadow) {}

    public function activate(User $user, CounterpartyRelation $relation, string $key): array
    {
        return $this->operation($user, $relation, 'activate', ['operation_key' => $key], function (CounterpartyRelation $relation): array {
            $this->require($relation->minor_unit <= 2, 'relation', 'Questa valuta richiede una precisione non supportata dai conti bancari attuali.');
            $report = $this->reconciliation->inspect($relation);
            $this->require($report['readiness'] === 'reconciled', 'relation', 'Questo rapporto non è riconciliato. Continua a usare i flussi esistenti.');
            if ($relation->activated_at === null) {
                $relation->update(['activated_at' => now(), 'activation_snapshot' => ['legacy_balance_minor' => $report['legacy_balance_minor'], 'ledger_balance_minor' => $report['ledger_balance_minor'], 'activated_by' => $relation->user_id], 'migration_state' => 'active']);
            }

            return ['relation_uuid' => $relation->uuid];
        }, false);
    }

    public function create(User $user, CounterpartyRelation $relation, array $data): array
    {
        return $this->operation($user, $relation, 'payment', $data, function (CounterpartyRelation $relation) use ($user, $data): array {
            $payment = $this->createLocked($user, $relation, $data);

            return ['payment_uuid' => $payment->uuid];
        });
    }

    public function cancel(User $user, CounterpartyRelation $relation, CounterpartyPayment $payment, array $data): array
    {
        return $this->operation($user, $relation, 'cancel', [...$data, 'payment_uuid' => $payment->uuid], function (CounterpartyRelation $relation) use ($user, $payment, $data): array {
            $payment = $this->payment($relation, $payment);
            $this->cancelLocked($user, $payment, $data);

            return ['payment_uuid' => $payment->uuid];
        });
    }

    public function revise(User $user, CounterpartyRelation $relation, CounterpartyPayment $payment, array $data): array
    {
        return $this->operation($user, $relation, 'revise', [...$data, 'payment_uuid' => $payment->uuid], function (CounterpartyRelation $relation) use ($user, $payment, $data): array {
            $payment = $this->payment($relation, $payment);
            $this->require($payment->return_of_id === null, 'payment', 'Per una restituzione usa annullamento e una nuova operazione.');
            $this->cancelLocked($user, $payment, $data);
            $replacement = $this->createLocked($user, $relation, $data, $payment);

            return ['payment_uuid' => $replacement->uuid, 'previous_payment_uuid' => $payment->uuid];
        });
    }

    public function reallocate(User $user, CounterpartyRelation $relation, CounterpartyPayment $payment, array $data): array
    {
        return $this->operation($user, $relation, 'allocation', [...$data, 'payment_uuid' => $payment->uuid], function (CounterpartyRelation $relation) use ($user, $payment, $data): array {
            $payment = $this->payment($relation, $payment);
            $this->require($payment->return_of_id === null && $this->projections->effectivePayment($payment) > 0, 'payment', 'Pagamento non riallocabile.');
            foreach ($this->projections->activeAllocations($payment) as $allocation) {
                $this->ledger->reverseAllocation($allocation, $data['date'] ?? $payment->economic_date->toDateString(), $this->key($data, 'release:'.$allocation->uuid), $user, 'Riallocazione esplicita.');
            }
            $amount = $this->projections->effectivePayment($payment) - $this->authority->returned($payment);
            $plan = $this->allocationPlan($relation, $payment->direction, $amount, $data['allocations'] ?? null, (bool) ($data['allow_unallocated'] ?? false), $data['scope'] ?? 'all', false);
            foreach ($plan as $uuid => $minor) {
                $obligation = $relation->obligations()->where('uuid', $uuid)->firstOrFail();
                $this->ledger->allocate($payment, $obligation, $minor, $data['date'] ?? $payment->economic_date->toDateString(), $this->key($data, 'allocate:'.$uuid), $user);
            }

            return ['payment_uuid' => $payment->uuid, 'allow_unallocated' => (bool) ($data['allow_unallocated'] ?? false)];
        });
    }

    public function compensate(User $user, CounterpartyRelation $relation, array $data): array
    {
        return $this->operation($user, $relation, 'compensation', $data, function (CounterpartyRelation $relation) use ($user, $data): array {
            $positive = $relation->obligations()->where('uuid', $data['positive_uuid'])->firstOrFail();
            $negative = $relation->obligations()->where('uuid', $data['negative_uuid'])->firstOrFail();
            $event = $this->ledger->compensate($positive, $negative, $this->minor($data['amount'], $relation), $data['date'], $this->key($data, 'compensate'), $user, $data['note'] ?? null);

            return ['compensation_uuid' => $event->uuid];
        });
    }

    public function reverseCompensation(User $user, CounterpartyRelation $relation, CounterpartyLedgerEntry $entry, array $data): array
    {
        return $this->operation($user, $relation, 'reverse_compensation', [...$data, 'entry_uuid' => $entry->uuid], function (CounterpartyRelation $relation) use ($user, $entry, $data): array {
            $this->require($entry->relation_id === $relation->id, 'relation', 'Compensazione appartenente a un altro rapporto.');
            $this->ledger->reverseCompensation($entry, $data['date'] ?? CarbonImmutable::today(config('app.timezone'))->toDateString(), $this->key($data, 'reverse'), $user, $data['note'] ?? null);

            return ['compensation_uuid' => $entry->uuid];
        });
    }

    public function returnMoney(User $user, CounterpartyRelation $relation, CounterpartyPayment $payment, array $data): array
    {
        return $this->operation($user, $relation, 'return', [...$data, 'payment_uuid' => $payment->uuid], function (CounterpartyRelation $relation) use ($user, $payment, $data): array {
            $payment = $this->payment($relation, $payment);
            $this->require($payment->return_of_id === null, 'payment', 'Non puoi restituire una restituzione.');
            $minor = $this->minor($data['amount'], $relation);
            $retained = $this->projections->effectivePayment($payment) - $this->authority->returned($payment);
            $this->require($minor <= $retained, 'amount', 'La restituzione supera il denaro ancora trattenuto.');
            $release = max(0, $minor - ($retained - $this->projections->allocatedToPayment($payment)));
            $released = [];
            $active = $this->projections->activeAllocations($payment)->sortByDesc(fn ($entry): string => $entry->obligation->economic_date->toDateString().':'.$entry->obligation->uuid);
            foreach ($active as $entry) {
                if ($release === 0) {
                    break;
                }
                $part = min($release, $entry->amount_minor);
                $this->ledger->reverseAllocation($entry, $data['date'], $this->key($data, 'return-release:'.$entry->uuid), $user);
                if ($entry->amount_minor > $part) {
                    $this->ledger->allocate($payment, $entry->obligation, $entry->amount_minor - $part, $data['date'], $this->key($data, 'retain:'.$entry->uuid), $user);
                }
                $released[] = ['obligation_uuid' => $entry->obligation->uuid, 'amount_minor' => $part];
                $release -= $part;
            }
            $direction = $payment->direction === Direction::RECEIVED ? Direction::SENT : Direction::RECEIVED;
            $transaction = $this->bankTransaction($user, $relation, $direction, $minor, [...$data, 'return_of_uuid' => $payment->uuid]);
            $return = $this->ledger->recordPayment($relation, $direction, $minor, $data['date'], $transaction->account, $this->key($data, 'return'), $transaction,
                metadata: ['public_lifecycle' => true, 'kind' => 'return', 'allocation_status' => 'return_offset', 'note' => $data['note'] ?? null, 'scope' => $payment->metadata['scope'] ?? 'all', 'released_allocations' => $released, 'return_of_uuid' => $payment->uuid], actor: $user, returnOf: $payment);
            $this->transactions->recalculateAccount($transaction->account);

            return ['payment_uuid' => $return->uuid, 'return_of_uuid' => $payment->uuid];
        });
    }

    /** @return array<string, int> */
    public function allocationPlan(CounterpartyRelation $relation, Direction $direction, int $minor, ?array $custom = null, bool $allowUnallocated = false, string $scope = 'all', bool $checkCapacity = true): array
    {
        $this->require(in_array($scope, ['all', 'shared'], true), 'scope', 'Ambito non valido.');
        $eligible = $this->projections->openObligations($relation)->filter(fn (array $row): bool => ($row['direction_sign'] > 0) === ($direction === Direction::RECEIVED) && ($scope !== 'shared' || $row['source_type'] !== 'credit_debt_item'))->keyBy('uuid');
        $capacity = CurrencyMinorUnits::sum($eligible->pluck('remaining_minor'));
        if ($checkCapacity) {
            $balance = $scope === 'shared' ? $this->authority->summary($relation)['shared_minor'] : $this->projections->balance($relation);
            if (($balance > 0 && $direction === Direction::RECEIVED) || ($balance < 0 && $direction === Direction::SENT)) {
                $capacity = min($capacity, abs($balance));
            }
            $this->require($minor > 0 && $minor <= $capacity, 'amount', 'L’importo supera il saldo compatibile. Gli anticipi non sono disponibili.');
        }
        $plan = [];
        $remaining = $minor;
        if ($custom === null) {
            foreach ($eligible as $uuid => $row) {
                $part = min($remaining, $row['remaining_minor']);
                if ($part > 0) {
                    $plan[$uuid] = $part;
                    $remaining -= $part;
                }
                if ($remaining === 0) {
                    break;
                }
            }
        } else {
            foreach ($custom as $row) {
                $uuid = $row['obligation_uuid'];
                $part = CurrencyMinorUnits::fromDecimal((string) $row['amount'], $relation->currency_code);
                $this->require(! array_key_exists($uuid, $plan) && $eligible->has($uuid) && $part > 0 && $part <= $eligible[$uuid]['remaining_minor'], 'allocations', 'Obbligazione incompatibile, duplicata o importo superiore al residuo.');
                $plan[$uuid] = $part;
                $remaining = CurrencyMinorUnits::add($remaining, -$part);
            }
        }
        $this->require($remaining >= 0 && ($remaining === 0 || $allowUnallocated), 'allocations', 'L’allocazione deve coprire il pagamento, oppure lasciare una quota non allocata esplicitamente.');

        return $plan;
    }

    private function createLocked(User $user, CounterpartyRelation $relation, array $data, ?CounterpartyPayment $previous = null): CounterpartyPayment
    {
        $direction = Direction::from($data['direction']);
        $minor = $this->minor($data['amount'], $relation);
        $scope = $data['scope'] ?? 'all';
        $allow = (bool) ($data['allow_unallocated'] ?? false);
        $plan = $this->allocationPlan($relation, $direction, $minor, $data['allocations'] ?? null, $allow, $scope);
        $transaction = $this->bankTransaction($user, $relation, $direction, $minor, $data);
        $payment = $this->ledger->recordPayment($relation, $direction, $minor, $data['date'], $transaction->account, $this->key($data, 'payment'), $transaction, revisionOf: $previous,
            metadata: ['public_lifecycle' => true, 'note' => $data['note'] ?? null, 'scope' => $scope, 'allocation_status' => $allow ? 'explicit_native' : 'known_native'], actor: $user);
        foreach ($plan as $uuid => $part) {
            $this->ledger->allocate($payment, $relation->obligations()->where('uuid', $uuid)->firstOrFail(), $part, $data['date'], $this->key($data, 'allocate:'.$uuid), $user);
        }
        $this->transactions->recalculateAccount($transaction->account);

        return $payment;
    }

    private function cancelLocked(User $user, CounterpartyPayment $payment, array $data): void
    {
        $this->require($this->projections->effectivePayment($payment) > 0 && $this->authority->returned($payment) === 0, 'payment', 'Pagamento già annullato o con restituzioni attive: gestisci prima le restituzioni.');
        $date = $data['date'] ?? CarbonImmutable::today(config('app.timezone'))->toDateString();
        $transaction = Transaction::withTrashed()->whereKey($payment->transaction_id)->lockForUpdate()->firstOrFail();
        $this->require($this->accounts->editable($user)->where('accounts.id', $transaction->account_id)->exists(), 'account_uuid', 'Non puoi modificare il conto del pagamento originale.');
        $this->require($transaction->status->value === 'confirmed' && ! $transaction->trashed(), 'transaction', 'Il movimento collegato non è attivo.');
        $this->ledger->reversePayment($payment, $date, $this->key($data, 'cancel'), $user, $data['note'] ?? 'Annullamento amministrativo.');
        $this->bank->withPayment($payment, fn () => $this->shadow->withoutIntermediateEvents(fn () => $transaction->delete()));
        if ($payment->return_of_id !== null) {
            $parent = CounterpartyPayment::query()->findOrFail($payment->return_of_id);
            foreach ($payment->metadata['released_allocations'] ?? [] as $row) {
                $obligation = $payment->relation->obligations()->where('uuid', $row['obligation_uuid'])->firstOrFail();
                $this->ledger->allocate($parent, $obligation, $row['amount_minor'], $date, $this->key($data, 'restore:'.$obligation->uuid), $user);
            }
        }
        $this->transactions->recalculateAccount($transaction->account);
    }

    private function bankTransaction(User $user, CounterpartyRelation $relation, Direction $direction, int $minor, array $data): Transaction
    {
        $account = $this->accounts->editable($user)->where('accounts.uuid', $data['account_uuid'])->first();
        $this->require($account !== null, 'account_uuid', 'Seleziona un conto modificabile.');
        $account = Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
        $this->require($account->is_active && $account->currency_code === $relation->currency_code && $relation->minor_unit <= 2, 'account_uuid', 'La valuta del conto deve coincidere con quella del rapporto.');
        $decimal = CurrencyMinorUnits::toDecimal($minor, $relation->currency_code);
        $this->require($minor <= CurrencyMinorUnits::fromDecimal('999999999.00', $relation->currency_code), 'amount', 'Importo superiore al limite del movimento bancario.');

        return $this->shadow->withoutIntermediateEvents(fn (): Transaction => Transaction::query()->create([
            'user_id' => $account->user_id, 'created_by_user_id' => $user->id, 'updated_by_user_id' => $user->id, 'account_id' => $account->id,
            'transaction_date' => $data['date'], 'value_date' => $data['date'], 'direction' => $direction === Direction::RECEIVED ? 'income' : 'expense',
            'amount' => $decimal, 'currency' => $relation->currency_code, 'kind' => 'manual', 'source_type' => 'generated', 'status' => 'confirmed', 'is_transfer' => false,
            ...$this->exchange->buildForAccount($account, (float) $decimal, $data['date']),
            'description' => (isset($data['return_of_uuid']) ? ($direction === Direction::RECEIVED ? 'Restituzione da ' : 'Restituzione a ') : ($direction === Direction::RECEIVED ? 'Ricevuto da ' : 'Pagato a ')).$relation->counterparty->name, 'notes' => $data['note'] ?? null,
        ]));
    }

    private function payment(CounterpartyRelation $relation, CounterpartyPayment $payment): CounterpartyPayment
    {
        return CounterpartyPayment::query()->where('relation_id', $relation->id)->whereKey($payment->id)->lockForUpdate()->firstOrFail();
    }

    private function operation(User $user, CounterpartyRelation $relation, string $kind, array $data, Closure $action, bool $activeRequired = true): array
    {
        abort_unless(config('counterparty_ledger.public_enabled'), 404);
        abort_unless($relation->user_id === $user->id && $relation->counterparty->user_id === $user->id && $relation->counterparty->account_id === null, 404);
        $this->require(isset($data['operation_key']) && is_string($data['operation_key']) && strlen($data['operation_key']) > 0 && strlen($data['operation_key']) <= 191, 'operation_key', 'Chiave operazione obbligatoria.');
        $hash = $this->hash(['kind' => $kind, 'data' => $data, 'actor_id' => $user->id]);

        return DB::transaction(function () use ($user, $relation, $kind, $data, $hash, $action, $activeRequired): array {
            TrackedItem::query()->whereKey($relation->counterparty_id)->lockForUpdate()->firstOrFail();
            $relation = CounterpartyRelation::query()->where('user_id', $user->id)->whereKey($relation->id)->lockForUpdate()->firstOrFail();
            $existing = CounterpartyOperation::query()->where('relation_id', $relation->id)->where('operation_key', $data['operation_key'])->first();
            if ($existing !== null) {
                $this->require(hash_equals($existing->payload_hash, $hash), 'operation_key', 'Questa chiave è già stata utilizzata per un’operazione diversa.');

                return $existing->result;
            }
            if ($activeRequired) {
                $this->require($relation->activated_at !== null && $this->reconciliation->inspect($relation)['readiness'] === 'reconciled', 'relation', 'Rapporto non attivo o non riconciliato.');
            }
            $relation->obligations()->orderBy('id')->lockForUpdate()->get();
            $relation->payments()->orderBy('id')->lockForUpdate()->get();
            $accountIds = $relation->payments()->pluck('account_id');
            if (isset($data['account_uuid'])) {
                $accountIds->push(Account::query()->where('uuid', $data['account_uuid'])->value('id'));
            }
            Account::query()->whereIn('id', $accountIds->filter()->unique())->orderBy('id')->lockForUpdate()->get();
            $beforeLedger = (int) $relation->ledgerEntries()->max('id');
            $beforeAllocations = (int) $relation->allocationEntries()->max('id');
            $result = $action($relation);
            $result['ledger_entry_ids'] = $relation->ledgerEntries()->where('id', '>', $beforeLedger)->pluck('id')->all();
            $result['allocation_entry_ids'] = $relation->allocationEntries()->where('id', '>', $beforeAllocations)->pluck('id')->all();
            CounterpartyOperation::query()->create(['relation_id' => $relation->id, 'operation_key' => $data['operation_key'], 'payload_hash' => $hash, 'kind' => $kind, 'actor_id' => $user->id, 'result' => $result]);

            return $result;
        }, 3);
    }

    private function minor(string $amount, CounterpartyRelation $relation): int
    {
        try {
            $minor = CurrencyMinorUnits::fromDecimal($amount, $relation->currency_code);
        } catch (\InvalidArgumentException|\OverflowException $exception) {
            throw ValidationException::withMessages(['amount' => 'Importo non rappresentabile nella valuta selezionata.']);
        }
        $this->require($minor > 0, 'amount', 'L’importo deve essere maggiore di zero.');

        return $minor;
    }

    private function key(array $data, string $suffix): string
    {
        return hash('sha256', 'public:'.$data['operation_key'].':'.$suffix);
    }

    private function hash(array $payload): string
    {
        $sort = function (mixed $value) use (&$sort): mixed {
            if (is_float($value)) {
                throw ValidationException::withMessages(['amount' => 'Usa importi decimali esatti.']);
            }
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };

        return hash('sha256', json_encode($sort($payload), JSON_THROW_ON_ERROR));
    }

    private function require(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
