<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'base_currency_code' => 'EUR',
            'quote_currency_code' => fake()->randomElement(['USD', 'GBP', 'CHF']),
            'rate' => fake()->randomFloat(8, 0.5, 2.0),
            'rate_date' => now()->subDay()->toDateString(),
            'source' => fake()->randomElement(['frankfurter', 'fawaz']),
            'fetched_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ];
    }
}
