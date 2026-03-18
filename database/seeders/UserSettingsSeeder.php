<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;

class UserSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        UserSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'active_year' => 2025,
                'base_currency' => 'EUR',
            ]
        );
    }
}
