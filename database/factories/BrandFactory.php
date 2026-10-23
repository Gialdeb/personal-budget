<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'aliases' => [], 'domain' => fake()->unique()->domainName(), 'category' => null, 'is_published' => false];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }
}
