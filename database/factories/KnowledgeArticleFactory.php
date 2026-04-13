<?php

namespace Database\Factories;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeArticle>
 */
class KnowledgeArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'section_id' => KnowledgeSection::factory(),
            'slug' => fake()->unique()->slug(3),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_published' => true,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }
}
