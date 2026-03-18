<?php

namespace Database\Seeders;

use App\Models\Scope;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultScopeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $scopes = [
            ['name' => 'Personale', 'type' => 'personal', 'color' => '#3B82F6'],
            ['name' => 'Casa 1', 'type' => 'home', 'color' => '#10B981'],
            ['name' => 'Casa 2', 'type' => 'home', 'color' => '#F59E0B'],
        ];

        foreach ($scopes as $scope) {
            Scope::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $scope['name'],
                ],
                [
                    'type' => $scope['type'],
                    'color' => $scope['color'],
                    'is_active' => true,
                ]
            );
        }
    }
}
