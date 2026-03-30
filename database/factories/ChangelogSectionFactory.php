<?php

namespace Database\Factories;

use App\Models\ChangelogRelease;
use App\Models\ChangelogSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangelogSection>
 */
class ChangelogSectionFactory extends Factory
{
    protected $model = ChangelogSection::class;

    public function definition(): array
    {
        return [
            'release_id' => ChangelogRelease::factory(),
            'key' => fake()->unique()->slug(2),
            'sort_order' => 1,
        ];
    }
}
