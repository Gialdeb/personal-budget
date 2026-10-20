<?php

namespace Database\Factories;

use App\Models\CompoundRecurringEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompoundRecurringEntry> */
class CompoundRecurringEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(2, true), 'currency' => 'EUR', 'direction' => 'expense'];
    }
}
