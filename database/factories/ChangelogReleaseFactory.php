<?php

namespace Database\Factories;

use App\Models\ChangelogRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangelogRelease>
 */
class ChangelogReleaseFactory extends Factory
{
    protected $model = ChangelogRelease::class;

    public function definition(): array
    {
        $minor = fake()->unique()->numberBetween(1, 50);

        return [
            'version_label' => "0.{$minor}.0-beta",
            'version_major' => 0,
            'version_minor' => $minor,
            'version_patch' => 0,
            'version_suffix' => 'beta',
            'channel' => 'beta',
            'is_published' => false,
            'is_pinned' => false,
            'published_at' => null,
            'sort_order' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
