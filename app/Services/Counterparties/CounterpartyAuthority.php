<?php

namespace App\Services\Counterparties;

use App\Models\CounterpartyAllocationEntry;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyObligation;
use App\Models\CounterpartyOperation;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Support\CurrencyMinorUnits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CounterpartyAuthority
{
    public function __construct(private readonly CounterpartyProjectionService $projections) {}

    public function hasActivationColumn(): bool
    {
        return once(fn (): bool => Schema::hasColumn('counterparty_relations', 'activated_at'));
    }

    public function activeRelation(int $userId, ?int $partyId, string $currency): ?CounterpartyRelation
    {
        if ($partyId === null || ! $this->hasActivationColumn()) {
            return null;
        }

        return CounterpartyRelation::query()->where(['user_id' => $userId, 'counterparty_id' => $partyId, 'currency_code' => $currency])->whereNotNull('activated_at')->first();
    }

    public function itemObligation(CreditDebtItem $item): ?CounterpartyObligation
    {
        $relation = $this->activeRelation($item->user_id, $item->counterparty_id, $item->currency_code);

        return $relation?->obligations()->where('source_type', 'credit_debt_item')->where('source_identity', $item->uuid)->first();
    }

    public function paid(CounterpartyObligation $obligation): int
    {
        return CurrencyMinorUnits::sum($obligation->allocationEntries()->whereNotNull('payment_id')->pluck('amount_minor'));
    }

    public function compensated(CounterpartyObligation $obligation): int
    {
        return CurrencyMinorUnits::sum($obligation->allocationEntries()->whereNotNull('compensation_entry_id')->pluck('amount_minor'));
    }

    /** @param Collection<int, CounterpartyObligation> $obligations
     * @return Collection<int, array{paid: int, compensated: int}>
     */
    public function obligationAmounts(Collection $obligations): Collection
    {
        if ($obligations->isEmpty()) {
            return collect();
        }
        $allocations = CounterpartyAllocationEntry::query()->whereIn('obligation_id', $obligations->pluck('id'))->get()->groupBy('obligation_id');

        return $obligations->mapWithKeys(function ($obligation) use ($allocations): array {
            $entries = $allocations->get($obligation->id, collect());

            return [$obligation->id => ['paid' => CurrencyMinorUnits::sum($entries->whereNotNull('payment_id')->pluck('amount_minor')),
                'compensated' => CurrencyMinorUnits::sum($entries->whereNotNull('compensation_entry_id')->pluck('amount_minor'))]];
        });
    }

    public function returned(CounterpartyPayment $payment): int
    {
        if (! Schema::hasColumn('counterparty_payments', 'return_of_id')) {
            return 0;
        }

        return CurrencyMinorUnits::sum(CounterpartyPayment::query()->where('return_of_id', $payment->id)->get()->map(fn (CounterpartyPayment $return): int => $this->projections->effectivePayment($return)));
    }

    /** @param Collection<int, CounterpartyPayment> $payments
     * @return Collection<int, int>
     */
    public function returnedForPayments(Collection $payments): Collection
    {
        if ($payments->isEmpty() || ! Schema::hasColumn('counterparty_payments', 'return_of_id')) {
            return collect();
        }
        $returns = CounterpartyPayment::query()->whereIn('return_of_id', $payments->pluck('id'))->get();
        $ledger = $returns->isEmpty() ? collect() : CounterpartyLedgerEntry::query()->whereIn('payment_id', $returns->pluck('id'))->get()->groupBy('payment_id');

        return $returns->groupBy('return_of_id')->map(fn ($group): int => CurrencyMinorUnits::sum($group->map(fn ($payment): int => $payment->direction->balanceSign() * CurrencyMinorUnits::sum($ledger->get($payment->id, collect())->pluck('signed_minor')))));
    }

    /** @return array{ledger: list<int>, allocations: list<int>} */
    public function publicEntryIds(CounterpartyRelation $relation): array
    {
        $results = CounterpartyOperation::query()->where('relation_id', $relation->id)->pluck('result');

        return ['ledger' => $results->flatMap(fn (array $result): array => $result['ledger_entry_ids'] ?? [])->unique()->values()->all(),
            'allocations' => $results->flatMap(fn (array $result): array => $result['allocation_entry_ids'] ?? [])->unique()->values()->all()];
    }

    /** @return array<string, int> */
    public function summary(CounterpartyRelation $relation): array
    {
        $manual = 0;
        $shared = 0;
        $unallocated = 0;
        $obligations = $relation->obligations()->get();
        $projections = $this->projections->obligations($obligations)->keyBy('uuid');
        foreach ($obligations as $obligation) {
            $projection = $projections[$obligation->uuid];
            $minor = $projection['remaining_minor'] * $projection['direction_sign'];
            if ($obligation->source_type->value === 'credit_debt_item') {
                $manual = CurrencyMinorUnits::add($manual, $minor);
            } else {
                $shared = CurrencyMinorUnits::add($shared, $minor);
            }
        }
        $payments = $relation->payments()->get();
        $paymentTotals = $this->projections->paymentTotals($payments);
        foreach ($payments as $payment) {
            $minor = $paymentTotals[$payment->id]['unallocated_minor'] * $payment->direction->balanceSign();
            if ($payment->legacy_source_type === 'shared_expense_settlement' || ($payment->metadata['scope'] ?? null) === 'shared') {
                $shared = CurrencyMinorUnits::add($shared, $minor);
            } else {
                $unallocated = CurrencyMinorUnits::add($unallocated, $minor);
            }
        }

        return ['manual_minor' => $manual, 'shared_minor' => $shared, 'unallocated_minor' => $unallocated, 'balance_minor' => $this->projections->balance($relation)];
    }
}
