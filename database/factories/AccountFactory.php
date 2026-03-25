<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Scope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_id' => Bank::query()->value('id') ?? Bank::factory(),
            'account_type_id' => AccountType::query()->value('id') ?? AccountType::factory(),
            'scope_id' => Scope::query()->value('id') ?? Scope::factory(),
            'name' => $this->faker->words(2, true),
            'iban' => strtoupper(Str::random(27)),
            'account_number_masked' => '****'.(string) $this->faker->numberBetween(1000, 9999),
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'opening_balance' => 0,
            'current_balance' => 0,
            'opening_balance_date' => now()->toDateString(),
            'is_manual' => true,
            'is_active' => true,
            'notes' => null,
            'settings' => null,
            'user_bank_id' => null,
            'uuid' => (string) Str::uuid(),
            'household_id' => null,
        ];
    }
}
