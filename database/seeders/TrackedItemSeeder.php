<?php

namespace Database\Seeders;

use App\Models\TrackedItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrackedItemSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $vehicles = TrackedItem::updateOrCreate(
            [
                'user_id' => $user->id,
                'slug' => 'veicoli',
            ],
            [
                'parent_id' => null,
                'name' => 'Veicoli',
                'type' => 'group',
                'is_active' => true,
                'settings' => null,
            ]
        );

        $auto = TrackedItem::updateOrCreate(
            [
                'user_id' => $user->id,
                'slug' => 'auto',
            ],
            [
                'parent_id' => $vehicles->id,
                'name' => 'Auto',
                'type' => 'vehicle_group',
                'is_active' => true,
                'settings' => null,
            ]
        );

        $moto = TrackedItem::updateOrCreate(
            [
                'user_id' => $user->id,
                'slug' => 'moto',
            ],
            [
                'parent_id' => $vehicles->id,
                'name' => 'Moto',
                'type' => 'vehicle_group',
                'is_active' => true,
                'settings' => null,
            ]
        );

        $items = [
            [
                'parent_id' => $auto->id,
                'name' => 'Kia',
                'slug' => 'kia',
                'type' => 'car',
            ],
            [
                'parent_id' => $auto->id,
                'name' => 'Smart',
                'slug' => 'smart',
                'type' => 'car',
            ],
            [
                'parent_id' => $moto->id,
                'name' => 'Beverly 400',
                'slug' => 'beverly-400',
                'type' => 'motorcycle',
            ],
            [
                'parent_id' => $moto->id,
                'name' => 'AK500',
                'slug' => 'ak500',
                'type' => 'motorcycle',
            ],
        ];

        foreach ($items as $item) {
            TrackedItem::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'slug' => $item['slug'],
                ],
                [
                    'parent_id' => $item['parent_id'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'is_active' => true,
                    'settings' => null,
                ]
            );
        }
    }
}
