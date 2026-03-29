<?php

use App\Models\User;
use App\Support\Sentinel\HorizonDriver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sentinel\Drivers\Laravel as LaravelSentinelDriver;
use Laravel\Sentinel\SentinelManager;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin can access the admin area', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Index')
            ->where('auth.user.is_admin', true));
});

test('normal user cannot access the admin area', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertForbidden();
});

test('guest cannot access the admin area', function () {
    $this->get(route('admin.index'))
        ->assertRedirect(route('login'));
});

test('seeded admin user receives both user and admin roles', function () {
    $adminUser = User::query()->forceCreate([
        'id' => 1,
        'name' => 'Admin',
        'surname' => 'User',
        'email' => 'admin@admin.it',
        'password' => bcrypt('password'),
    ]);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($adminUser->hasRole('admin'))->toBeTrue()
        ->and($adminUser->hasRole('user'))->toBeTrue();
});

test('shared auth user marks non admin accounts correctly', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.is_admin', false));
});

test('admin can access settings and core management areas', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('imports.index'))
        ->assertOk();
});

test('admin can access horizon dashboard in local environment', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get('/horizon')
        ->assertOk();
});

test('horizon sentinel context uses the dedicated driver instead of the generic local proxy block', function () {
    Request::setTrustedProxies(
        ['127.0.0.1'],
        Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO,
    );

    $request = Request::create('https://soamco.lo/horizon', 'GET', server: [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '8.8.8.8',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'soamco.lo',
    ]);

    $localApp = mock(Application::class);
    $localApp->shouldReceive('environment')->with('local')->andReturn(true);

    $genericDriver = new LaravelSentinelDriver(fn () => $localApp);
    $horizonDriver = app(SentinelManager::class)->driverOrFallback('horizon');

    expect($genericDriver->authorize($request))->toBeFalse()
        ->and($horizonDriver)->toBeInstanceOf(HorizonDriver::class)
        ->and($horizonDriver->authorize($request))->toBeTrue();
});

test('user can access dashboard but not admin only areas', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('imports.index'))
        ->assertOk();
});
