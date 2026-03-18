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
                'settings' => [
                    'dashboard' => [
                        'savings_mode' => 'net_remaining',
                        'visible_boxes' => [
                            'balance' => true,
                            'income' => true,
                            'expense' => true,
                            'budget' => true,
                            'accounts' => true,
                            'recurring' => true,
                            'scheduled' => true,
                            'notifications' => true,
                        ],
                        'visible_charts' => [
                            'trend' => true,
                            'categories' => true,
                            'budget_comparison' => true,
                            'merchant_breakdown' => true,
                        ],
                    ],
                ],
            ]
        );
    }
}
