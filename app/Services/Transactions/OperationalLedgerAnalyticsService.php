<?php

namespace App\Services\Transactions;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalLedgerAnalyticsService
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
    ) {}

    /**
     * @return Collection<int, Transaction>
     */
    public function transactionsForPeriod(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?string $accountUuid = null,
        bool $includeInternalTransfers = false,
    ): Collection {
        $accountIds = $this->accessibleAccountsQuery->ids($user, 'all', $accountUuid);
        $ownerIds = $this->accessibleAccountsQuery->ownerIds($user, 'all', $accountUuid);

        $query = Transaction::query()
            ->whereIn('account_id', $accountIds !== [] ? $accountIds : [0])
            ->where('status', TransactionStatusEnum::CONFIRMED->value)
            ->where('kind', '!=', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereBetween('transaction_date', [
                $start->toDateString(),
                $end->toDateString(),
            ]);

        if (! $includeInternalTransfers) {
            $query->where('is_transfer', false);
        }

        $this->applyTrackedItemOwnershipConstraintForOwners(
            $query,
            'tracked_item_id',
            $ownerIds,
        );

        return $query->get([
            'uuid',
            'account_id',
            'category_id',
            'transaction_date',
            'direction',
            'kind',
            'amount',
            'currency',
            'currency_code',
            'base_currency_code',
            'converted_base_amount',
            'description',
            'is_transfer',
        ]);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array{
     *     income: float,
     *     expense: float,
     *     net: float,
     *     resolved_count: int,
     *     unresolved_count: int
     * }
     */
    public function summarize(Collection $transactions, string $baseCurrency): array
    {
        $totals = [
            'income' => 0.0,
            'expense' => 0.0,
            'resolved_count' => 0,
            'unresolved_count' => 0,
        ];

        foreach ($transactions as $transaction) {
            if ($transaction->kind === TransactionKindEnum::OPENING_BALANCE) {
                continue;
            }

            $amount = $this->resolveAggregateAmountForTransaction(
                $transaction,
                $baseCurrency,
            );

            if ($amount === null) {
                $totals['unresolved_count']++;

                continue;
            }

            $totals['resolved_count']++;
            $this->applyTransactionToTotals($totals, $transaction, $amount);
        }

        $income = round((float) $totals['income'], 2);
        $expense = round((float) $totals['expense'], 2);

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => round($income - $expense, 2),
            'resolved_count' => (int) $totals['resolved_count'],
            'unresolved_count' => (int) $totals['unresolved_count'],
        ];
    }

    public function resolveAggregateAmountForTransaction(
        Transaction $transaction,
        string $baseCurrency,
    ): ?float {
        $normalizedBaseCurrency = $this->normalizeCurrencyCode($baseCurrency, 'EUR');
        $transactionBaseCurrency = $this->normalizeCurrencyCode(
            $transaction->base_currency_code,
            $normalizedBaseCurrency,
        );

        if (
            $transaction->converted_base_amount !== null
            && $transactionBaseCurrency === $normalizedBaseCurrency
        ) {
            return round(abs((float) $transaction->converted_base_amount), 2);
        }

        $transactionCurrency = $this->normalizeCurrencyCode(
            $transaction->currency_code ?: $transaction->currency,
            $normalizedBaseCurrency,
        );

        if ($transactionCurrency === $normalizedBaseCurrency) {
            return round(abs((float) $transaction->amount), 2);
        }

        return null;
    }

    /**
     * @param  array{income: float, expense: float, resolved_count: int, unresolved_count: int}  $totals
     */
    public function applyTransactionToTotals(array &$totals, Transaction $transaction, float $amount): void
    {
        if ($transaction->kind === TransactionKindEnum::REFUND) {
            if ($transaction->direction === TransactionDirectionEnum::INCOME) {
                $totals['expense'] -= $amount;

                return;
            }

            $totals['income'] -= $amount;

            return;
        }

        if ($transaction->direction === TransactionDirectionEnum::INCOME) {
            $totals['income'] += $amount;

            return;
        }

        $totals['expense'] += $amount;
    }

    protected function normalizeCurrencyCode(?string $currencyCode, string $fallback): string
    {
        $normalizedCurrencyCode = strtoupper(trim((string) $currencyCode));

        return $normalizedCurrencyCode !== ''
            ? $normalizedCurrencyCode
            : strtoupper(trim($fallback));
    }

    protected function applyTrackedItemOwnershipConstraintForOwners(
        Builder $query,
        string $qualifiedColumn,
        array $ownerIds,
        string $relation = 'trackedItem',
    ): Builder {
        return $query->where(function (Builder $trackedItemQuery) use (
            $qualifiedColumn,
            $relation,
            $ownerIds,
        ): void {
            $trackedItemQuery->whereNull($qualifiedColumn)
                ->orWhereHas($relation, function (Builder $ownedTrackedItemQuery) use ($ownerIds): void {
                    $ownedTrackedItemQuery->whereIn('user_id', $ownerIds !== [] ? $ownerIds : [0]);
                });
        });
    }
}
