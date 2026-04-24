<?php

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('login payload keeps only the minimal shared props', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->has('auth')
            ->has('flash')
            ->has('locale')
            ->missing('app')
            ->missing('analytics')
            ->missing('notificationInbox')
            ->missing('publicSeo')
            ->missing('sessionWarning')
            ->missing('settingsNavigation')
            ->missing('sidebarOpen')
            ->missing('transactionsNavigation'));
});

test('public pages receive only public seo and analytics shared props', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('locale')
            ->has('analytics')
            ->has('publicSeo')
            ->missing('app')
            ->missing('notificationInbox')
            ->missing('sessionWarning')
            ->missing('settingsNavigation')
            ->missing('sidebarOpen')
            ->missing('transactionsNavigation'));
});

test('authenticated app shell routes receive the shared props they need', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);
    $category = Category::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'parent_id' => null,
        'name' => 'Utilities',
        'slug' => 'utilities',
        'foundation_key' => null,
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'color' => '#2563eb',
        'icon' => 'zap',
        'sort_order' => 1,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('app')
            ->where('features.reports_enabled', true)
            ->has('notificationInbox')
            ->has('sessionWarning')
            ->has('sidebarOpen')
            ->has('entrySearch.account_options')
            ->has('entrySearch.category_options')
            ->where('entrySearch.account_options.0.value', $account->uuid)
            ->where('entrySearch.category_options.0.value', $category->uuid)
            ->where('entrySearch.category_options.0.full_path', 'Utilities')
            ->where('entrySearch.category_options.0.icon', 'zap')
            ->has('transactionsNavigation')
            ->missing('analytics')
            ->missing('publicSeo')
            ->missing('settingsNavigation'));
});

test('reports route receives app shell shared props and report payload', function () {
    $user = User::factory()->create();
    createTestAccount($user);

    $this->actingAs($user)
        ->get(route('reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->has('app')
            ->where('features.reports_enabled', true)
            ->has('notificationInbox')
            ->has('sessionWarning')
            ->has('sidebarOpen')
            ->has('transactionsNavigation')
            ->has('reportContext')
            ->has('reportSections')
            ->missing('analytics')
            ->missing('publicSeo')
            ->missing('settingsNavigation'));
});

test('reports kpis route receives report analytics payload alongside app shell shared props', function () {
    $user = User::factory()->create();
    createTestAccount($user);

    $this->actingAs($user)
        ->get(route('reports.kpis'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Overview')
            ->has('app')
            ->has('notificationInbox')
            ->has('sessionWarning')
            ->has('sidebarOpen')
            ->has('transactionsNavigation')
            ->has('reportContext')
            ->has('reportSections')
            ->has('reportOverview.meta')
            ->has('reportOverview.filters')
            ->has('reportOverview.kpis')
            ->has('reportOverview.trend')
            ->has('reportOverview.comparison')
            ->missing('analytics')
            ->missing('publicSeo')
            ->missing('settingsNavigation'));
});

test('admin routes do not receive shared entry search payload', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Index')
            ->missing('entrySearch'));
});

test('settings routes receive settings navigation without public analytics payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->has('app')
            ->has('notificationInbox')
            ->has('sessionWarning')
            ->has('settingsNavigation')
            ->missing('analytics')
            ->missing('publicSeo'));
});
