<?php

namespace Database\Factories;

use App\Models\ContextualHelpEntry;
use App\Models\KnowledgeArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContextualHelpEntry>
 */
class ContextualHelpEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'page_key' => $this->faker->unique()->slug(2),
            'knowledge_article_id' => null,
            'sort_order' => $this->faker->numberBetween(1, 20),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
        ]);
    }

    public function forKnowledgeArticle(?KnowledgeArticle $knowledgeArticle = null): static
    {
        return $this->state(fn (): array => [
            'knowledge_article_id' => $knowledgeArticle?->id ?? KnowledgeArticle::factory(),
        ]);
    }
}
