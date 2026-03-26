<?php

namespace App\Services\Transactions;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\Account;
use App\Models\Transaction;

class BalanceAdjustmentService
{
    /**
     * @return array{
     *     theoretical_balance: float,
     *     desired_balance: float,
     *     adjustment_amount: float,
     *     absolute_amount: float,
     *     direction: string
     * }
     */
    public function preview(Account $account, string $transactionDate, float $desiredBalance): array
    {
        $theoreticalBalance = $this->theoreticalBalanceAt($account, $transactionDate);
        $adjustmentAmount = round($desiredBalance - $theoreticalBalance, 2);

        return [
            'theoretical_balance' => round($theoreticalBalance, 2),
            'desired_balance' => round($desiredBalance, 2),
            'adjustment_amount' => $adjustmentAmount,
            'absolute_amount' => abs($adjustmentAmount),
            'direction' => $adjustmentAmount >= 0
                ? TransactionDirectionEnum::INCOME->value
                : TransactionDirectionEnum::EXPENSE->value,
        ];
    }

    public function theoreticalBalanceAt(Account $account, string $transactionDate): float
    {
        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', '<=', $transactionDate)
            ->orderBy('transaction_date')
            ->orderByRaw(
                'case when kind = ? then 0 else 1 end asc',
                [TransactionKindEnum::OPENING_BALANCE->value]
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['direction', 'amount', 'kind']);

        $runningBalance = $transactions
            ->contains(fn (Transaction $transaction): bool => $transaction->kind === TransactionKindEnum::OPENING_BALANCE)
            ? 0.0
            : (float) ($account->opening_balance ?? 0.0);

        foreach ($transactions as $transaction) {
            $runningBalance += $this->signedAmount($transaction);
        }

        return round($runningBalance, 2);
    }

    protected function signedAmount(Transaction $transaction): float
    {
        $amount = (float) $transaction->amount;

        return match ($transaction->direction) {
            TransactionDirectionEnum::INCOME => $amount,
            TransactionDirectionEnum::EXPENSE => $amount * -1,
            default => 0.0,
        };
    }
}
