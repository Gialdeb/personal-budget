<?php

use App\Enums\UserStatusEnum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin can ban a normal user', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create(['email' => 'user@example.com']);
    $target->assignRole('user');

    $this->actingAs($admin)
        ->patch(route('admin.users.ban', $target), [
            'reason' => 'Violazione regole',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($target->fresh()->status)->toBe(UserStatusEnum::BANNED->value)
        ->and($target->fresh()->status_reason)->toBe('Violazione regole');

    expect(Activity::query()->latest()->first()?->description)->toBe('user.banned');
});

test('admin can suspend a normal user', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create();
    $target->assignRole('user');

    $this->actingAs($admin)
        ->patch(route('admin.users.suspend', $target), [
            'reason' => 'Controllo manuale',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($target->fresh()->status)->toBe(UserStatusEnum::SUSPENDED->value);
});

test('admin can reactivate a suspended or banned user', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create([
        'status' => UserStatusEnum::BANNED->value,
        'status_reason' => 'Test',
    ]);
    $target->assignRole('user');

    $this->actingAs($admin)
        ->patch(route('admin.users.reactivate', $target), [])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($target->fresh()->status)->toBe(UserStatusEnum::ACTIVE->value)
        ->and($target->fresh()->status_reason)->toBeNull();
});

test('admin cannot ban another admin', function () {
    $admin = User::factory()->create(['email' => 'admin1@example.com']);
    $admin->syncRoles(['user', 'admin']);

    $targetAdmin = User::factory()->create(['email' => 'admin2@example.com']);
    $targetAdmin->syncRoles(['user', 'admin']);

    $this->actingAs($admin)
        ->patch(route('admin.users.ban', $targetAdmin), [
            'reason' => 'Forbidden',
        ])
        ->assertSessionHasErrors('user');

    expect($targetAdmin->fresh()->status)->toBe(UserStatusEnum::ACTIVE->value);
});

test('admin can sync roles for non admin users using only allowed roles', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $target = User::factory()->create();
    $target->assignRole('user');

    $this->actingAs($admin)
        ->patch(route('admin.users.roles.update', $target), [
            'roles' => ['staff'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($target->fresh()->hasRole('staff'))->toBeTrue()
        ->and($target->fresh()->hasRole('user'))->toBeFalse();

    expect(Activity::query()->latest()->first()?->description)->toBe('user.roles_synced');
});

test('admin cannot update roles of another admin', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $targetAdmin = User::factory()->create();
    $targetAdmin->syncRoles(['user', 'admin']);

    $this->actingAs($admin)
        ->patch(route('admin.users.roles.update', $targetAdmin), [
            'roles' => ['staff'],
        ])
        ->assertSessionHasErrors('user');

    expect($targetAdmin->fresh()->hasRole('admin'))->toBeTrue();
});
