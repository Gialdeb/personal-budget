<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['admin', 'staff', 'user'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $adminUser = User::query()->where('email', 'admin@admin.it')->first();

        if ($adminUser) {
            $adminUser->syncRoles(['user', 'admin']);
        }
    }
}
