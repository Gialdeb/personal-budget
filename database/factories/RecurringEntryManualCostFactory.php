<?php

namespace Database\Factories;

use App\Models\RecurringEntryManualCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecurringEntryManualCost> */
class RecurringEntryManualCostFactory extends Factory
{
    public function definition(): array
    {
        return ['date' => fake()->dateTimeBetween('-5 years', '-1 day')->format('Y-m-d'), 'amount' => fake()->randomFloat(2, 1, 500), 'currency' => 'EUR'];
    }
}
