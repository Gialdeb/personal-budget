<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Categories\CategoryFoundationService;
use Illuminate\Database\Seeder;

class DefaultCategorySeeder extends Seeder
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

        app(CategoryFoundationService::class)->ensureForUser($user);
    }
}
