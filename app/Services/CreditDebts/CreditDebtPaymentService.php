<?php

namespace App\Services\CreditDebts;

use App\Enums\CreditDebtTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Models\Account;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Counterparties\CounterpartyAuthority;
use App\Services\Counterparties\ManualCreditDebtShadowService;
use App\Services\Transactions\TransactionMutationService;
use App\Support\MoneyAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditDebtPaymentService
{
    public function __construct(
        private readonly TransactionMutationService $transactionMutationService,
        private readonly ManualCreditDebtShadowService $shadow,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, CreditDebtItem $item, array $data): CreditDebtPayment
    {
        if (app(CounterpartyAuthority::class)->activeRelation($user->id, $item->counterparty_id, $item->currency_code) !== null) {
            throw ValidationException::withMessages(['counterparty' => __('form_errors.use_record_payment_in_the_counterparty_relationship')]);
        }
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        $this->ensureItemOwnedBy($user, $item);
        $item = $item->fresh();

        $amount = MoneyAmount::decimal(MoneyAmount::cents((string) $data['amount']));

        return DB::transaction(function () use ($user, $item, $data, $amount): CreditDebtPayment {
            if ($item->counterparty_id !== null) {
                TrackedItem::query()->whereKey($item->counterparty_id)->lockForUpdate()->firstOrFail();
            }
            $lockedItem = CreditDebtItem::query()
                ->forUser($user)
                ->lockForUpdate()
                ->findOrFail($item->id);

            app(ManualEconomicSubjectResolver::class)->assertConsistent($lockedItem);

            if ((string) $lockedItem->counterparty_id !== (string) $item->counterparty_id) {
                throw ValidationException::withMessages(['counterparty' => __('form_errors.the_counterparty_has_changed_reload_the_position_before_recording_the_payment')]);
            }
            if (app(CounterpartyAuthority::class)->activeRelation($user->id, $lockedItem->counterparty_id, $lockedItem->currency_code) !== null) {
                throw ValidationException::withMessages(['counterparty' => __('form_errors.use_record_payment_in_the_counterparty_relationship')]);
            }
            if (! $lockedItem->canAcceptPayment($amount)) {
                throw ValidationException::withMessages([
                    'amount' => __('credit_debts.validation.payment_exceeds_remaining'),
                ]);
            }

            $account = Account::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail((int) $data['account_id']);

            if ($account->currency_code !== $lockedItem->currency_code) {
                throw ValidationException::withMessages([
                    'account_id' => __('credit_debts.validation.account_currency_mismatch'),
                ]);
            }

            $trackedItemId = $lockedItem->reference_id === null
                ? null
                : TrackedItem::query()
                    ->whereKey($lockedItem->reference_id)
                    ->lockForUpdate()
                    ->value('id');
            $direction = $lockedItem->type === CreditDebtTypeEnum::CREDIT
                ? TransactionDirectionEnum::INCOME->value
                : TransactionDirectionEnum::EXPENSE->value;
            $descriptionPrefix = $lockedItem->type === CreditDebtTypeEnum::CREDIT
                ? __('ui.credit_collection_5097476f')
                : __('ui.debt_payment_bcf60b33');

            $transaction = $this->transactionMutationService->storeGeneratedStandardTransaction(
                user: $user,
                account: $account,
                direction: $direction,
                amount: (float) $amount,
                transactionDate: (string) $data['paid_at'],
                categoryId: $lockedItem->category_id,
                trackedItemId: $trackedItemId,
                description: "{$descriptionPrefix}: {$lockedItem->description}",
                notes: $data['note'] ?? null,
            );

            $payment = CreditDebtPayment::query()->create([
                'user_id' => $user->id,
                'credit_debt_item_id' => $lockedItem->id,
                'transaction_id' => $transaction->id,
                'account_id' => $account->id,
                'amount' => $amount,
                'currency_code' => $lockedItem->currency_code,
                'paid_at' => $data['paid_at'],
                'note' => $data['note'] ?? null,
            ]);

            $this->shadow->paymentCreated($payment);

            return $payment->fresh(['account', 'transaction']);
        });
    }

    public function delete(User $user, CreditDebtPayment $payment): void
    {
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        $this->ensurePaymentOwnedBy($user, $payment);
        DB::transaction(function () use ($user, $payment): void {
            CreditDebtItem::query()->forUser($user)->whereKey($payment->credit_debt_item_id)->lockForUpdate()->firstOrFail();
            $payment = CreditDebtPayment::query()->forUser($user)->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $latestPaymentId = CreditDebtPayment::query()
                ->where('credit_debt_item_id', $payment->credit_debt_item_id)
                ->latestFirst()->value('id');
            if ($latestPaymentId !== $payment->id) {
                throw ValidationException::withMessages(['payment' => __('credit_debts.validation.delete_latest_payment_required')]);
            }

            $this->shadow->paymentDeleted($payment);
            $this->transactionMutationService->forceDeleteCreditDebtPaymentTransaction($user, $payment);
            $payment->delete();
        });
    }

    private function ensureItemOwnedBy(User $user, CreditDebtItem $item): void
    {
        if ($item->user_id !== $user->id) {
            abort(404);
        }
    }

    private function ensurePaymentOwnedBy(User $user, CreditDebtPayment $payment): void
    {
        if ($payment->user_id !== $user->id) {
            abort(404);
        }
    }
}
