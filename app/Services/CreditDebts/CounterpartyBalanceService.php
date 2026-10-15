<?php

namespace App\Services\CreditDebts;

use App\Enums\CreditDebtTypeEnum;
use App\Models\CounterpartyRelation;
use App\Models\CreditDebtItem;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Counterparties\CounterpartyAuthority;
use App\Services\SharedExpenses\SharedExpenseAllocation;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use App\Support\CurrencyMinorUnits;
use App\Support\MoneyAmount;
use Illuminate\Support\Collection;

class CounterpartyBalanceService
{
    public function __construct(
        private readonly SharedExpenseSettlementService $sharedBalances,
        private readonly AccessibleAccountsQuery $accounts,
        private readonly CounterpartyAuthority $authority,
    ) {}

    /** @return array{currency: string, manual_cents: int, shared_cents: int, total_cents: int} */
    public function balance(User $user, TrackedItem $counterparty, string $currency): array
    {
        $relation = $this->authority->activeRelation($user->id, $counterparty->id, $currency);
        if ($relation === null) {
            return $this->legacyBalance($user, $counterparty, $currency);
        }
        $summary = $this->authority->summary($relation);
        $cents = fn (int $minor): int => MoneyAmount::cents(CurrencyMinorUnits::toDecimal($minor, $currency));

        return ['currency' => $currency, 'manual_cents' => $cents($summary['manual_minor']), 'shared_cents' => $cents($summary['shared_minor']), 'unallocated_cents' => $cents($summary['unallocated_minor']), 'total_cents' => $cents($summary['balance_minor'])];
    }

    public function legacyBalance(User $user, TrackedItem $counterparty, string $currency): array
    {
        abort_unless($counterparty->user_id === $user->id && $counterparty->account_id === null, 404);
        $items = CreditDebtItem::query()->forUser($user)->confirmedEconomicIdentity()->where('counterparty_id', $counterparty->id)
            ->where('currency_code', $currency)->with('payments')->get();
        $manual = $items->sum(fn (CreditDebtItem $item): int => $item->legacyRemainingCents() * ($item->type === CreditDebtTypeEnum::CREDIT ? 1 : -1));
        $shared = $this->sharedBalances->legacyBalanceCents($user, $counterparty, $currency);

        return ['currency' => $currency, 'manual_cents' => $manual, 'shared_cents' => $shared, 'total_cents' => $manual + $shared];
    }

    /**
     * @param  Collection<int, TrackedItem>|null  $counterparties
     * @return array<string, list<array{currency: string, manual_cents: int, shared_cents: int, total_cents: int}>>
     */
    public function forUser(User $user, ?Collection $counterparties = null): array
    {
        $counterparties ??= TrackedItem::query()->economicRelationshipHistory($user->id)->orderBy('name')->get();
        $manualCurrencies = CreditDebtItem::query()->forUser($user)->confirmedEconomicIdentity()
            ->with('payments')->get()->groupBy('counterparty_id');
        $transactionCurrencies = Transaction::query()->whereIn('account_id', $this->accounts->ids($user))
            ->whereIn('shared_expense_counterparty_id', $counterparties->pluck('id'))->with('refundTransaction')->get()->groupBy('shared_expense_counterparty_id');
        $ledgerCurrencies = SharedExpenseLedgerEntry::query()->where('user_id', $user->id)
            ->recorded()->whereIn('counterparty_id', $counterparties->pluck('id'))->get()->groupBy('counterparty_id');
        $settlements = SharedExpenseSettlement::query()->where('user_id', $user->id)
            ->whereIn('counterparty_id', $counterparties->pluck('id'))->with(['transaction.refundTransaction', 'transaction.account'])->get()->groupBy('counterparty_id');
        $activeRelations = $this->authority->hasActivationColumn()
            ? CounterpartyRelation::query()->where('user_id', $user->id)->whereNotNull('activated_at')->get()->keyBy(fn ($relation): string => $relation->counterparty_id.':'.$relation->currency_code)
            : collect();
        $allocation = app(SharedExpenseAllocation::class);

        return $counterparties->mapWithKeys(function (TrackedItem $counterparty) use ($user, $manualCurrencies, $transactionCurrencies, $ledgerCurrencies, $settlements, $activeRelations, $allocation): array {
            $currencies = collect(['EUR'])
                ->concat(($manualCurrencies[$counterparty->id] ?? collect())->pluck('currency_code'))
                ->concat(($transactionCurrencies[$counterparty->id] ?? collect())->pluck('currency'))
                ->concat(($ledgerCurrencies[$counterparty->id] ?? collect())->pluck('currency'))
                ->concat($activeRelations->where('counterparty_id', $counterparty->id)->pluck('currency_code'))
                ->concat(($settlements[$counterparty->id] ?? collect())->map(fn ($settlement) => $settlement->transaction?->currency)->filter())
                ->unique()->sort()->values();

            return [$counterparty->uuid => $currencies->map(function (string $currency) use ($user, $counterparty, $manualCurrencies, $transactionCurrencies, $ledgerCurrencies, $settlements, $activeRelations, $allocation): array {
                abort_unless($counterparty->user_id === $user->id && $counterparty->account_id === null, 404);
                $relation = $activeRelations->get($counterparty->id.':'.$currency);
                if ($relation !== null) {
                    $summary = $this->authority->summary($relation);
                    $cents = fn (int $minor): int => MoneyAmount::cents(CurrencyMinorUnits::toDecimal($minor, $currency));

                    return ['currency' => $currency, 'manual_cents' => $cents($summary['manual_minor']), 'shared_cents' => $cents($summary['shared_minor']), 'unallocated_cents' => $cents($summary['unallocated_minor']), 'total_cents' => $cents($summary['balance_minor'])];
                }
                $manual = ($manualCurrencies[$counterparty->id] ?? collect())->where('currency_code', $currency)
                    ->sum(fn (CreditDebtItem $item): int => $item->legacyRemainingCents() * ($item->type === CreditDebtTypeEnum::CREDIT ? 1 : -1));
                $shared = ($transactionCurrencies[$counterparty->id] ?? collect())->where('currency', $currency)->sum(function (Transaction $transaction) use ($allocation): int {
                    $total = $allocation->toCents((string) $transaction->amount);
                    $effect = $allocation->balanceEffectCents([
                        'paid_by' => $transaction->shared_expense_paid_by ?? SharedExpenseAllocation::PAID_BY_SELF,
                        'my_share_cents' => $transaction->shared_expense_my_share_cents ?? intdiv($total, 2),
                        'counterparty_share_cents' => $transaction->shared_expense_counterparty_share_cents ?? ($total - intdiv($total, 2)),
                    ]);

                    return $total > 0 ? MoneyAmount::proportional($effect, $this->sharedBalances->activeTransactionCents($transaction), $total) : 0;
                });
                $shared += ($ledgerCurrencies[$counterparty->id] ?? collect())->where('currency', $currency)->sum(fn ($entry): int => $allocation->balanceEffectCents([
                    'paid_by' => $entry->paid_by, 'my_share_cents' => $entry->my_share_cents, 'counterparty_share_cents' => $entry->counterparty_share_cents,
                ]));
                $shared += ($settlements[$counterparty->id] ?? collect())->filter(fn ($settlement): bool => $settlement->transaction?->currency === $currency)
                    ->sum(fn ($settlement): int => $this->sharedBalances->settlementEffectCents($settlement));

                return ['currency' => $currency, 'manual_cents' => $manual, 'shared_cents' => $shared, 'total_cents' => $manual + $shared];
            })->all()];
        })->all();
    }
}
