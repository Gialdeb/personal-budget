<?php

namespace Database\Factories;

use App\Models\TrackedItem;
use App\Models\TrackedItemAlias;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrackedItemAlias> */
class TrackedItemAliasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by_user_id' => User::factory(),
            'source_tracked_item_id' => fn (array $attributes): int => TrackedItem::query()->create([
                'user_id' => $attributes['created_by_user_id'], 'name' => fake()->name(), 'slug' => fake()->uuid(), 'is_active' => false,
            ])->id,
            'canonical_tracked_item_id' => fn (array $attributes): int => TrackedItem::query()->create([
                'user_id' => $attributes['created_by_user_id'], 'name' => fake()->name(), 'slug' => fake()->uuid(), 'is_active' => true,
            ])->id,
        ];
    }
}
