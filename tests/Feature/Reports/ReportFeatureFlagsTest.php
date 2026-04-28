<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('reports are enabled by default in the test environment', function () {
    expect(config('features.reports.enabled'))->toBeTrue()
        ->and(config('features.reports.sections.kpis'))->toBeTrue()
        ->and(config('features.reports.sections.categories'))->toBeTrue()
        ->and(config('features.reports.sections.category_analysis'))->toBeTrue()
        ->and(config('features.reports.sections.accounts'))->toBeTrue();
});

test('disabling reports hides only the reports area while budget planning remains available', function () {
    config()->set('features.reports.enabled', false);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $user->assignRole('user');

    createTestAccount($user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('features.reports_enabled', false));

    $this->actingAs($user)
        ->get(route('reports'))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('reports.kpis'))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('budget-planning'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/Planning'));
});

test('disabling a report section does not affect budget planning or the remaining report sections', function () {
    config()->set('features.reports.enabled', true);
    config()->set('features.reports.sections.accounts', false);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $user->assignRole('user');

    createTestAccount($user);

    $this->actingAs($user)
        ->get(route('reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('reportSections', fn ($sections) => collect($sections)
                ->pluck('key')
                ->all() === ['kpis', 'categories', 'category_analysis']));

    $this->actingAs($user)
        ->get(route('reports.accounts'))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('budget-planning'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/Planning'));
});
