<?php

namespace App\Services\SharedExpenses;

use App\Enums\TransactionDirectionEnum;
use App\Models\Account;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\Transactions\TransactionExchangeSnapshotService;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SharedExpenseSettlementService
{
    public const COUNTERPARTY_TO_ME = 'counterparty_to_me';

    public const ME_TO_COUNTERPARTY = 'me_to_counterparty';

    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected OperationalTransactionCategoryResolver $operationalTransactionCategoryResolver,
        protected SharedExpenseAllocation $sharedExpenseAllocation,
        protected TransactionMutationService $transactionMutationService,
        protected TransactionExchangeSnapshotService $transactionExchangeSnapshotService,
    ) {}

    public function balanceCents(User $user, TrackedItem $counterparty): int
    {
        $transactionEffect = Transaction::query()
            ->whereIn('account_id', $this->accessibleAccountsQuery->ids($user))
            ->where('shared_expense_counterparty_id', $counterparty->id)
            ->get(['amount', 'shared_expense_paid_by', 'shared_expense_my_share_cents', 'shared_expense_counterparty_share_cents'])
            ->sum(function (Transaction $transaction): int {
                $totalCents = $this->sharedExpenseAllocation->toCents((string) $transaction->amount);

                return $this->sharedExpenseAllocation->balanceEffectCents([
                    'paid_by' => $transaction->shared_expense_paid_by ?? SharedExpenseAllocation::PAID_BY_SELF,
                    'my_share_cents' => $transaction->shared_expense_my_share_cents ?? intdiv($totalCents, 2),
                    'counterparty_share_cents' => $transaction->shared_expense_counterparty_share_cents ?? ($totalCents - intdiv($totalCents, 2)),
                ]);
            });
        $ledgerEffect = SharedExpenseLedgerEntry::query()->recorded()
            ->where('user_id', $user->id)
            ->where('counterparty_id', $counterparty->id)
            ->get(['paid_by', 'my_share_cents', 'counterparty_share_cents'])
            ->sum(fn (SharedExpenseLedgerEntry $entry): int => $this->sharedExpenseAllocation->balanceEffectCents([
                'paid_by' => $entry->paid_by,
                'my_share_cents' => $entry->my_share_cents,
                'counterparty_share_cents' => $entry->counterparty_share_cents,
            ]));
        $settlementEffect = SharedExpenseSettlement::query()
            ->where('user_id', $user->id)
            ->where('counterparty_id', $counterparty->id)
            ->sum(DB::raw("case when direction = 'counterparty_to_me' then -amount_cents else amount_cents end"));

        return $transactionEffect + $ledgerEffect + (int) $settlementEffect;
    }

    public function settlementEffectCents(SharedExpenseSettlement $settlement): int
    {
        return $settlement->direction === self::COUNTERPARTY_TO_ME
            ? -$settlement->amount_cents
            : $settlement->amount_cents;
    }

    /** @param array{amount: string, settled_at: string, account_uuid: string, category_uuid?: string|null, reference?: string|null, notes?: string|null} $validated */
    public function record(User $user, TrackedItem $counterparty, array $validated): SharedExpenseSettlement
    {
        return DB::transaction(function () use ($user, $counterparty, $validated): SharedExpenseSettlement {
            $counterparty = TrackedItem::query()->whereKey($counterparty->id)->lockForUpdate()->firstOrFail();
            $balanceCents = $this->balanceCents($user, $counterparty);

            if ($balanceCents === 0) {
                throw ValidationException::withMessages(['counterparty' => __('ui.this_counterparty_is_already_settled_4970667c')]);
            }

            $amountCents = $this->sharedExpenseAllocation->toCents($validated['amount']);
            if ($amountCents > abs($balanceCents)) {
                throw ValidationException::withMessages(['amount' => __('ui.the_amount_cannot_exceed_the_remaining_balance_50a91f22')]);
            }

            $account = $this->accessibleAccountsQuery->editable($user)
                ->where('accounts.uuid', $validated['account_uuid'])
                ->first();
            if (! $account instanceof Account) {
                throw ValidationException::withMessages(['account_uuid' => __('ui.select_an_editable_account_825c5060')]);
            }

            $categoryId = null;
            if (! empty($validated['category_uuid'])) {
                $categoryId = $this->operationalTransactionCategoryResolver
                    ->categoriesForAccount($account)
                    ->firstWhere('uuid', $validated['category_uuid'])?->id;
                if ($categoryId === null) {
                    throw ValidationException::withMessages(['category_uuid' => __('ui.the_selected_category_is_unavailable_for_this_account_15aae920')]);
                }
            }

            $direction = $balanceCents > 0 ? self::COUNTERPARTY_TO_ME : self::ME_TO_COUNTERPARTY;
            $transaction = $this->transactionMutationService->storeGeneratedStandardTransaction(
                user: $user,
                account: $account,
                direction: $direction === self::COUNTERPARTY_TO_ME ? TransactionDirectionEnum::INCOME->value : TransactionDirectionEnum::EXPENSE->value,
                amount: $amountCents / 100,
                transactionDate: $validated['settled_at'],
                categoryId: $categoryId,
                description: $validated['reference'] ?: ($direction === self::COUNTERPARTY_TO_ME ? "Rimborso da {$counterparty->name}" : "Rimborso a {$counterparty->name}"),
                notes: $validated['notes'] ?? null,
            );

            return SharedExpenseSettlement::query()->create([
                'user_id' => $user->id,
                'counterparty_id' => $counterparty->id,
                'amount_cents' => $amountCents,
                'direction' => $direction,
                'settled_at' => $validated['settled_at'],
                'transaction_id' => $transaction->id,
                'notes' => $validated['notes'] ?? null,
            ]);
        });
    }

    /** @param array{amount: string, settled_at: string, account_uuid: string, category_uuid?: string|null, reference?: string|null, notes?: string|null} $validated */
    public function update(User $user, SharedExpenseSettlement $settlement, array $validated): SharedExpenseSettlement
    {
        return DB::transaction(function () use ($user, $settlement, $validated): SharedExpenseSettlement {
            $settlement = SharedExpenseSettlement::query()->with('counterparty')->lockForUpdate()->findOrFail($settlement->id);
            $counterparty = TrackedItem::query()->whereKey($settlement->counterparty_id)->lockForUpdate()->firstOrFail();
            $amountCents = $this->sharedExpenseAllocation->toCents($validated['amount']);
            $balanceWithoutSettlement = $this->balanceCents($user, $counterparty) - $this->settlementEffectCents($settlement);

            if ($amountCents > abs($balanceWithoutSettlement)) {
                throw ValidationException::withMessages(['amount' => __('ui.the_amount_cannot_exceed_the_remaining_balance_50a91f22')]);
            }

            $account = $this->editableAccount($user, $validated['account_uuid']);
            $categoryId = $this->categoryId($account, $validated['category_uuid'] ?? null);
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($settlement->transaction_id);
            $previousAccount = $transaction->account()->firstOrFail();
            $direction = $settlement->direction === self::COUNTERPARTY_TO_ME ? TransactionDirectionEnum::INCOME->value : TransactionDirectionEnum::EXPENSE->value;
            $amount = $amountCents / 100;

            $transaction->forceFill([
                'account_id' => $account->id,
                'category_id' => $categoryId,
                'transaction_date' => $validated['settled_at'],
                'value_date' => $validated['settled_at'],
                'direction' => $direction,
                'amount' => $amount,
                'currency' => $account->currency,
                ...$this->transactionExchangeSnapshotService->buildForAccount($account, $amount, $validated['settled_at']),
                'description' => $validated['reference'] ?: ($settlement->direction === self::COUNTERPARTY_TO_ME ? "Rimborso da {$counterparty->name}" : "Rimborso a {$counterparty->name}"),
                'notes' => $validated['notes'] ?? null,
                'updated_by_user_id' => $user->id,
            ])->save();
            $settlement->forceFill(['amount_cents' => $amountCents, 'settled_at' => $validated['settled_at'], 'notes' => $validated['notes'] ?? null])->save();

            $this->transactionMutationService->recalculateAccount($previousAccount);
            if ($previousAccount->id !== $account->id) {
                $this->transactionMutationService->recalculateAccount($account);
            }

            return $settlement->fresh('transaction');
        });
    }

    public function delete(User $user, SharedExpenseSettlement $settlement): void
    {
        DB::transaction(function () use ($user, $settlement): void {
            $settlement = SharedExpenseSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            TrackedItem::query()->whereKey($settlement->counterparty_id)->lockForUpdate()->firstOrFail();
            $transaction = Transaction::query()->findOrFail($settlement->transaction_id);
            $settlement->delete();
            $this->transactionMutationService->destroy($user, $transaction);
        });
    }

    private function editableAccount(User $user, string $accountUuid): Account
    {
        $account = $this->accessibleAccountsQuery->editable($user)->where('accounts.uuid', $accountUuid)->first();
        if (! $account instanceof Account) {
            throw ValidationException::withMessages(['account_uuid' => __('ui.select_an_editable_account_825c5060')]);
        }

        return $account;
    }

    private function categoryId(Account $account, ?string $categoryUuid): ?int
    {
        if ($categoryUuid === null || $categoryUuid === '') {
            return null;
        }

        $categoryId = $this->operationalTransactionCategoryResolver->categoriesForAccount($account)->firstWhere('uuid', $categoryUuid)?->id;
        if ($categoryId === null) {
            throw ValidationException::withMessages(['category_uuid' => __('ui.the_selected_category_is_unavailable_for_this_account_15aae920')]);
        }

        return $categoryId;
    }
}
