<?php

namespace Database\Factories;

use App\Models\CounterpartyRelation;
use App\Models\TrackedItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CounterpartyRelation> */
class CounterpartyRelationFactory extends Factory
{
    protected $model = CounterpartyRelation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'counterparty_id' => fn (array $attributes): int => TrackedItem::query()->create([
                'user_id' => $attributes['user_id'], 'name' => fake()->name(), 'slug' => fake()->uuid(),
                'is_active' => true, 'is_shared_expense_counterpart' => true,
            ])->id,
            'currency_code' => 'EUR', 'minor_unit' => 2, 'migration_state' => 'shadow',
        ];
    }
}
