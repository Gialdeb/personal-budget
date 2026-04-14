<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\UserProvisioningService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'info@giuseppealessandrodeblasio.it'],
            [
                'name' => 'Giuseppe A.',
                'surname' => 'De Blasio',
                'password' => Hash::make('Admin@123!'),
                'base_currency_code' => 'EUR',
                'locale' => 'it',
                'format_locale' => 'it-IT',
                'email_verified_at' => Carbon::now(),
            ],
        );

        app(UserProvisioningService::class)->provisionApplicationUser($admin, 'it', 'it-IT');

        $admin->syncRoles(['user', 'admin']);
    }
}
