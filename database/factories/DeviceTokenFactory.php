<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => 'fcm-token-'.$this->faker->uuid(),
            'platform' => 'web',
            'locale' => $this->faker->randomElement(['it', 'en']),
            'is_active' => true,
            'last_seen_at' => now(),
        ];
    }
}
