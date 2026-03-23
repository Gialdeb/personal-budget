<?php

namespace App\Services\Accounts;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transactions\TransactionMutationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AccountOpeningBalanceService
{
    public function __construct(
        protected TransactionMutationService $transactionMutationService
    ) {}

    public function sync(
        Account $account,
        ?float $openingBalanceAmount,
        string $openingBalanceDirection,
        ?string $openingBalanceDate,
        User $user
    ): void {
        DB::transaction(function () use ($account, $openingBalanceAmount, $openingBalanceDirection, $openingBalanceDate, $user): void {
            $account->refresh();

            $openingTransaction = Transaction::query()
                ->where('account_id', $account->id)
                ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
                ->orderBy('id')
                ->first();

            Transaction::query()
                ->where('account_id', $account->id)
                ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
                ->when(
                    $openingTransaction !== null,
                    fn ($query) => $query->where('id', '!=', $openingTransaction->id)
                )
                ->delete();

            $amount = round(max(0, (float) ($openingBalanceAmount ?? 0)), 2);
            $direction = $openingBalanceDirection === 'negative'
                ? TransactionDirectionEnum::EXPENSE
                : TransactionDirectionEnum::INCOME;
            $signedOpeningBalance = $direction === TransactionDirectionEnum::EXPENSE
                ? $amount * -1
                : $amount;

            $account->forceFill([
                'opening_balance' => round($signedOpeningBalance, 2),
                'opening_balance_date' => $amount > 0
                    ? $this->resolveOpeningDate($openingBalanceDate, $openingTransaction, $account, $user)
                    : null,
            ])->save();

            if ($amount <= 0) {
                $openingTransaction?->delete();
                $this->transactionMutationService->recalculateAccount($account->fresh());

                return;
            }

            $openingDate = $account->opening_balance_date?->toDateString()
                ?? $this->resolveOpeningDate($openingBalanceDate, $openingTransaction, $account, $user);

            $payload = [
                'user_id' => $account->user_id,
                'account_id' => $account->id,
                'transaction_date' => $openingDate,
                'value_date' => $openingDate,
                'direction' => $direction->value,
                'kind' => TransactionKindEnum::OPENING_BALANCE->value,
                'amount' => $amount,
                'currency' => $account->currency_code,
                'description' => null,
                'notes' => null,
                'source_type' => TransactionSourceTypeEnum::GENERATED->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'category_id' => null,
                'tracked_item_id' => null,
                'is_transfer' => false,
                'related_transaction_id' => null,
            ];

            if ($openingTransaction instanceof Transaction) {
                $openingTransaction->fill($payload);
                $openingTransaction->save();
            } else {
                Transaction::query()->create($payload);
            }

            $this->transactionMutationService->recalculateAccount($account->fresh());
        });
    }

    protected function resolveOpeningDate(
        ?string $openingBalanceDate,
        ?Transaction $openingTransaction,
        Account $account,
        User $user
    ): string {
        if (is_string($openingBalanceDate) && $openingBalanceDate !== '') {
            return CarbonImmutable::parse($openingBalanceDate)->toDateString();
        }

        if ($openingTransaction?->transaction_date !== null) {
            return $openingTransaction->transaction_date->toDateString();
        }

        if ($account->opening_balance_date !== null) {
            return $account->opening_balance_date->toDateString();
        }

        return CarbonImmutable::create(
            $user->settings?->active_year ?? now()->year,
            1,
            1,
        )->toDateString();
    }
}
