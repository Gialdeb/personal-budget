<?php

namespace App\Services\CreditDebts;

use App\Models\CreditDebtItem;
use App\Models\User;
use App\Services\Counterparties\ManualCreditDebtShadowService;
use App\Support\MoneyAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditDebtItemService
{
    public function __construct(private readonly ManualCreditDebtShadowService $shadow) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): CreditDebtItem
    {
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        unset($data['counterparty_uuid']);

        return DB::transaction(function () use ($user, $data): CreditDebtItem {
            $item = CreditDebtItem::query()->create([...$data, 'user_id' => $user->id]);
            $this->shadow->itemCreated($item);

            return $item->fresh(['account', 'category', 'counterparty']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, CreditDebtItem $item, array $data): CreditDebtItem
    {
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        $this->ensureOwnedBy($user, $item);

        return DB::transaction(function () use ($user, $item, $data): CreditDebtItem {
            $item = CreditDebtItem::query()->forUser($user)->lockForUpdate()->findOrFail($item->id);
            unset($data['counterparty_uuid']);

            if ($item->payments()->exists()) {
                if (array_key_exists('counterparty_id', $data) && ($data['counterparty_id'] === null ? null : (int) $data['counterparty_id']) !== $item->counterparty_id) {
                    throw ValidationException::withMessages(['counterparty_id' => __('credit_debts.validation.locked_with_payments')]);
                }
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

                if (array_key_exists('total_amount', $data) && MoneyAmount::cents((string) $data['total_amount']) !== MoneyAmount::cents((string) $item->total_amount)) {
                    throw ValidationException::withMessages([
                        'total_amount' => __('credit_debts.validation.total_locked_with_payments'),
                    ]);
                }
            }

            $item->fill($data)->save();
            $this->shadow->itemChanged($item);

            return $item->fresh(['account', 'category', 'counterparty']);
        });
    }

    public function delete(User $user, CreditDebtItem $item): void
    {
        abort_unless((bool) config('features.credits_debts.enabled'), 404);

        $this->ensureOwnedBy($user, $item);
        DB::transaction(function () use ($user, $item): void {
            $item = CreditDebtItem::query()->forUser($user)->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $paymentsCount = $item->payments()->count();
            if ($paymentsCount > 0) {
                throw ValidationException::withMessages([
                    'credit_debt_item' => __('credit_debts.validation.delete_item_with_payments', ['count' => $paymentsCount]),
                ]);
            }

            $item->delete();
            $this->shadow->itemChanged($item, deleted: true);
        });
    }

    private function ensureOwnedBy(User $user, CreditDebtItem $item): void
    {
        if ($item->user_id !== $user->id) {
            abort(404);
        }
    }
}
