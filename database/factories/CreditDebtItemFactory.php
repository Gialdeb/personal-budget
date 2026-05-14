<?php

namespace Database\Factories;

use App\Enums\CreditDebtTypeEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\CreditDebtItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditDebtItem>
 */
class CreditDebtItemFactory extends Factory
{
    protected $model = CreditDebtItem::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'reference_id' => null,
            'account_id' => null,
            'category_id' => null,
            'type' => CreditDebtTypeEnum::CREDIT->value,
            'description' => $this->faker->sentence(3),
            'total_amount' => '100.00',
            'currency_code' => 'EUR',
            'due_date' => now()->addDays(7)->toDateString(),
            'note' => null,
        ];
    }

    public function debit(): self
    {
        return $this->state(fn (): array => [
            'type' => CreditDebtTypeEnum::DEBIT->value,
        ]);
    }

    public function forAccount(Account $account): self
    {
        return $this->state(fn (): array => [
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'currency_code' => $account->currency_code,
        ]);
    }

    public function forCategory(Category $category): self
    {
        return $this->state(fn (): array => [
            'user_id' => $category->user_id,
            'category_id' => $category->id,
        ]);
    }
}
