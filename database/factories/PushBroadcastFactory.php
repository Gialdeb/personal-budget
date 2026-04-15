<?php

namespace Database\Factories;

use App\Models\PushBroadcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushBroadcast>
 */
class PushBroadcastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'status' => 'queued',
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->sentence(10),
            'url' => $this->faker->optional()->url(),
            'eligible_users_count' => 0,
            'target_tokens_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'invalidated_count' => 0,
            'payload_snapshot' => null,
            'error_message' => null,
            'queued_at' => now(),
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
