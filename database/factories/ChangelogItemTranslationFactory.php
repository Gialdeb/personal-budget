<?php

namespace Database\Factories;

use App\Models\ChangelogItem;
use App\Models\ChangelogItemTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangelogItemTranslation>
 */
class ChangelogItemTranslationFactory extends Factory
{
    protected $model = ChangelogItemTranslation::class;

    public function definition(): array
    {
        return [
            'item_id' => ChangelogItem::factory(),
            'locale' => 'it',
            'title' => fake()->sentence(4),
            'body' => '<p>'.fake()->sentence(10).'</p>',
        ];
    }
}
