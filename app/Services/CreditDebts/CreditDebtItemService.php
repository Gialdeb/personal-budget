<?php

namespace App\Services\CreditDebts;

use App\Models\CreditDebtItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreditDebtItemService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): CreditDebtItem
    {
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        return CreditDebtItem::query()->create([
            ...$data,
            'user_id' => $user->id,
        ])->fresh(['account', 'category']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, CreditDebtItem $item, array $data): CreditDebtItem
    {
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        $this->ensureOwnedBy($user, $item);
        $paidAmount = (float) $item->paidAmount();

        if ($item->payments()->exists()) {
            if (array_key_exists('type', $data) && $data['type'] !== $item->type->value) {
                throw ValidationException::withMessages([
                    'type' => __('credit_debts.validation.locked_with_payments'),
                ]);
            }

            if (array_key_exists('currency_code', $data) && $data['currency_code'] !== $item->currency_code) {
                throw ValidationException::withMessages([
                    'currency_code' => __('credit_debts.validation.locked_with_payments'),
                ]);
            }

            if (array_key_exists('total_amount', $data) && round((float) $data['total_amount'], 2) !== round((float) $item->total_amount, 2)) {
                throw ValidationException::withMessages([
                    'total_amount' => __('credit_debts.validation.total_locked_with_payments'),
                ]);
            }
        }

        $item->fill($data)->save();

        return $item->fresh(['account', 'category']);
    }

    public function delete(User $user, CreditDebtItem $item): void
    {
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        $this->ensureOwnedBy($user, $item);
        $paymentsCount = $item->payments()->count();

        if ($paymentsCount > 0) {
            throw ValidationException::withMessages([
                'credit_debt_item' => __('credit_debts.validation.delete_item_with_payments', ['count' => $paymentsCount]),
            ]);
        }

        $item->delete();
    }

    private function ensureOwnedBy(User $user, CreditDebtItem $item): void
    {
        if ($item->user_id !== $user->id) {
            abort(404);
        }
    }
}
