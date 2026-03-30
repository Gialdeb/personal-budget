<?php

namespace Database\Factories;

use App\Models\ChangelogItem;
use App\Models\ChangelogSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangelogItem>
 */
class ChangelogItemFactory extends Factory
{
    protected $model = ChangelogItem::class;

    public function definition(): array
    {
        return [
            'section_id' => ChangelogSection::factory(),
            'sort_order' => 1,
            'screenshot_key' => null,
            'link_url' => null,
            'link_label' => null,
            'item_type' => null,
            'platform' => null,
        ];
    }
}
