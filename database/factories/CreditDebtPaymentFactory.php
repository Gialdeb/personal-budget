<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditDebtPayment>
 */
class CreditDebtPaymentFactory extends Factory
{
    protected $model = CreditDebtPayment::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'credit_debt_item_id' => CreditDebtItem::factory(),
            'transaction_id' => null,
            'account_id' => Account::factory(),
            'amount' => '25.00',
            'currency_code' => 'EUR',
            'paid_at' => now()->toDateString(),
            'note' => null,
        ];
    }

    public function forItem(CreditDebtItem $item, Account $account): self
    {
        return $this->state(fn (): array => [
            'user_id' => $item->user_id,
            'credit_debt_item_id' => $item->id,
            'account_id' => $account->id,
            'currency_code' => $item->currency_code,
        ]);
    }
}
