<?php

namespace App\Services\Counterparties;

use App\Enums\TransactionStatusEnum;
use App\Models\CounterpartyRelation;
use App\Models\CounterpartySourceLink;
use App\Models\CreditDebtItem;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Support\CurrencyMinorUnits;
use App\Support\MoneyAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CounterpartySourceGuard
{
    public function __construct(private readonly CounterpartyAuthority $authority, private readonly CounterpartyProjectionService $projections) {}

    public function relation(Transaction|SharedExpenseLedgerEntry|CreditDebtItem $source): ?CounterpartyRelation
    {
        if ($source instanceof CreditDebtItem) {
            return $this->authority->activeRelation($source->getRawOriginal('user_id') ?? $source->user_id, $source->getRawOriginal('counterparty_id') ?? $source->counterparty_id, $source->getRawOriginal('currency_code') ?? $source->currency_code);
        }
        $partyId = $source instanceof Transaction ? ($source->getRawOriginal('shared_expense_counterparty_id') ?? $source->shared_expense_counterparty_id) : ($source->getRawOriginal('counterparty_id') ?? $source->counterparty_id);
        $party = $partyId === null ? null : TrackedItem::query()->find($partyId);

        return $party === null ? null : $this->authority->activeRelation($party->user_id, $party->id, $source->getRawOriginal('currency') ?? $source->currency);
    }

    public function assertChange(Transaction|SharedExpenseLedgerEntry|CreditDebtItem $source, bool $deleted = false): void
    {
        $relation = $this->relation($source);
        if ($relation === null) {
            return;
        }
        DB::transaction(function () use ($source, $relation, $deleted): void {
            TrackedItem::query()->whereKey($relation->counterparty_id)->lockForUpdate()->firstOrFail();
            $relation = $relation->newQuery()->whereKey($relation->id)->lockForUpdate()->firstOrFail();
            if ($source instanceof CreditDebtItem) {
                $obligation = $relation->obligations()->where('source_type', 'credit_debt_item')->where('source_identity', $source->uuid)->first();
                if ($obligation !== null && ($source->type->value === 'credit') !== ($obligation->recognized_minor > 0)) {
                    $this->fail('Non cambiare direzione a una posizione attiva: registra una nuova obbligazione.');
                }
                $minor = $deleted ? 0 : CurrencyMinorUnits::fromDecimal((string) $source->total_amount, $relation->currency_code) * ($source->type->value === 'credit' ? 1 : -1);
                $partyId = $source->counterparty_id;
                $currency = $source->currency_code;
            } else {
                $type = $source instanceof Transaction ? 'shared_transaction_share' : 'shared_ledger_share';
                $link = CounterpartySourceLink::query()->where('source_type', $type)->where('source_identity', $source->uuid)->first();
                $obligation = $link === null ? null : $relation->obligations()->where('economic_identity', $link->economic_identity)->first();
                $partyId = $source instanceof Transaction ? $source->shared_expense_counterparty_id : $source->counterparty_id;
                $currency = $source->currency;
                $minor = $this->sourceMinor($source, $relation, $deleted);
                if ($obligation !== null) {
                    $snapshot = $this->projections->obligation($obligation);
                    if (isset($snapshot['component_snapshots'][$source->uuid])) {
                        if ($source instanceof Transaction && ($source->kind->value !== 'scheduled' || $source->isDirty('recurring_entry_occurrence_id'))) {
                            $this->fail('La sorgente appartiene a un’occorrenza regolata: non cambiarne l’identità.');
                        }
                        $minor = CurrencyMinorUnits::add($snapshot['recognized_minor'], $minor - $snapshot['component_snapshots'][$source->uuid]);
                    }
                }
            }
            if ($partyId !== $relation->counterparty_id || $currency !== $relation->currency_code) {
                $this->fail('Controparte e valuta di un rapporto attivo non possono essere spostate con una modifica generica.');
            }
            if ($obligation !== null && ($this->projections->allocatedToObligation($obligation) > abs($minor) || ($this->projections->allocatedToObligation($obligation) > 0 && ($minor > 0) !== ($this->projections->recognized($obligation) > 0)))) {
                $this->fail('Questa quota è già regolata. Annulla o rialloca i pagamenti prima di ridurla o cambiare anticipante.');
            }
        });
    }

    public function assertRefund(Transaction $refund): void
    {
        if ($refund->refunded_transaction_id === null || $refund->status !== TransactionStatusEnum::CONFIRMED || $refund->deleted_at !== null) {
            return;
        }
        $original = Transaction::query()->find($refund->refunded_transaction_id);
        if ($original === null || $original->shared_expense_counterparty_id === null) {
            return;
        }
        $relation = $this->relation($original);
        if ($relation === null) {
            return;
        }
        DB::transaction(function () use ($original, $refund, $relation): void {
            TrackedItem::query()->whereKey($relation->counterparty_id)->lockForUpdate()->firstOrFail();
            $relation->newQuery()->whereKey($relation->id)->lockForUpdate()->firstOrFail();
            $type = 'shared_transaction_share';
            $link = CounterpartySourceLink::query()->where('source_type', $type)->where('source_identity', $original->uuid)->first();
            $obligation = $link === null ? null : $relation->obligations()->where('economic_identity', $link->economic_identity)->first();
            if ($obligation === null) {
                return;
            }
            $total = MoneyAmount::cents((string) $original->amount);
            $share = ($original->shared_expense_paid_by ?? 'self') === 'self' ? ($original->shared_expense_counterparty_share_cents ?? $total - intdiv($total, 2)) : ($original->shared_expense_my_share_cents ?? intdiv($total, 2));
            $new = CurrencyMinorUnits::fromDecimal(MoneyAmount::decimal(MoneyAmount::proportional($share, max(0, $total - MoneyAmount::cents((string) $refund->amount)), $total)), $relation->currency_code);
            $snapshot = $this->projections->obligation($obligation);
            if (isset($snapshot['component_snapshots'][$original->uuid])) {
                $new = $snapshot['recognized_minor'] + $new - $snapshot['component_snapshots'][$original->uuid];
            }
            if ($this->projections->allocatedToObligation($obligation) > abs($new)) {
                $this->fail('Il refund ridurrebbe una quota già regolata. Rialloca prima il pagamento della controparte.');
            }
        });
    }

    private function sourceMinor(Transaction|SharedExpenseLedgerEntry $source, CounterpartyRelation $relation, bool $deleted): int
    {
        if ($deleted) {
            return 0;
        }
        if ($source instanceof SharedExpenseLedgerEntry) {
            $cents = ! $source->isRecorded() ? 0 : ($source->paid_by === 'self' ? $source->counterparty_share_cents : -$source->my_share_cents);
        } else {
            if ($source->is_transfer) {
                $this->fail('Una spesa del rapporto attivo non può diventare un trasferimento generico.');
            }
            $total = MoneyAmount::cents((string) $source->amount);
            $share = ($source->shared_expense_paid_by ?? 'self') === 'self' ? ($source->shared_expense_counterparty_share_cents ?? $total - intdiv($total, 2)) : -($source->shared_expense_my_share_cents ?? intdiv($total, 2));
            $refund = $source->refundTransaction()->first();
            $active = $source->status !== TransactionStatusEnum::CONFIRMED ? 0 : max(0, $total - ($refund?->status === TransactionStatusEnum::CONFIRMED ? MoneyAmount::cents((string) $refund->amount) : 0));
            $cents = MoneyAmount::proportional($share, $active, $total);
        }

        return CurrencyMinorUnits::fromDecimal(MoneyAmount::decimal($cents), $relation->currency_code);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['counterparty' => $message]);
    }
}
