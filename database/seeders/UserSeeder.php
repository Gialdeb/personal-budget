<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::truncate();
        User::create([
            'name' => 'admin',
            'surname' => 'admin',
            'email' => 'admin@admin.it',
            'password' => bcrypt('Admin@123'),
            'base_currency_code' => 'EUR',
            'format_locale' => 'it-IT',
            'email_verified_at' => Carbon::now(),
        ]);
    }
}
