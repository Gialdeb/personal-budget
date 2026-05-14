<?php

namespace App\Services\CreditDebts;

use App\Enums\CreditDebtTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Models\Account;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transactions\TransactionMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditDebtPaymentService
{
    public function __construct(
        private readonly TransactionMutationService $transactionMutationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, CreditDebtItem $item, array $data): CreditDebtPayment
    {
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        $this->ensureItemOwnedBy($user, $item);

        $amount = round((float) $data['amount'], 2);

        if (! $item->canAcceptPayment($amount)) {
            throw ValidationException::withMessages([
                'amount' => __('credit_debts.validation.payment_exceeds_remaining'),
            ]);
        }

        $account = Account::query()
            ->where('user_id', $user->id)
            ->findOrFail((int) $data['account_id']);

        if ($account->currency_code !== $item->currency_code) {
            throw ValidationException::withMessages([
                'account_id' => __('credit_debts.validation.account_currency_mismatch'),
            ]);
        }

        return DB::transaction(function () use ($user, $item, $data, $amount, $account): CreditDebtPayment {
            $direction = $item->type === CreditDebtTypeEnum::CREDIT
                ? TransactionDirectionEnum::INCOME->value
                : TransactionDirectionEnum::EXPENSE->value;
            $descriptionPrefix = $item->type === CreditDebtTypeEnum::CREDIT
                ? 'Incasso credito'
                : 'Pagamento debito';

            $transaction = $this->transactionMutationService->storeGeneratedStandardTransaction(
                user: $user,
                account: $account,
                direction: $direction,
                amount: $amount,
                transactionDate: (string) $data['paid_at'],
                categoryId: $item->category_id,
                trackedItemId: $item->reference_id,
                description: "{$descriptionPrefix}: {$item->description}",
                notes: $data['note'] ?? null,
            );

            $payment = CreditDebtPayment::query()->create([
                'user_id' => $user->id,
                'credit_debt_item_id' => $item->id,
                'transaction_id' => $transaction->id,
                'account_id' => $account->id,
                'amount' => $amount,
                'currency_code' => $item->currency_code,
                'paid_at' => $data['paid_at'],
                'note' => $data['note'] ?? null,
            ]);

            return $payment->fresh(['account', 'transaction']);
        });
    }

    public function delete(User $user, CreditDebtPayment $payment): void
    {
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        $this->ensurePaymentOwnedBy($user, $payment);
        $payment->loadMissing('item', 'transaction');

        $latestPaymentId = CreditDebtPayment::query()
            ->where('credit_debt_item_id', $payment->credit_debt_item_id)
            ->latestFirst()
            ->value('id');

        if ($latestPaymentId !== $payment->id) {
            throw ValidationException::withMessages([
                'payment' => __('credit_debts.validation.delete_latest_payment_required'),
            ]);
        }

        DB::transaction(function () use ($user, $payment): void {
            $transaction = $payment->transaction;

            if ($transaction instanceof Transaction) {
                $this->transactionMutationService->destroy($user, $transaction, allowCreditDebtLinked: true);
            }

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
