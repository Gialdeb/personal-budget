<?php

namespace App\Services\Transactions;

use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionMutationService
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(User $user, array $validated): Transaction
    {
        if ($this->isTransferPayload($validated)) {
            return $this->storeTransfer($user, $validated);
        }

        return DB::transaction(function () use ($user, $validated): Transaction {
            $account = $this->ownedAccount($user, (int) $validated['account_id']);

            $transaction = Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => (int) $validated['category_id'],
                'tracked_item_id' => $validated['tracked_item_id'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => $this->directionFromTypeKey((string) $validated['type_key']),
                'amount' => round((float) $validated['amount'], 2),
                'currency' => $account->currency,
                'description' => $validated['description'] ?: null,
                'notes' => $validated['notes'] ?: null,
                'source_type' => TransactionSourceTypeEnum::MANUAL->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'value_date' => $validated['transaction_date'],
            ]);

            $this->recalculateAffectedAccounts([$account]);

            return $transaction->fresh(['account', 'category', 'trackedItem']);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(User $user, Transaction $transaction, array $validated): Transaction
    {
        if ($this->isTransferPayload($validated) || $transaction->is_transfer) {
            return $this->updateTransferAware($user, $transaction, $validated);
        }

        return DB::transaction(function () use ($user, $transaction, $validated): Transaction {
            $originalAccountId = $transaction->account_id;
            $account = $this->ownedAccount($user, (int) $validated['account_id']);

            $transaction->fill([
                'account_id' => $account->id,
                'category_id' => (int) $validated['category_id'],
                'tracked_item_id' => $validated['tracked_item_id'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => $this->directionFromTypeKey((string) $validated['type_key']),
                'amount' => round((float) $validated['amount'], 2),
                'currency' => $account->currency,
                'description' => $validated['description'] ?: null,
                'notes' => $validated['notes'] ?: null,
                'value_date' => $validated['transaction_date'],
            ]);
            $transaction->save();

            if ($originalAccountId !== $account->id) {
                $this->recalculateAffectedAccounts([
                    $this->ownedAccount($user, (int) $originalAccountId),
                ]);
            }

            $this->recalculateAffectedAccounts([$account]);

            return $transaction->fresh(['account', 'category', 'trackedItem']);
        });
    }

    public function destroy(User $user, Transaction $transaction): void
    {
        DB::transaction(function () use ($user, $transaction): void {
            if ($transaction->is_transfer) {
                $pair = $this->resolveTransferPair($transaction);
                $accounts = [
                    $this->ownedAccount($user, (int) $pair['current']->account_id),
                ];

                if ($pair['linked'] instanceof Transaction) {
                    $accounts[] = $this->ownedAccount($user, (int) $pair['linked']->account_id);
                    $pair['linked']->delete();
                }

                $pair['current']->delete();
                $this->recalculateAffectedAccounts($accounts);

                return;
            }

            $account = $this->ownedAccount($user, (int) $transaction->account_id);

            $transaction->delete();

            $this->recalculateAffectedAccounts([$account]);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function storeTransfer(User $user, array $validated): Transaction
    {
        return DB::transaction(function () use ($user, $validated): Transaction {
            $sourceAccount = $this->ownedAccount($user, (int) $validated['account_id']);
            $destinationAccount = $this->ownedAccount($user, (int) $validated['destination_account_id']);
            $transferCategory = $this->transferCategory($user);
            $amount = round((float) $validated['amount'], 2);

            $sourceTransaction = Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $sourceAccount->id,
                'category_id' => $transferCategory->id,
                'tracked_item_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => TransactionDirectionEnum::EXPENSE->value,
                'amount' => $amount,
                'currency' => $sourceAccount->currency,
                'description' => $validated['description'] ?: null,
                'notes' => $validated['notes'] ?: null,
                'source_type' => TransactionSourceTypeEnum::MANUAL->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'value_date' => $validated['transaction_date'],
                'is_transfer' => true,
            ]);

            $destinationTransaction = Transaction::query()->create([
                'user_id' => $user->id,
                'account_id' => $destinationAccount->id,
                'category_id' => $transferCategory->id,
                'tracked_item_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => TransactionDirectionEnum::INCOME->value,
                'amount' => $amount,
                'currency' => $destinationAccount->currency,
                'description' => $validated['description'] ?: null,
                'notes' => $validated['notes'] ?: null,
                'source_type' => TransactionSourceTypeEnum::MANUAL->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'value_date' => $validated['transaction_date'],
                'is_transfer' => true,
                'related_transaction_id' => $sourceTransaction->id,
            ]);

            $sourceTransaction->forceFill([
                'related_transaction_id' => $destinationTransaction->id,
            ])->save();

            $this->recalculateAffectedAccounts([$sourceAccount, $destinationAccount]);

            return $sourceTransaction->fresh(['account', 'category', 'trackedItem', 'relatedTransaction.account']);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function updateTransferAware(User $user, Transaction $transaction, array $validated): Transaction
    {
        return DB::transaction(function () use ($user, $transaction, $validated): Transaction {
            if (! $this->isTransferPayload($validated) && $transaction->is_transfer) {
                return $this->convertTransferToStandard($user, $transaction, $validated);
            }

            $currentTransaction = $transaction;
            $linkedTransaction = null;
            $sourceTransaction = $currentTransaction;
            $destinationTransaction = null;

            if ($transaction->is_transfer) {
                $pair = $this->resolveTransferPair($transaction);
                $currentTransaction = $pair['current'];
                $linkedTransaction = $pair['linked'];

                if ($linkedTransaction instanceof Transaction) {
                    $sourceTransaction = $currentTransaction->direction === TransactionDirectionEnum::EXPENSE
                        ? $currentTransaction
                        : $linkedTransaction;
                    $destinationTransaction = $currentTransaction->direction === TransactionDirectionEnum::INCOME
                        ? $currentTransaction
                        : $linkedTransaction;
                }
            }

            $sourceAccount = $this->ownedAccount($user, (int) $validated['account_id']);
            $destinationAccount = $this->ownedAccount($user, (int) $validated['destination_account_id']);
            $transferCategory = $this->transferCategory($user);
            $amount = round((float) $validated['amount'], 2);

            $affectedAccounts = [];

            $affectedAccounts[] = $this->ownedAccount($user, (int) $sourceTransaction->account_id);

            if ($destinationTransaction instanceof Transaction) {
                $affectedAccounts[] = $this->ownedAccount($user, (int) $destinationTransaction->account_id);
            }

            $sourceTransaction->fill([
                'account_id' => $sourceAccount->id,
                'category_id' => $transferCategory->id,
                'tracked_item_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => TransactionDirectionEnum::EXPENSE->value,
                'amount' => $amount,
                'currency' => $sourceAccount->currency,
                'description' => $validated['description'] ?: null,
                'notes' => $validated['notes'] ?: null,
                'value_date' => $validated['transaction_date'],
                'is_transfer' => true,
            ]);
            $sourceTransaction->save();

            if (! ($destinationTransaction instanceof Transaction)) {
                $destinationTransaction = new Transaction([
                    'user_id' => $user->id,
                    'source_type' => TransactionSourceTypeEnum::MANUAL->value,
                    'status' => TransactionStatusEnum::CONFIRMED->value,
                ]);
            }

            $destinationTransaction->fill([
                'user_id' => $user->id,
                'account_id' => $destinationAccount->id,
                'category_id' => $transferCategory->id,
                'tracked_item_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => TransactionDirectionEnum::INCOME->value,
                'amount' => $amount,
                'currency' => $destinationAccount->currency,
                'description' => $validated['description'] ?: null,
                'notes' => $validated['notes'] ?: null,
                'value_date' => $validated['transaction_date'],
                'source_type' => TransactionSourceTypeEnum::MANUAL->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'is_transfer' => true,
                'related_transaction_id' => $sourceTransaction->id,
            ]);
            $destinationTransaction->save();

            $sourceTransaction->forceFill([
                'related_transaction_id' => $destinationTransaction->id,
            ])->save();

            $affectedAccounts[] = $sourceAccount;
            $affectedAccounts[] = $destinationAccount;

            $this->recalculateAffectedAccounts($affectedAccounts);

            return $transaction->fresh(['account', 'category', 'trackedItem', 'relatedTransaction.account']);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function convertTransferToStandard(User $user, Transaction $transaction, array $validated): Transaction
    {
        $pair = $this->resolveTransferPair($transaction);
        $currentTransaction = $pair['current'];
        $linkedTransaction = $pair['linked'];
        $account = $this->ownedAccount($user, (int) $validated['account_id']);
        $affectedAccounts = [
            $this->ownedAccount($user, (int) $currentTransaction->account_id),
            $account,
        ];

        if ($linkedTransaction instanceof Transaction) {
            $affectedAccounts[] = $this->ownedAccount($user, (int) $linkedTransaction->account_id);
            $linkedTransaction->delete();
        }

        $currentTransaction->fill([
            'account_id' => $account->id,
            'category_id' => (int) $validated['category_id'],
            'tracked_item_id' => $validated['tracked_item_id'] ?? null,
            'transaction_date' => $validated['transaction_date'],
            'direction' => $this->directionFromTypeKey((string) $validated['type_key']),
            'amount' => round((float) $validated['amount'], 2),
            'currency' => $account->currency,
            'description' => $validated['description'] ?: null,
            'notes' => $validated['notes'] ?: null,
            'value_date' => $validated['transaction_date'],
            'is_transfer' => false,
            'related_transaction_id' => null,
        ]);
        $currentTransaction->save();

        $this->recalculateAffectedAccounts($affectedAccounts);

        return $currentTransaction->fresh(['account', 'category', 'trackedItem', 'relatedTransaction.account']);
    }

    protected function recalculateAccountBalances(Account $account): void
    {
        $runningBalance = $account->opening_balance !== null
            ? (float) $account->opening_balance
            : 0.0;

        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $runningBalance += $this->signedAmount($transaction);

            $transaction->forceFill([
                'balance_after' => round($runningBalance, 2),
            ])->save();
        }

        $account->forceFill([
            'current_balance' => round($runningBalance, 2),
        ])->save();
    }

    /**
     * @param  array<int, Account>  $accounts
     */
    protected function recalculateAffectedAccounts(array $accounts): void
    {
        foreach (
            collect($accounts)->unique(fn (Account $account): int => $account->id) as $account
        ) {
            $this->recalculateAccountBalances($account);
        }
    }

    protected function ownedAccount(User $user, int $accountId): Account
    {
        return Account::query()
            ->ownedBy($user->id)
            ->findOrFail($accountId);
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

    protected function directionFromTypeKey(string $typeKey): string
    {
        return $typeKey === CategoryGroupTypeEnum::INCOME->value
            ? TransactionDirectionEnum::INCOME->value
            : TransactionDirectionEnum::EXPENSE->value;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function isTransferPayload(array $validated): bool
    {
        return ($validated['type_key'] ?? null) === CategoryGroupTypeEnum::TRANSFER->value;
    }

    protected function transferCategory(User $user): Category
    {
        $category = Category::query()
            ->ownedBy($user->id)
            ->where('group_type', CategoryGroupTypeEnum::TRANSFER->value)
            ->where('is_selectable', true)
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if (! $category instanceof Category) {
            throw ValidationException::withMessages([
                'type_key' => 'Per registrare un giroconto serve una categoria di trasferimento attiva e selezionabile.',
            ]);
        }

        return $category;
    }

    /**
     * @return array{current: Transaction, linked: Transaction|null}
     */
    protected function resolveTransferPair(Transaction $transaction): array
    {
        $transaction->loadMissing(['relatedTransaction', 'linkedTransactions']);

        $linkedTransaction = $transaction->relatedTransaction;

        if (! $linkedTransaction instanceof Transaction) {
            $linkedTransaction = $transaction->linkedTransactions()->first();
        }

        return [
            'current' => $transaction,
            'linked' => $linkedTransaction instanceof Transaction ? $linkedTransaction : null,
        ];
    }
}
