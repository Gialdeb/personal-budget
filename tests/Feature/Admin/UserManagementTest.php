<?php

use App\Enums\UserStatusEnum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin can access admin users page', function () {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
        'status' => UserStatusEnum::ACTIVE->value,
        'plan_code' => 'free',
        'subscription_status' => 'active',
        'is_impersonable' => true,
    ]);
    $target->assignRole('user');

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users')
            ->where('filters.role', 'all')
            ->where('filters.status', 'all')
            ->where('filters.plan', 'all')
            ->where('users.data.0.email', fn ($email) => is_string($email))
        );
});

test('normal user cannot access admin users page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertForbidden();
});

test('admin users page can filter by role', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->syncRoles(['user', 'admin']);

    $staff = User::factory()->create(['email' => 'staff@example.com']);
    $staff->assignRole('staff');

    $normal = User::factory()->create(['email' => 'user@example.com']);
    $normal->assignRole('user');

    $this->actingAs($admin)
        ->get(route('admin.users', ['role' => 'staff']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.role', 'staff')
            ->where('users.data', fn ($users) => collect($users)->every(
                fn ($user) => in_array('staff', $user['roles'], true)
            ))
        );
});

test('admin users page can filter by status', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->syncRoles(['user', 'admin']);

    $active = User::factory()->create([
        'email' => 'active@example.com',
        'status' => UserStatusEnum::ACTIVE->value,
    ]);
    $active->assignRole('user');

    $banned = User::factory()->create([
        'email' => 'banned@example.com',
        'status' => UserStatusEnum::BANNED->value,
    ]);
    $banned->assignRole('user');

    $this->actingAs($admin)
        ->get(route('admin.users', ['status' => UserStatusEnum::BANNED->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', UserStatusEnum::BANNED->value)
            ->where('users.data', fn ($users) => collect($users)->every(
                fn ($user) => $user['status'] === UserStatusEnum::BANNED->value
            ))
        );
});

test('admin payload disables sensitive actions for admin accounts', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->syncRoles(['user', 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.users', ['search' => 'admin@example.com']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.email', 'admin@example.com')
            ->where('users.data.0.can_impersonate', false)
            ->where('users.data.0.can_ban', false)
            ->where('users.data.0.can_suspend', false)
            ->where('users.data.0.can_manage_roles', false)
            ->where('users.data.0.can_delete', false)
        );
});
