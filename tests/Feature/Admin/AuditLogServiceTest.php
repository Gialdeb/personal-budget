<?php

use App\Models\User;
use App\Services\Audit\AuditLogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('it logs role assignment', function () {
    $causer = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
    $causer->assignRole('admin');

    $target = User::factory()->create([
        'email' => 'user@example.com',
    ]);
    $target->assignRole('user');

    app(AuditLogService::class)->roleAssigned($causer, $target, 'admin');

    $activity = Activity::query()->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('users')
        ->and($activity->description)->toBe('user.role_assigned')
        ->and($activity->causer_id)->toBe($causer->id)
        ->and($activity->subject_id)->toBe($target->id)
        ->and($activity->properties['role'])->toBe('admin')
        ->and($activity->properties['target_user_email'])->toBe('user@example.com');
});

test('it logs impersonation start', function () {
    $causer = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
    $causer->assignRole('admin');

    $target = User::factory()->create([
        'email' => 'user@example.com',
    ]);
    $target->assignRole('user');

    app(AuditLogService::class)->impersonationStarted($causer, $target);

    $activity = Activity::query()->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('admin')
        ->and($activity->description)->toBe('user.impersonation_started')
        ->and($activity->causer_id)->toBe($causer->id)
        ->and($activity->subject_id)->toBe($target->id)
        ->and($activity->properties['target_user_email'])->toBe('user@example.com');
});
