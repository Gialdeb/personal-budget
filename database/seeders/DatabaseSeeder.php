<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AccountTypeSeeder::class,
            DefaultBankSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            NotificationTopicSeeder::class,
            CommunicationTemplateSeeder::class,
            CommunicationCategorySeeder::class,
        ]);
    }
}
