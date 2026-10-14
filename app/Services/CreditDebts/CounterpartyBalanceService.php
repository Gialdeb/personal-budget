<?php

namespace App\Services\CreditDebts;

use App\Enums\CreditDebtTypeEnum;
use App\Models\CreditDebtItem;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Counterparties\CounterpartyAuthority;
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
        $items = CreditDebtItem::query()->forUser($user)->where('counterparty_id', $counterparty->id)
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
        $counterparties ??= TrackedItem::query()->sharedExpenseHistory($user->id)->orderBy('name')->get();
        $manualCurrencies = CreditDebtItem::query()->forUser($user)->whereNotNull('counterparty_id')
            ->with('payments')->get()->groupBy('counterparty_id');
        $transactionCurrencies = Transaction::query()->whereIn('account_id', $this->accounts->ids($user))
            ->whereNotNull('shared_expense_counterparty_id')->get(['shared_expense_counterparty_id', 'currency'])->groupBy('shared_expense_counterparty_id');
        $ledgerCurrencies = SharedExpenseLedgerEntry::query()->where('user_id', $user->id)
            ->recorded()->get(['counterparty_id', 'currency'])->groupBy('counterparty_id');

        return $counterparties->mapWithKeys(function (TrackedItem $counterparty) use ($user, $manualCurrencies, $transactionCurrencies, $ledgerCurrencies): array {
            $currencies = collect(['EUR'])
                ->concat(($manualCurrencies[$counterparty->id] ?? collect())->pluck('currency_code'))
                ->concat(($transactionCurrencies[$counterparty->id] ?? collect())->pluck('currency'))
                ->concat(($ledgerCurrencies[$counterparty->id] ?? collect())->pluck('currency'))
                ->unique()->sort()->values();

            return [$counterparty->uuid => $currencies->map(function (string $currency) use ($user, $counterparty): array {
                return $this->balance($user, $counterparty, $currency);
            })->all()];
        })->all();
    }
}
