<?php

namespace App\Services\SharedExpenses;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\SharedExpenseLedgerEntry;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Counterparties\CounterpartyAuthority;
use App\Services\Counterparties\SharedCounterpartyShadowService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\Transactions\TransactionExchangeSnapshotService;
use App\Services\Transactions\TransactionMutationService;
use App\Support\CurrencyMinorUnits;
use App\Support\MoneyAmount;
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
        protected SharedCounterpartyShadowService $counterpartyShadow,
    ) {}

    public function balanceCents(User $user, TrackedItem $counterparty, string $currency = 'EUR'): int
    {
        $authority = app(CounterpartyAuthority::class);
        $relation = $authority->activeRelation($user->id, $counterparty->id, $currency);

        return $relation === null ? $this->legacyBalanceCents($user, $counterparty, $currency) : MoneyAmount::cents(CurrencyMinorUnits::toDecimal($authority->summary($relation)['shared_minor'], $currency));
    }

    public function legacyBalanceCents(User $user, TrackedItem $counterparty, string $currency = 'EUR'): int
    {
        abort_unless($counterparty->user_id === $user->id && $counterparty->account_id === null, 404);

        $transactionEffect = Transaction::query()
            ->whereIn('account_id', $this->accessibleAccountsQuery->ids($user))
            ->where('shared_expense_counterparty_id', $counterparty->id)
            ->where('currency', $currency)
            ->with('refundTransaction')->get()
            ->sum(function (Transaction $transaction): int {
                $totalCents = $this->sharedExpenseAllocation->toCents((string) $transaction->amount);

                $effect = $this->sharedExpenseAllocation->balanceEffectCents([
                    'paid_by' => $transaction->shared_expense_paid_by ?? SharedExpenseAllocation::PAID_BY_SELF,
                    'my_share_cents' => $transaction->shared_expense_my_share_cents ?? intdiv($totalCents, 2),
                    'counterparty_share_cents' => $transaction->shared_expense_counterparty_share_cents ?? ($totalCents - intdiv($totalCents, 2)),
                ]);

                return $totalCents > 0 ? MoneyAmount::proportional($effect, $this->activeTransactionCents($transaction), $totalCents) : 0;
            });
        $ledgerEffect = SharedExpenseLedgerEntry::query()->recorded()
            ->where('user_id', $user->id)
            ->where('counterparty_id', $counterparty->id)
            ->where('currency', $currency)
            ->get(['paid_by', 'my_share_cents', 'counterparty_share_cents'])
            ->sum(fn (SharedExpenseLedgerEntry $entry): int => $this->sharedExpenseAllocation->balanceEffectCents([
                'paid_by' => $entry->paid_by,
                'my_share_cents' => $entry->my_share_cents,
                'counterparty_share_cents' => $entry->counterparty_share_cents,
            ]));
        $settlementEffect = SharedExpenseSettlement::query()
            ->where('user_id', $user->id)
            ->where('counterparty_id', $counterparty->id)
            ->whereHas('transaction', fn ($query) => $query->where('currency', $currency))
            ->with(['transaction.refundTransaction', 'transaction.account'])
            ->get()->sum(fn (SharedExpenseSettlement $settlement): int => $this->settlementEffectCents($settlement));

        return $transactionEffect + $ledgerEffect + (int) $settlementEffect;
    }

    public function activeTransactionCents(Transaction $transaction): int
    {
        if ($transaction->trashed() || $transaction->status !== TransactionStatusEnum::CONFIRMED) {
            return 0;
        }

        $refund = $transaction->refundTransaction;

        return max(0, MoneyAmount::cents((string) $transaction->amount)
            - ($refund?->status === TransactionStatusEnum::CONFIRMED ? MoneyAmount::cents((string) $refund->amount) : 0));
    }

    public function settlementEffectCents(SharedExpenseSettlement $settlement): int
    {
        $transaction = $settlement->transaction;
        $direction = $settlement->direction === self::COUNTERPARTY_TO_ME
            ? TransactionDirectionEnum::INCOME : TransactionDirectionEnum::EXPENSE;
        if ($transaction === null || $transaction->currency !== 'EUR' || $transaction->account?->currency_code !== 'EUR' || $transaction->direction !== $direction
            || MoneyAmount::cents((string) $transaction->amount) !== $settlement->amount_cents
            || $transaction->user_id !== $transaction->account?->user_id) {
            return 0;
        }

        $activeCents = $this->activeTransactionCents($transaction);

        return $settlement->direction === self::COUNTERPARTY_TO_ME ? -$activeCents : $activeCents;
    }

    /** @param array{amount: string, settled_at: string, account_uuid: string, category_uuid?: string|null, reference?: string|null, notes?: string|null} $validated */
    public function record(User $user, TrackedItem $counterparty, array $validated): SharedExpenseSettlement
    {
        if (app(CounterpartyAuthority::class)->activeRelation($user->id, $counterparty->id, 'EUR') !== null) {
            throw ValidationException::withMessages(['counterparty' => __('form_errors.use_record_payment_in_the_counterparty_relationship')]);
        }

        return DB::transaction(function () use ($user, $counterparty, $validated): SharedExpenseSettlement {
            $counterparty = TrackedItem::query()->whereKey($counterparty->id)->lockForUpdate()->firstOrFail();
            if (app(CounterpartyAuthority::class)->activeRelation($user->id, $counterparty->id, 'EUR') !== null) {
                throw ValidationException::withMessages(['counterparty' => __('form_errors.use_record_payment_in_the_counterparty_relationship')]);
            }
            $balanceCents = $this->balanceCents($user, $counterparty);

            if ($balanceCents === 0) {
                throw ValidationException::withMessages(['counterparty' => __('ui.this_counterparty_is_already_settled_4970667c')]);
            }

            $amountCents = $this->sharedExpenseAllocation->toCents($validated['amount']);
            if ($amountCents <= 0 || $amountCents > abs($balanceCents)) {
                throw ValidationException::withMessages(['amount' => __('ui.the_amount_cannot_exceed_the_remaining_balance_50a91f22')]);
            }

            $account = $this->accessibleAccountsQuery->editable($user)
                ->where('accounts.uuid', $validated['account_uuid'])
                ->first();
            if (! $account instanceof Account) {
                throw ValidationException::withMessages(['account_uuid' => __('ui.select_an_editable_account_825c5060')]);
            }

            $this->ensureSettlementCurrency($account);

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
                amount: (float) MoneyAmount::decimal($amountCents),
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
        abort_unless($settlement->user_id === $user->id, 404);

        return DB::transaction(function () use ($user, $settlement, $validated): SharedExpenseSettlement {
            $settlement = SharedExpenseSettlement::query()->with('counterparty')->lockForUpdate()->findOrFail($settlement->id);
            $counterparty = TrackedItem::query()->whereKey($settlement->counterparty_id)->lockForUpdate()->firstOrFail();
            $amountCents = $this->sharedExpenseAllocation->toCents($validated['amount']);
            $balanceWithoutSettlement = $this->balanceCents($user, $counterparty) - $this->settlementEffectCents($settlement);

            if ($amountCents <= 0 || $amountCents > abs($balanceWithoutSettlement)) {
                throw ValidationException::withMessages(['amount' => __('ui.the_amount_cannot_exceed_the_remaining_balance_50a91f22')]);
            }

            $expectedDirection = $balanceWithoutSettlement > 0 ? self::COUNTERPARTY_TO_ME : self::ME_TO_COUNTERPARTY;
            if ($balanceWithoutSettlement === 0 || $settlement->direction !== $expectedDirection) {
                throw ValidationException::withMessages(['amount' => __('credit_debts.validation.settlement_direction_changed')]);
            }

            $account = $this->editableAccount($user, $validated['account_uuid']);
            $this->ensureSettlementCurrency($account);
            $categoryId = $this->categoryId($account, $validated['category_uuid'] ?? null);
            $transaction = Transaction::query()->lockForUpdate()->findOrFail($settlement->transaction_id);
            if ($transaction->status !== TransactionStatusEnum::CONFIRMED || $transaction->refundTransaction()->exists() || $this->settlementEffectCents($settlement) === 0) {
                throw ValidationException::withMessages(['transaction' => __('transactions.validation.linked_obligation_locked')]);
            }
            $previousAccount = $transaction->account()->firstOrFail();
            $direction = $settlement->direction === self::COUNTERPARTY_TO_ME ? TransactionDirectionEnum::INCOME->value : TransactionDirectionEnum::EXPENSE->value;
            $amount = MoneyAmount::decimal($amountCents);

            $this->counterpartyShadow->withoutIntermediateEvents(function () use ($transaction, $settlement, $account, $categoryId, $validated, $direction, $amount, $counterparty, $user, $amountCents): void {
                $transaction->forceFill([
                    'user_id' => $account->user_id,
                    'account_id' => $account->id,
                    'category_id' => $categoryId,
                    'transaction_date' => $validated['settled_at'],
                    'value_date' => $validated['settled_at'],
                    'direction' => $direction,
                    'amount' => $amount,
                    'currency' => $account->currency_code,
                    ...$this->transactionExchangeSnapshotService->buildForAccount($account, (float) $amount, $validated['settled_at']),
                    'description' => $validated['reference'] ?: ($settlement->direction === self::COUNTERPARTY_TO_ME ? "Rimborso da {$counterparty->name}" : "Rimborso a {$counterparty->name}"),
                    'notes' => $validated['notes'] ?? null,
                    'updated_by_user_id' => $user->id,
                ])->save();
                $settlement->forceFill(['amount_cents' => $amountCents, 'settled_at' => $validated['settled_at'], 'notes' => $validated['notes'] ?? null])->save();

            });
            $this->counterpartyShadow->changed($settlement);

            $this->transactionMutationService->recalculateAccount($previousAccount);
            if ($previousAccount->id !== $account->id) {
                $this->transactionMutationService->recalculateAccount($account);
            }

            return $settlement->fresh('transaction');
        });
    }

    public function delete(User $user, SharedExpenseSettlement $settlement): void
    {
        abort_unless($settlement->user_id === $user->id, 404);

        DB::transaction(function () use ($user, $settlement): void {
            $settlement = SharedExpenseSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            TrackedItem::query()->whereKey($settlement->counterparty_id)->lockForUpdate()->firstOrFail();
            $transaction = Transaction::withTrashed()->findOrFail($settlement->transaction_id);
            $settlement->delete();
            if (! $transaction->trashed()) {
                $this->transactionMutationService->destroy($user, $transaction);
            }
        });
    }

    private function ensureSettlementCurrency(Account $account): void
    {
        if ($account->currency_code !== 'EUR') {
            throw ValidationException::withMessages(['account_uuid' => __('credit_debts.validation.settlement_currency_mismatch')]);
        }
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
