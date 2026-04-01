<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin overview page renders the admin shell', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Index')
            ->where('auth.user.is_admin', true));
});

test('admin users page renders the admin users shell', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users')
            ->where('auth.user.is_admin', true));
});

test('admin user billing page renders the billing shell', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $target = User::factory()->create();

    expect(route('admin.users.billing.show', $target))->toContain('/users/'.$target->uuid.'/billing');

    $this->actingAs($user)
        ->get(route('admin.users.billing.show', $target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/UserBilling')
            ->where('auth.user.is_admin', true)
            ->where('user.uuid', $target->uuid));
});

test('admin user billing page returns not found for numeric user path', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get('/admin/users/1/billing')
        ->assertNotFound();
});

test('admin activity log page renders the admin activity log shell', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.activity-log'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/ActivityLog')
            ->where('auth.user.is_admin', true));
});

test('admin automation page renders the automation shell', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.automation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Automation/Index')
            ->where('auth.user.is_admin', true));
});

test('admin changelog page renders the changelog shell', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.changelog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Changelog/Index')
            ->where('auth.user.is_admin', true));
});

test('admin communication templates page renders the templates shell', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.communication-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommunicationTemplates/Index')
            ->where('auth.user.is_admin', true));
});

test('admin communication categories page renders the category shell', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.communication-categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommunicationCategories/Index')
            ->where('auth.user.is_admin', true));
});

test('admin communication composer page renders the composer shell', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.communications.compose.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Communications/Compose')
            ->where('auth.user.is_admin', true));
});

test('admin outbound history page renders the outbound shell', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.communications.outbound.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Communications/Outbound/Index')
            ->where('auth.user.is_admin', true));
});
