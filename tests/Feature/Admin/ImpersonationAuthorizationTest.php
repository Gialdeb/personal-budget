<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin can impersonate a user who gave consent', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create([
        'email' => 'user@example.com',
        'is_impersonable' => true,
    ]);
    $target->assignRole('user');

    expect($admin->canImpersonate())->toBeTrue()
        ->and($target->canBeImpersonated())->toBeTrue();
});

test('admin cannot impersonate a user who did not give consent', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create([
        'email' => 'user@example.com',
        'is_impersonable' => false,
    ]);
    $target->assignRole('user');

    expect($admin->canImpersonate())->toBeTrue()
        ->and($target->canBeImpersonated())->toBeFalse();
});

test('admin cannot impersonate another admin even if consent flag is true', function () {
    $admin = User::factory()->create(['email' => 'admin1@example.com']);
    $admin->syncRoles(['user', 'admin']);

    $targetAdmin = User::factory()->create([
        'email' => 'admin2@example.com',
        'is_impersonable' => true,
    ]);
    $targetAdmin->syncRoles(['user', 'admin']);

    expect($admin->canImpersonate())->toBeTrue()
        ->and($targetAdmin->canBeImpersonated())->toBeFalse();
});

test('normal user cannot impersonate others', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);
    $user->assignRole('user');

    expect($user->canImpersonate())->toBeFalse();
});

test('admin can leave impersonation even when current impersonated user is not admin', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create([
        'email' => 'user@example.com',
        'is_impersonable' => true,
    ]);
    $target->assignRole('user');

    $this->actingAs($admin)
        ->get(route('admin.impersonate', ['id' => $target->id]))
        ->assertRedirect();

    expect(auth()->user()?->is($target))->toBeTrue();

    $this->get(route('admin.impersonate.leave'))
        ->assertRedirect();

    expect(auth()->user()?->is($admin))->toBeTrue();
});
