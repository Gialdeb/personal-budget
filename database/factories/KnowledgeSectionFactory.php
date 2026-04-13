<?php

namespace Database\Factories;

use App\Models\KnowledgeSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeSection>
 */
class KnowledgeSectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'slug' => fake()->unique()->slug(2),
            'sort_order' => fake()->numberBetween(1, 50),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => [
            'is_published' => false,
        ]);
    }
}
