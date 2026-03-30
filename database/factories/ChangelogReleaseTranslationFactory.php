<?php

namespace Database\Factories;

use App\Models\ChangelogRelease;
use App\Models\ChangelogReleaseTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangelogReleaseTranslation>
 */
class ChangelogReleaseTranslationFactory extends Factory
{
    protected $model = ChangelogReleaseTranslation::class;

    public function definition(): array
    {
        return [
            'release_id' => ChangelogRelease::factory(),
            'locale' => 'it',
            'title' => fake()->sentence(4),
            'summary' => '<p>'.fake()->sentence(8).'</p>',
            'excerpt' => fake()->sentence(6),
        ];
    }
}
