<?php

namespace App\Services\Transactions;

use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Accounts\AccountBalanceConstraintService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionMutationService
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(User $user, array $validated): Transaction
    {
        if ($this->isTransferPayload($validated)) {
            return $this->storeTransfer($user, $validated);
        }

        return DB::transaction(function () use ($user, $validated): Transaction {
            $account = $this->accessibleAccount($user, (int) $validated['account_id'], true);
            $amount = round((float) $validated['amount'], 2);
            $direction = $this->directionFromTypeKey((string) $validated['type_key']);

            $this->ensureNoConcurrentDuplicate(
                accountId: $account->id,
                transactionDate: (string) $validated['transaction_date'],
                direction: $direction,
                amount: $amount,
                description: $validated['description'] ?? null,
                isTransfer: false,
            );

            $transaction = Transaction::query()->create([
                'user_id' => $account->user_id,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => (int) $validated['category_id'],
                'tracked_item_id' => $validated['tracked_item_id'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => $direction,
                'kind' => TransactionKindEnum::MANUAL->value,
                'amount' => $amount,
                'currency' => $account->currency,
                'description' => $validated['description'] ?: null,
                'notes' => $validated['notes'] ?: null,
                'source_type' => TransactionSourceTypeEnum::MANUAL->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'value_date' => $validated['transaction_date'],
            ]);

            $this->recalculateAffectedAccounts([$account]);

            return $transaction->fresh(['account', 'category', 'trackedItem', 'createdByUser', 'updatedByUser']);
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
            $account = $this->accessibleAccount($user, (int) $validated['account_id'], true);
            $amount = round((float) $validated['amount'], 2);
            $direction = $this->directionFromTypeKey((string) $validated['type_key']);

            $this->ensureNoConcurrentDuplicate(
                accountId: $account->id,
                transactionDate: (string) $validated['transaction_date'],
                direction: $direction,
                amount: $amount,
                description: $validated['description'] ?? null,
                isTransfer: false,
                ignoreTransactionId: $transaction->id,
            );

            $transaction->fill([
                'user_id' => $account->user_id,
                'updated_by_user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => (int) $validated['category_id'],
                'tracked_item_id' => $validated['tracked_item_id'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => $direction,
                'kind' => TransactionKindEnum::MANUAL->value,
                'amount' => $amount,
                'currency' => $account->currency,
                'description' => $validated['description'] ?: null,
                'notes' => $validated['notes'] ?: null,
                'value_date' => $validated['transaction_date'],
            ]);
            $transaction->save();

            if ($originalAccountId !== $account->id) {
                $this->recalculateAffectedAccounts([
                    $this->accessibleAccount($user, (int) $originalAccountId, true),
                ]);
            }

            $this->recalculateAffectedAccounts([$account]);

            return $transaction->fresh(['account', 'category', 'trackedItem', 'createdByUser', 'updatedByUser']);
        });
    }

    public function destroy(User $user, Transaction $transaction): void
    {
        DB::transaction(function () use ($user, $transaction): void {
            $this->ensureDeletionAllowed($transaction);

            if ($transaction->is_transfer) {
                $pair = $this->resolveTransferPair($transaction);
                $accounts = [
                    $this->accessibleAccount($user, (int) $pair['current']->account_id, true),
                ];
                $pair['current']->forceFill([
                    'updated_by_user_id' => $user->id,
                ])->save();

                if ($pair['linked'] instanceof Transaction) {
                    $accounts[] = $this->accessibleAccount($user, (int) $pair['linked']->account_id, true);
                    $pair['linked']->forceFill([
                        'updated_by_user_id' => $user->id,
                    ])->save();
                    $pair['linked']->delete();
                }

                $pair['current']->delete();
                $this->recalculateAffectedAccounts($accounts);

                return;
            }

            $account = $this->accessibleAccount($user, (int) $transaction->account_id, true);
            $transaction->forceFill([
                'updated_by_user_id' => $user->id,
            ])->save();

            $transaction->delete();

            $this->recalculateAffectedAccounts([$account]);
        });
    }

    public function restore(User $user, Transaction $transaction): void
    {
        DB::transaction(function () use ($user, $transaction): void {
            $this->ensureRestoreAllowed($transaction);

            if ($transaction->is_transfer) {
                $pair = $this->resolveTransferPair($transaction);
                $accounts = [
                    $this->accessibleAccount($user, (int) $pair['current']->account_id, true),
                ];

                $pair['current']->forceFill([
                    'updated_by_user_id' => $user->id,
                ]);
                $pair['current']->restore();

                if ($pair['linked'] instanceof Transaction) {
                    $accounts[] = $this->accessibleAccount($user, (int) $pair['linked']->account_id, true);
                    $pair['linked']->forceFill([
                        'updated_by_user_id' => $user->id,
                    ]);
                    $pair['linked']->restore();
                }

                $this->recalculateAffectedAccounts($accounts);

                return;
            }

            $account = $this->accessibleAccount($user, (int) $transaction->account_id, true);

            $transaction->forceFill([
                'updated_by_user_id' => $user->id,
            ]);
            $transaction->restore();

            $this->recalculateAffectedAccounts([$account]);
        });
    }

    public function forceDelete(User $user, Transaction $transaction): void
    {
        DB::transaction(function () use ($user, $transaction): void {
            $this->ensureForceDeleteAllowed($transaction);

            if ($transaction->is_transfer) {
                $pair = $this->resolveTransferPair($transaction);
                $accounts = [
                    $this->accessibleAccount($user, (int) $pair['current']->account_id, true),
                ];

                if ($pair['linked'] instanceof Transaction) {
                    $accounts[] = $this->accessibleAccount($user, (int) $pair['linked']->account_id, true);
                    $pair['linked']->forceDelete();
                }

                $pair['current']->forceDelete();
                $this->recalculateAffectedAccounts($accounts);

                return;
            }

            $account = $this->accessibleAccount($user, (int) $transaction->account_id, true);

            $transaction->forceDelete();

            $this->recalculateAffectedAccounts([$account]);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function storeTransfer(User $user, array $validated): Transaction
    {
        return DB::transaction(function () use ($user, $validated): Transaction {
            $sourceAccount = $this->accessibleAccount($user, (int) $validated['account_id'], true);
            $destinationAccount = $this->accessibleAccount($user, (int) $validated['destination_account_id'], true);
            $transferCategory = $this->transferCategory($sourceAccount->user_id);
            $amount = round((float) $validated['amount'], 2);

            $this->ensureNoConcurrentDuplicate(
                accountId: $sourceAccount->id,
                transactionDate: (string) $validated['transaction_date'],
                direction: TransactionDirectionEnum::EXPENSE->value,
                amount: $amount,
                description: $validated['description'] ?? null,
                isTransfer: true,
            );
            $this->ensureNoConcurrentDuplicate(
                accountId: $destinationAccount->id,
                transactionDate: (string) $validated['transaction_date'],
                direction: TransactionDirectionEnum::INCOME->value,
                amount: $amount,
                description: $validated['description'] ?? null,
                isTransfer: true,
            );

            $sourceTransaction = Transaction::query()->create([
                'user_id' => $sourceAccount->user_id,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
                'account_id' => $sourceAccount->id,
                'category_id' => $transferCategory->id,
                'tracked_item_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => TransactionDirectionEnum::EXPENSE->value,
                'kind' => TransactionKindEnum::MANUAL->value,
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
                'user_id' => $destinationAccount->user_id,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
                'account_id' => $destinationAccount->id,
                'category_id' => $transferCategory->id,
                'tracked_item_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => TransactionDirectionEnum::INCOME->value,
                'kind' => TransactionKindEnum::MANUAL->value,
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

            return $sourceTransaction->fresh(['account', 'category', 'trackedItem', 'relatedTransaction.account', 'createdByUser', 'updatedByUser']);
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

            $sourceAccount = $this->accessibleAccount($user, (int) $validated['account_id'], true);
            $destinationAccount = $this->accessibleAccount($user, (int) $validated['destination_account_id'], true);
            $transferCategory = $this->transferCategory($sourceAccount->user_id);
            $amount = round((float) $validated['amount'], 2);

            $affectedAccounts = [];

            $this->ensureNoConcurrentDuplicate(
                accountId: $sourceAccount->id,
                transactionDate: (string) $validated['transaction_date'],
                direction: TransactionDirectionEnum::EXPENSE->value,
                amount: $amount,
                description: $validated['description'] ?? null,
                isTransfer: true,
                ignoreTransactionId: $sourceTransaction->id,
            );

            $affectedAccounts[] = $this->accessibleAccount($user, (int) $sourceTransaction->account_id, true);

            if ($destinationTransaction instanceof Transaction) {
                $this->ensureNoConcurrentDuplicate(
                    accountId: $destinationAccount->id,
                    transactionDate: (string) $validated['transaction_date'],
                    direction: TransactionDirectionEnum::INCOME->value,
                    amount: $amount,
                    description: $validated['description'] ?? null,
                    isTransfer: true,
                    ignoreTransactionId: $destinationTransaction->id,
                );

                $affectedAccounts[] = $this->accessibleAccount($user, (int) $destinationTransaction->account_id, true);
            }

            $sourceTransaction->fill([
                'user_id' => $sourceAccount->user_id,
                'updated_by_user_id' => $user->id,
                'account_id' => $sourceAccount->id,
                'category_id' => $transferCategory->id,
                'tracked_item_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => TransactionDirectionEnum::EXPENSE->value,
                'kind' => TransactionKindEnum::MANUAL->value,
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
                    'created_by_user_id' => $user->id,
                    'kind' => TransactionKindEnum::MANUAL->value,
                    'source_type' => TransactionSourceTypeEnum::MANUAL->value,
                    'status' => TransactionStatusEnum::CONFIRMED->value,
                ]);
            }

            $destinationTransaction->fill([
                'user_id' => $destinationAccount->user_id,
                'updated_by_user_id' => $user->id,
                'created_by_user_id' => $destinationTransaction->exists
                    ? $destinationTransaction->created_by_user_id
                    : $user->id,
                'account_id' => $destinationAccount->id,
                'category_id' => $transferCategory->id,
                'tracked_item_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => TransactionDirectionEnum::INCOME->value,
                'kind' => TransactionKindEnum::MANUAL->value,
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

            return $transaction->fresh(['account', 'category', 'trackedItem', 'relatedTransaction.account', 'createdByUser', 'updatedByUser']);
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
        $account = $this->accessibleAccount($user, (int) $validated['account_id'], true);
        $amount = round((float) $validated['amount'], 2);
        $direction = $this->directionFromTypeKey((string) $validated['type_key']);

        $this->ensureNoConcurrentDuplicate(
            accountId: $account->id,
            transactionDate: (string) $validated['transaction_date'],
            direction: $direction,
            amount: $amount,
            description: $validated['description'] ?? null,
            isTransfer: false,
            ignoreTransactionId: $currentTransaction->id,
        );

        $affectedAccounts = [
            $this->accessibleAccount($user, (int) $currentTransaction->account_id, true),
            $account,
        ];

        if ($linkedTransaction instanceof Transaction) {
            $affectedAccounts[] = $this->accessibleAccount($user, (int) $linkedTransaction->account_id, true);
            $linkedTransaction->forceFill([
                'updated_by_user_id' => $user->id,
            ])->save();
            $linkedTransaction->delete();
        }

        $currentTransaction->fill([
            'user_id' => $account->user_id,
            'updated_by_user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => (int) $validated['category_id'],
            'tracked_item_id' => $validated['tracked_item_id'] ?? null,
            'transaction_date' => $validated['transaction_date'],
            'direction' => $direction,
            'kind' => TransactionKindEnum::MANUAL->value,
            'amount' => $amount,
            'currency' => $account->currency,
            'description' => $validated['description'] ?: null,
            'notes' => $validated['notes'] ?: null,
            'value_date' => $validated['transaction_date'],
            'is_transfer' => false,
            'related_transaction_id' => null,
        ]);
        $currentTransaction->save();

        $this->recalculateAffectedAccounts($affectedAccounts);

        return $currentTransaction->fresh(['account', 'category', 'trackedItem', 'relatedTransaction.account', 'createdByUser', 'updatedByUser']);
    }

    protected function recalculateAccountBalances(Account $account): void
    {
        $balanceConstraintService = app(AccountBalanceConstraintService::class);
        $hasOpeningBalanceTransaction = Transaction::query()
            ->where('account_id', $account->id)
            ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
            ->exists();
        $runningBalance = $hasOpeningBalanceTransaction
            ? 0.0
            : (float) ($account->opening_balance ?? 0.0);

        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->orderBy('transaction_date')
            ->orderByRaw(
                'case when kind = ? then 0 else 1 end asc',
                [TransactionKindEnum::OPENING_BALANCE->value]
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $runningBalance += $this->signedAmount($transaction);
            $balanceConstraintService->ensureBalanceAllowed($account, $runningBalance);

            $transaction->forceFill([
                'balance_after' => round($runningBalance, 2),
            ])->save();
        }

        $balanceConstraintService->ensureBalanceAllowed($account, $runningBalance);

        $account->forceFill([
            'current_balance' => round($runningBalance, 2),
        ])->save();
    }

    public function recalculateAccount(Account $account): void
    {
        $this->recalculateAccountBalances($account);
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

    protected function accessibleAccount(User $user, int $accountId, bool $requireEdit = false): Account
    {
        $account = $this->accessibleAccountsQuery->findAccessibleAccount($user, $accountId, $requireEdit);

        if ($account instanceof Account) {
            return $account;
        }

        throw ValidationException::withMessages([
            'account_uuid' => $requireEdit
                ? __('transactions.validation.account_read_only')
                : __('transactions.validation.account_unavailable'),
        ]);
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

    protected function transferCategory(int $ownerUserId): Category
    {
        $category = Category::query()
            ->ownedBy($ownerUserId)
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

    protected function ensureNoConcurrentDuplicate(
        int $accountId,
        string $transactionDate,
        string $direction,
        float $amount,
        ?string $description,
        bool $isTransfer,
        ?int $ignoreTransactionId = null,
    ): void {
        $normalizedDescription = $this->normalizeDescription($description);

        $duplicates = Transaction::query()
            ->where('account_id', $accountId)
            ->whereDate('transaction_date', $transactionDate)
            ->where('direction', $direction)
            ->where('amount', $amount)
            ->where('is_transfer', $isTransfer)
            ->when(
                $ignoreTransactionId !== null,
                fn ($query) => $query->whereKeyNot($ignoreTransactionId),
            )
            ->get(['id', 'description']);

        $hasDuplicate = $duplicates->contains(function (Transaction $transaction) use ($normalizedDescription): bool {
            return $this->normalizeDescription($transaction->description) === $normalizedDescription;
        });

        if ($hasDuplicate) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.duplicate_transaction_detected'),
            ]);
        }
    }

    protected function normalizeDescription(?string $description): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', $description ?? '') ?? ''));
    }

    /**
     * @return array{current: Transaction, linked: Transaction|null}
     */
    protected function resolveTransferPair(Transaction $transaction): array
    {
        $linkedTransaction = null;

        if ($transaction->related_transaction_id !== null) {
            $linkedTransaction = Transaction::withTrashed()
                ->find($transaction->related_transaction_id);
        }

        if (! $linkedTransaction instanceof Transaction) {
            $linkedTransaction = Transaction::withTrashed()
                ->where('related_transaction_id', $transaction->id)
                ->first();
        }

        return [
            'current' => $transaction,
            'linked' => $linkedTransaction instanceof Transaction ? $linkedTransaction : null,
        ];
    }

    protected function ensureDeletionAllowed(Transaction $transaction): void
    {
        if ($transaction->kind === TransactionKindEnum::MANUAL) {
            return;
        }

        if ($transaction->kind === TransactionKindEnum::SCHEDULED) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.delete_scheduled_blocked'),
            ]);
        }

        if ($transaction->kind === TransactionKindEnum::OPENING_BALANCE) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.delete_opening_balance_blocked'),
            ]);
        }

        if ($transaction->kind === TransactionKindEnum::REFUND) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.delete_refund_blocked'),
            ]);
        }

        throw ValidationException::withMessages([
            'transaction' => __('transactions.validation.delete_blocked'),
        ]);
    }

    protected function ensureRestoreAllowed(Transaction $transaction): void
    {
        if (! $transaction->trashed()) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.restore_not_deleted'),
            ]);
        }

        if ($transaction->kind !== TransactionKindEnum::MANUAL) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.restore_blocked'),
            ]);
        }
    }

    protected function ensureForceDeleteAllowed(Transaction $transaction): void
    {
        if (! $transaction->trashed()) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.force_delete_not_deleted'),
            ]);
        }

        if ($transaction->kind !== TransactionKindEnum::MANUAL) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.force_delete_blocked'),
            ]);
        }
    }
}
