<?php

namespace App\Services\Transactions;

use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCardCycleCharge;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Accounts\AccountBalanceConstraintService;
use App\Services\Categories\CategoryFoundationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionMutationService
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected BalanceAdjustmentService $balanceAdjustmentService,
        protected OperationalTransactionCategoryResolver $operationalTransactionCategoryResolver,
        protected CategoryFoundationService $categoryFoundationService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(User $user, array $validated): Transaction
    {
        if ($this->isTransferPayload($validated)) {
            return $this->storeTransfer($user, $validated);
        }

        if ($this->isBalanceAdjustmentPayload($validated)) {
            return $this->storeBalanceAdjustment($user, $validated);
        }

        return DB::transaction(function () use ($user, $validated): Transaction {
            $account = $this->accessibleAccount($user, (int) $validated['account_id'], true);
            $categoryId = $this->resolvedCategoryIdForAccount($account, (int) $validated['category_id']);
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
                'scope_id' => $validated['scope_id'] ?? null,
                'category_id' => $categoryId,
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
            $this->reconcileProcessedCreditCardCyclesForCandidates([
                $this->cycleCandidateForAccountAndDate($account, (string) $validated['transaction_date']),
            ]);

            return $transaction->fresh(['account', 'category', 'trackedItem', 'createdByUser', 'updatedByUser']);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(User $user, Transaction $transaction, array $validated): Transaction
    {
        if ($this->isMovePayload($validated)) {
            return $this->move($user, $transaction, $validated);
        }

        if ($this->isTransferPayload($validated) || $transaction->is_transfer) {
            return $this->updateTransferAware($user, $transaction, $validated);
        }

        return DB::transaction(function () use ($user, $transaction, $validated): Transaction {
            $originalAccountId = $transaction->account_id;
            $originalTransactionDate = $transaction->transaction_date?->toDateString();
            $account = $this->accessibleAccount($user, (int) $validated['account_id'], true);
            $categoryId = $this->resolvedCategoryIdForAccount($account, (int) $validated['category_id']);
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
                'scope_id' => $validated['scope_id'] ?? null,
                'category_id' => $categoryId,
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
            $this->reconcileProcessedCreditCardCyclesForCandidates([
                $this->cycleCandidateForAccountAndDateId((int) $originalAccountId, $originalTransactionDate),
                $this->cycleCandidateForAccountAndDate($account, (string) $validated['transaction_date']),
            ]);

            return $transaction->fresh(['account', 'category', 'trackedItem', 'createdByUser', 'updatedByUser']);
        });
    }

    /**
     * @return array{source: Transaction, destination: Transaction}
     */
    public function storeGeneratedTransferBetweenAccounts(
        Account $sourceAccount,
        Account $destinationAccount,
        float $amount,
        string $transactionDate,
        ?int $actingUserId = null,
        ?string $description = null,
        ?string $notes = null,
    ): array {
        $transferCategory = $this->transferCategory((int) $sourceAccount->user_id);

        return $this->storeGeneratedTransferPairBetweenAccounts(
            sourceAccount: $sourceAccount,
            destinationAccount: $destinationAccount,
            amount: $amount,
            transactionDate: $transactionDate,
            actingUserId: $actingUserId,
            category: $transferCategory,
            kind: TransactionKindEnum::MANUAL,
            description: $description,
            notes: $notes,
        );
    }

    /**
     * @return array{source: Transaction, destination: Transaction}
     */
    public function storeGeneratedCreditCardSettlementBetweenAccounts(
        Account $sourceAccount,
        Account $destinationAccount,
        float $amount,
        string $transactionDate,
        ?int $actingUserId = null,
        ?string $description = null,
        ?string $notes = null,
    ): array {
        $settlementCategory = $this->creditCardSettlementCategory((int) $sourceAccount->user_id);

        return $this->storeGeneratedTransferPairBetweenAccounts(
            sourceAccount: $sourceAccount,
            destinationAccount: $destinationAccount,
            amount: $amount,
            transactionDate: $transactionDate,
            actingUserId: $actingUserId,
            category: $settlementCategory,
            kind: TransactionKindEnum::CREDIT_CARD_SETTLEMENT,
            description: $description,
            notes: $notes,
        );
    }

    /**
     * @return array{source: Transaction, destination: Transaction}
     */
    protected function storeGeneratedTransferPairBetweenAccounts(
        Account $sourceAccount,
        Account $destinationAccount,
        float $amount,
        string $transactionDate,
        ?int $actingUserId,
        Category $category,
        TransactionKindEnum $kind,
        ?string $description,
        ?string $notes,
    ): array {
        return DB::transaction(function () use (
            $sourceAccount,
            $destinationAccount,
            $amount,
            $transactionDate,
            $actingUserId,
            $category,
            $kind,
            $description,
            $notes,
        ): array {
            $roundedAmount = round($amount, 2);
            $resolvedActingUserId = $actingUserId ?? (int) $sourceAccount->user_id;

            $this->ensureNoConcurrentDuplicate(
                accountId: (int) $sourceAccount->id,
                transactionDate: $transactionDate,
                direction: TransactionDirectionEnum::EXPENSE->value,
                amount: $roundedAmount,
                description: $description,
                isTransfer: true,
            );
            $this->ensureNoConcurrentDuplicate(
                accountId: (int) $destinationAccount->id,
                transactionDate: $transactionDate,
                direction: TransactionDirectionEnum::INCOME->value,
                amount: $roundedAmount,
                description: $description,
                isTransfer: true,
            );

            $sourceTransaction = Transaction::query()->create([
                'user_id' => $sourceAccount->user_id,
                'created_by_user_id' => $resolvedActingUserId,
                'updated_by_user_id' => $resolvedActingUserId,
                'account_id' => $sourceAccount->id,
                'category_id' => $category->id,
                'tracked_item_id' => null,
                'transaction_date' => $transactionDate,
                'direction' => TransactionDirectionEnum::EXPENSE->value,
                'kind' => $kind->value,
                'amount' => $roundedAmount,
                'currency' => $sourceAccount->currency,
                'description' => $description ?: null,
                'notes' => $notes ?: null,
                'source_type' => TransactionSourceTypeEnum::GENERATED->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'value_date' => $transactionDate,
                'is_transfer' => true,
            ]);

            $destinationTransaction = Transaction::query()->create([
                'user_id' => $destinationAccount->user_id,
                'created_by_user_id' => $resolvedActingUserId,
                'updated_by_user_id' => $resolvedActingUserId,
                'account_id' => $destinationAccount->id,
                'category_id' => $category->id,
                'tracked_item_id' => null,
                'transaction_date' => $transactionDate,
                'direction' => TransactionDirectionEnum::INCOME->value,
                'kind' => $kind->value,
                'amount' => $roundedAmount,
                'currency' => $destinationAccount->currency,
                'description' => $description ?: null,
                'notes' => $notes ?: null,
                'source_type' => TransactionSourceTypeEnum::GENERATED->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'value_date' => $transactionDate,
                'is_transfer' => true,
                'related_transaction_id' => $sourceTransaction->id,
            ]);

            $sourceTransaction->forceFill([
                'related_transaction_id' => $destinationTransaction->id,
            ])->save();

            $this->recalculateAffectedAccounts([$sourceAccount, $destinationAccount]);

            return [
                'source' => $sourceTransaction->fresh(['relatedTransaction']),
                'destination' => $destinationTransaction->fresh(['relatedTransaction']),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function move(User $user, Transaction $transaction, array $validated): Transaction
    {
        return DB::transaction(function () use ($user, $transaction, $validated): Transaction {
            $account = $this->accessibleAccount($user, (int) $transaction->account_id, true);

            $this->ensureNoConcurrentDuplicate(
                accountId: $account->id,
                transactionDate: (string) $validated['transaction_date'],
                direction: $transaction->direction instanceof TransactionDirectionEnum
                    ? $transaction->direction->value
                    : (string) $transaction->direction,
                amount: round((float) $transaction->amount, 2),
                description: $transaction->description,
                isTransfer: false,
                ignoreTransactionId: $transaction->id,
            );

            $transaction->fill([
                'updated_by_user_id' => $user->id,
                'transaction_date' => $validated['transaction_date'],
                'value_date' => $validated['transaction_date'],
            ]);
            $transaction->save();

            $this->recalculateAffectedAccounts([$account]);
            $this->reconcileProcessedCreditCardCyclesForCandidates([
                $this->cycleCandidateForAccountAndDate($account, (string) $validated['transaction_date']),
            ]);

            return $transaction->fresh(['account', 'category', 'trackedItem', 'createdByUser', 'updatedByUser']);
        });
    }

    protected function resolvedCategoryIdForAccount(Account $account, int $categoryId): int
    {
        $category = $this->operationalTransactionCategoryResolver->findCategoryForAccount($account, $categoryId);

        return $category instanceof Category
            ? (int) $category->id
            : $categoryId;
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
                $this->reconcileProcessedCreditCardCyclesForCandidates(
                    $this->cycleCandidatesForTransactions([$pair['current'], $pair['linked']]),
                );

                return;
            }

            $account = $this->accessibleAccount($user, (int) $transaction->account_id, true);
            $transaction->forceFill([
                'updated_by_user_id' => $user->id,
            ])->save();

            $transaction->delete();

            $this->recalculateAffectedAccounts([$account]);
            $this->reconcileProcessedCreditCardCyclesForCandidates(
                $this->cycleCandidatesForTransactions([$transaction]),
            );
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
                $this->reconcileProcessedCreditCardCyclesForCandidates(
                    $this->cycleCandidatesForTransactions([$pair['current'], $pair['linked']]),
                );

                return;
            }

            $account = $this->accessibleAccount($user, (int) $transaction->account_id, true);

            $transaction->forceFill([
                'updated_by_user_id' => $user->id,
            ]);
            $transaction->restore();

            $this->recalculateAffectedAccounts([$account]);
            $this->reconcileProcessedCreditCardCyclesForCandidates(
                $this->cycleCandidatesForTransactions([$transaction]),
            );
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
                $this->reconcileProcessedCreditCardCyclesForCandidates(
                    $this->cycleCandidatesForTransactions([$pair['current'], $pair['linked']]),
                );

                return;
            }

            $account = $this->accessibleAccount($user, (int) $transaction->account_id, true);

            $transaction->forceDelete();

            $this->recalculateAffectedAccounts([$account]);
            $this->reconcileProcessedCreditCardCyclesForCandidates(
                $this->cycleCandidatesForTransactions([$transaction]),
            );
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

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function isMovePayload(array $validated): bool
    {
        return ($validated['type_key'] ?? null) === StoreTransactionRequest::MOVE_TYPE_KEY;
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
    protected function storeBalanceAdjustment(User $user, array $validated): Transaction
    {
        return DB::transaction(function () use ($user, $validated): Transaction {
            $account = $this->accessibleAccount($user, (int) $validated['account_id'], true);
            $preview = $this->balanceAdjustmentService->preview(
                $account,
                (string) $validated['transaction_date'],
                (float) $validated['desired_balance']
            );

            if ((float) $preview['absolute_amount'] <= 0.0) {
                throw ValidationException::withMessages([
                    'desired_balance' => __('transactions.validation.balance_adjustment_no_difference'),
                ]);
            }

            $transaction = Transaction::query()->create([
                'user_id' => $account->user_id,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
                'account_id' => $account->id,
                'scope_id' => null,
                'category_id' => null,
                'tracked_item_id' => null,
                'transaction_date' => $validated['transaction_date'],
                'direction' => $preview['direction'],
                'kind' => TransactionKindEnum::BALANCE_ADJUSTMENT->value,
                'amount' => $preview['absolute_amount'],
                'currency' => $account->currency,
                'description' => $validated['description'] ?: __('transactions.balance_adjustment.detail'),
                'notes' => $validated['notes'] ?: null,
                'source_type' => TransactionSourceTypeEnum::ADJUSTMENT->value,
                'status' => TransactionStatusEnum::CONFIRMED->value,
                'value_date' => $validated['transaction_date'],
            ]);

            $this->recalculateAffectedAccounts([$account]);
            $this->reconcileProcessedCreditCardCyclesForCandidates([
                $this->cycleCandidateForAccountAndDate($account, (string) $validated['transaction_date']),
            ]);

            return $transaction->fresh(['account', 'category', 'trackedItem', 'createdByUser', 'updatedByUser']);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function isTransferPayload(array $validated): bool
    {
        return ($validated['type_key'] ?? null) === CategoryGroupTypeEnum::TRANSFER->value;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function isBalanceAdjustmentPayload(array $validated): bool
    {
        return ($validated['type_key'] ?? null) === 'balance_adjustment';
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

    protected function creditCardSettlementCategory(int $ownerUserId): Category
    {
        return $this->categoryFoundationService->ensureCreditCardSettlementCategoryForUserId($ownerUserId);
    }

    /**
     * @param  array<int, array{account_id:int|null, transaction_date:string|null}>  $candidates
     */
    protected function reconcileProcessedCreditCardCyclesForCandidates(array $candidates): void
    {
        $referenceDate = CarbonImmutable::today(config('app.timezone'))->toDateString();

        foreach ($this->processedCycleChargesForCandidates($candidates) as $cycleCharge) {
            $this->reconcileProcessedCreditCardCycleCharge($cycleCharge, $referenceDate);
        }
    }

    /**
     * @param  array<int, Transaction|null>  $transactions
     */
    public function reconcileProcessedCreditCardCyclesForTransactions(array $transactions): void
    {
        $this->reconcileProcessedCreditCardCyclesForCandidates(
            $this->cycleCandidatesForTransactions($transactions),
        );
    }

    /**
     * @param  array<int, array{account_id:int|null, transaction_date:string|null}>  $candidates
     * @return Collection<int, CreditCardCycleCharge>
     */
    protected function processedCycleChargesForCandidates(array $candidates): Collection
    {
        $cycleCharges = collect();

        foreach ($candidates as $candidate) {
            $accountId = isset($candidate['account_id']) && is_numeric($candidate['account_id'])
                ? (int) $candidate['account_id']
                : null;
            $transactionDate = $candidate['transaction_date'] ?? null;

            if ($accountId === null || $transactionDate === null) {
                continue;
            }

            $account = Account::query()
                ->with('accountType:id,code')
                ->find($accountId);

            if (! $account instanceof Account || ! $account->isCreditCard()) {
                continue;
            }

            $cycleChargeId = $this->processedCycleChargeIdForAccountAndDate($account->id, $transactionDate);

            if ($cycleChargeId !== null) {
                $cycleCharge = CreditCardCycleCharge::query()->find($cycleChargeId);

                if ($cycleCharge instanceof CreditCardCycleCharge) {
                    $cycleCharges->push($cycleCharge);
                }
            }
        }

        return $cycleCharges->unique('id')->values();
    }

    protected function reconcileProcessedCreditCardCycleCharge(
        CreditCardCycleCharge $cycleCharge,
        string $referenceDate,
    ): void {
        $creditCardAccount = Account::query()
            ->with('accountType:id,code')
            ->find($cycleCharge->credit_card_account_id);

        if (! $creditCardAccount instanceof Account || ! $creditCardAccount->isCreditCard()) {
            return;
        }

        $balanceAtCycleEnd = round(
            $this->balanceAdjustmentService->theoreticalBalanceAt(
                $creditCardAccount,
                $cycleCharge->cycle_end_date->toDateString(),
            ),
            2,
        );
        $postCycleRefundSignedAmount = $this->postCycleRefundSignedAmountForCycleCharge($cycleCharge);
        $targetChargedAmount = round(max(($balanceAtCycleEnd + $postCycleRefundSignedAmount) * -1, 0), 2);
        $currentChargedAmount = $this->currentCreditCardCycleChargedAmount($cycleCharge);
        $deltaAmount = round($targetChargedAmount - $currentChargedAmount, 2);

        $linkedPaymentAccount = $this->linkedPaymentAccountForCycleCharge($cycleCharge);
        $description = __('transactions.credit_card.autopay.description', [
            'account' => $creditCardAccount->name,
            'date' => $cycleCharge->cycle_end_date->format('d/m/Y'),
        ]);

        $meta = is_array($cycleCharge->meta) ? $cycleCharge->meta : [];
        $settlementPair = $this->synchronizeCreditCardSettlementPairForCycleCharge(
            $cycleCharge,
            $linkedPaymentAccount,
            $creditCardAccount,
            $targetChargedAmount,
            $description,
        );
        $adjustmentAmountTotal = round($targetChargedAmount - (float) $cycleCharge->charged_amount, 2);
        $adjustments = collect(data_get($meta, 'adjustments', []));

        if (abs($deltaAmount) >= 0.01) {
            $adjustments->push([
                'delta_amount' => $deltaAmount,
                'previous_amount' => $currentChargedAmount,
                'new_amount' => $targetChargedAmount,
                'payment_transaction_id' => $settlementPair['payment_transaction_id'],
                'card_settlement_transaction_id' => $settlementPair['card_settlement_transaction_id'],
                'processed_at' => CarbonImmutable::parse($referenceDate)->toISOString(),
                'transaction_date' => $referenceDate,
            ]);
        }

        $cycleCharge->forceFill([
            'payment_transaction_id' => $settlementPair['payment_transaction_id'],
            'card_settlement_transaction_id' => $settlementPair['card_settlement_transaction_id'],
            'balance_at_cycle_end' => $balanceAtCycleEnd,
            'meta' => [
                ...$meta,
                'adjustment_amount_total' => $adjustmentAmountTotal,
                'current_charged_amount' => $targetChargedAmount,
                'last_reconciled_balance_at_cycle_end' => $balanceAtCycleEnd,
                'post_cycle_refund_signed_amount' => $postCycleRefundSignedAmount,
                'last_reconciled_at' => CarbonImmutable::parse($referenceDate)->toISOString(),
                'adjustments' => $adjustments->values()->all(),
            ],
        ])->save();
    }

    /**
     * @return array{payment_transaction_id:int|null, card_settlement_transaction_id:int|null}
     */
    protected function synchronizeCreditCardSettlementPairForCycleCharge(
        CreditCardCycleCharge $cycleCharge,
        Account $linkedPaymentAccount,
        Account $creditCardAccount,
        float $targetChargedAmount,
        string $description,
    ): array {
        $this->purgeCreditCardAdjustmentTransactions($cycleCharge);

        $paymentTransaction = $cycleCharge->paymentTransaction()->withTrashed()->first();
        $cardSettlementTransaction = $cycleCharge->cardSettlementTransaction()->withTrashed()->first();

        if ($targetChargedAmount <= 0) {
            if ($paymentTransaction instanceof Transaction) {
                $paymentTransaction->forceDelete();
            }

            if ($cardSettlementTransaction instanceof Transaction) {
                $cardSettlementTransaction->forceDelete();
            }

            $this->recalculateAffectedAccounts([$linkedPaymentAccount, $creditCardAccount]);

            return [
                'payment_transaction_id' => null,
                'card_settlement_transaction_id' => null,
            ];
        }

        if (! $paymentTransaction instanceof Transaction || ! $cardSettlementTransaction instanceof Transaction) {
            $transferPair = $this->storeGeneratedCreditCardSettlementBetweenAccounts(
                $linkedPaymentAccount,
                $creditCardAccount,
                $targetChargedAmount,
                $cycleCharge->payment_due_date->toDateString(),
                (int) $creditCardAccount->user_id,
                $description,
                __('transactions.credit_card.autopay.notes'),
            );

            return [
                'payment_transaction_id' => (int) $transferPair['source']->id,
                'card_settlement_transaction_id' => (int) $transferPair['destination']->id,
            ];
        }

        $paymentTransaction->forceFill([
            'amount' => $targetChargedAmount,
            'transaction_date' => $cycleCharge->payment_due_date->toDateString(),
            'value_date' => $cycleCharge->payment_due_date->toDateString(),
            'description' => $description,
            'notes' => __('transactions.credit_card.autopay.notes'),
        ])->save();

        $cardSettlementTransaction->forceFill([
            'amount' => $targetChargedAmount,
            'transaction_date' => $cycleCharge->payment_due_date->toDateString(),
            'value_date' => $cycleCharge->payment_due_date->toDateString(),
            'description' => $description,
            'notes' => __('transactions.credit_card.autopay.notes'),
        ])->save();

        $this->recalculateAffectedAccounts([$linkedPaymentAccount, $creditCardAccount]);

        return [
            'payment_transaction_id' => (int) $paymentTransaction->id,
            'card_settlement_transaction_id' => (int) $cardSettlementTransaction->id,
        ];
    }

    protected function purgeCreditCardAdjustmentTransactions(CreditCardCycleCharge $cycleCharge): void
    {
        $adjustmentTransactionIds = collect(data_get($cycleCharge->meta, 'adjustments', []))
            ->flatMap(fn (array $adjustment): array => [
                $adjustment['payment_transaction_id'] ?? null,
                $adjustment['card_settlement_transaction_id'] ?? null,
            ])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($adjustmentTransactionIds->isEmpty()) {
            return;
        }

        Transaction::withTrashed()
            ->whereIn('id', $adjustmentTransactionIds->all())
            ->orderByDesc('id')
            ->get()
            ->each(function (Transaction $transaction): void {
                $transaction->forceDelete();
            });
    }

    protected function postCycleRefundSignedAmountForCycleCharge(CreditCardCycleCharge $cycleCharge): float
    {
        return round(
            Transaction::query()
                ->with('refundedTransaction')
                ->where('account_id', $cycleCharge->credit_card_account_id)
                ->where('kind', TransactionKindEnum::REFUND->value)
                ->whereDate('transaction_date', '>', $cycleCharge->cycle_end_date->toDateString())
                ->whereNotNull('refunded_transaction_id')
                ->get()
                ->filter(function (Transaction $refund) use ($cycleCharge): bool {
                    $originalTransaction = $refund->refundedTransaction;

                    if (! $originalTransaction instanceof Transaction) {
                        return false;
                    }

                    return $this->processedCycleChargeIdForAccountAndDate(
                        (int) $originalTransaction->account_id,
                        $originalTransaction->transaction_date?->toDateString(),
                    ) === $cycleCharge->id;
                })
                ->sum(fn (Transaction $refund): float => $this->signedAmount($refund)),
            2,
        );
    }

    protected function currentCreditCardCycleChargedAmount(CreditCardCycleCharge $cycleCharge): float
    {
        $meta = is_array($cycleCharge->meta) ? $cycleCharge->meta : [];

        return round((float) $cycleCharge->charged_amount + (float) data_get($meta, 'adjustment_amount_total', 0), 2);
    }

    protected function linkedPaymentAccountForCycleCharge(CreditCardCycleCharge $cycleCharge): Account
    {
        $linkedPaymentAccount = Account::query()
            ->find($cycleCharge->linked_payment_account_id);

        if ($linkedPaymentAccount instanceof Account) {
            return $linkedPaymentAccount;
        }

        throw ValidationException::withMessages([
            'account_uuid' => __('transactions.credit_card.autopay.reporting.missing_linked_account'),
        ]);
    }

    protected function processedCycleChargeIdForAccountAndDate(int $accountId, ?string $transactionDate): ?int
    {
        if ($transactionDate === null) {
            return null;
        }

        return CreditCardCycleCharge::query()
            ->where('credit_card_account_id', $accountId)
            ->whereDate('cycle_end_date', '>=', $transactionDate)
            ->orderBy('cycle_end_date')
            ->value('id');
    }

    /**
     * @return array{account_id:int|null, transaction_date:string|null}
     */
    protected function cycleCandidateForTransaction(?Transaction $transaction): array
    {
        if (! $transaction instanceof Transaction || $transaction->isCreditCardSettlement()) {
            return [];
        }

        $candidates = [[
            'account_id' => (int) $transaction->account_id,
            'transaction_date' => $transaction->transaction_date?->toDateString(),
        ]];

        if ($transaction->kind === TransactionKindEnum::REFUND && $transaction->refunded_transaction_id !== null) {
            $refundedTransaction = $transaction->relationLoaded('refundedTransaction')
                ? $transaction->refundedTransaction
                : Transaction::withTrashed()->find($transaction->refunded_transaction_id);

            if ($refundedTransaction instanceof Transaction) {
                $candidates[] = [
                    'account_id' => (int) $refundedTransaction->account_id,
                    'transaction_date' => $refundedTransaction->transaction_date?->toDateString(),
                ];
            }
        }

        return collect($candidates)
            ->unique(fn (array $candidate): string => sprintf(
                '%s|%s',
                (string) ($candidate['account_id'] ?? ''),
                (string) ($candidate['transaction_date'] ?? ''),
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, Transaction|null>  $transactions
     * @return array<int, array{account_id:int|null, transaction_date:string|null}>
     */
    protected function cycleCandidatesForTransactions(array $transactions): array
    {
        return collect($transactions)
            ->flatMap(fn (?Transaction $transaction): array => $this->cycleCandidateForTransaction($transaction))
            ->all();
    }

    /**
     * @return array{account_id:int|null, transaction_date:string|null}
     */
    protected function cycleCandidateForAccountAndDate(Account $account, string $transactionDate): array
    {
        return $this->cycleCandidateForAccountAndDateId((int) $account->id, $transactionDate);
    }

    /**
     * @return array{account_id:int|null, transaction_date:string|null}
     */
    protected function cycleCandidateForAccountAndDateId(?int $accountId, ?string $transactionDate): array
    {
        return [
            'account_id' => $accountId,
            'transaction_date' => $transactionDate,
        ];
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
        if ($transaction->refundTransaction()->exists()) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.delete_refunded_original_blocked'),
            ]);
        }

        if (in_array($transaction->kind, [TransactionKindEnum::MANUAL, TransactionKindEnum::BALANCE_ADJUSTMENT], true)) {
            return;
        }

        if ($transaction->kind === TransactionKindEnum::CREDIT_CARD_SETTLEMENT) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.delete_credit_card_settlement_blocked'),
            ]);
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

        if ($transaction->kind === TransactionKindEnum::CREDIT_CARD_SETTLEMENT) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.restore_credit_card_settlement_blocked'),
            ]);
        }

        if (! in_array($transaction->kind, [TransactionKindEnum::MANUAL, TransactionKindEnum::BALANCE_ADJUSTMENT], true)) {
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

        if ($transaction->refundTransaction()->exists()) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.delete_refunded_original_blocked'),
            ]);
        }

        if ($transaction->kind === TransactionKindEnum::CREDIT_CARD_SETTLEMENT) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.force_delete_credit_card_settlement_blocked'),
            ]);
        }

        if (! in_array($transaction->kind, [TransactionKindEnum::MANUAL, TransactionKindEnum::BALANCE_ADJUSTMENT], true)) {
            throw ValidationException::withMessages([
                'transaction' => __('transactions.validation.force_delete_blocked'),
            ]);
        }
    }
}
