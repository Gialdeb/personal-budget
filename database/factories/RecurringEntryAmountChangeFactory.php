<?php

namespace Database\Factories;

use App\Models\RecurringEntryAmountChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringEntryAmountChange>
 */
class RecurringEntryAmountChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'previous_amount' => '29.99',
            'new_amount' => '34.99',
            'effective_from' => today()->addDay(),
            'currency' => 'EUR',
        ];
    }
}
