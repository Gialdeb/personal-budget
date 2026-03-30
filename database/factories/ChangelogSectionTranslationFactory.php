<?php

namespace Database\Factories;

use App\Models\ChangelogSection;
use App\Models\ChangelogSectionTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangelogSectionTranslation>
 */
class ChangelogSectionTranslationFactory extends Factory
{
    protected $model = ChangelogSectionTranslation::class;

    public function definition(): array
    {
        return [
            'section_id' => ChangelogSection::factory(),
            'locale' => 'it',
            'label' => fake()->words(2, true),
        ];
    }
}
