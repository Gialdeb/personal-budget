<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserYear;
use Illuminate\Database\Seeder;

class UserYearSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $years = [2024, 2025, 2026, 2027];

        foreach ($years as $year) {
            UserYear::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'year' => $year,
                ],
                [
                    'is_closed' => false,
                ]
            );
        }
    }
}
