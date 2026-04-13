<?php

namespace Database\Factories;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequest>
 */
class SupportRequestFactory extends Factory
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
            'category' => fake()->randomElement(SupportRequest::categories()),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraphs(2, true),
            'locale' => fake()->randomElement(['it', 'en']),
            'source_url' => fake()->optional()->url(),
            'source_route' => fake()->optional()->randomElement([
                'help-center.index',
                'help-center.sections.show',
                'help-center.articles.show',
            ]),
            'status' => SupportRequest::STATUS_NEW,
            'meta' => null,
        ];
    }
}
