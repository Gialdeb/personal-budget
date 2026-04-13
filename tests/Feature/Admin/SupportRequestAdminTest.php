<?php

use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function supportAdminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function standardUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('user');

    return $user;
}

it('allows admin to access the support requests index and detail pages', function () {
    $admin = supportAdminUser();
    $supportRequest = SupportRequest::factory()->create([
        'subject' => 'Import issue after preview',
        'source_url' => '/help-center/articles/imports',
        'source_route' => 'help-center.articles.show',
        'meta' => ['user_agent' => 'Test Browser'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.support-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/SupportRequests/Index')
            ->where('supportRequests.data.0.subject', 'Import issue after preview'));

    $this->actingAs($admin)
        ->get(route('admin.support-requests.show', $supportRequest->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/SupportRequests/Show')
            ->where('supportRequest.subject', 'Import issue after preview')
            ->where('supportRequest.source_url', '/help-center/articles/imports')
            ->where('supportRequest.source_route', 'help-center.articles.show')
            ->where('supportRequest.meta.user_agent', 'Test Browser'));
});

it('filters support requests by status', function () {
    $admin = supportAdminUser();

    SupportRequest::factory()->create([
        'status' => SupportRequest::STATUS_NEW,
    ]);

    SupportRequest::factory()->create([
        'status' => SupportRequest::STATUS_CLOSED,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.support-requests.index', [
            'status' => SupportRequest::STATUS_CLOSED,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', SupportRequest::STATUS_CLOSED)
            ->where('supportRequests.data', fn ($requests) => collect($requests)->every(
                fn ($request) => $request['status'] === SupportRequest::STATUS_CLOSED
            )));
});

it('filters support requests by category', function () {
    $admin = supportAdminUser();

    SupportRequest::factory()->create([
        'category' => SupportRequest::CATEGORY_BUG,
    ]);

    SupportRequest::factory()->create([
        'category' => SupportRequest::CATEGORY_FEATURE_REQUEST,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.support-requests.index', [
            'category' => SupportRequest::CATEGORY_FEATURE_REQUEST,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.category', SupportRequest::CATEGORY_FEATURE_REQUEST)
            ->where('supportRequests.data', fn ($requests) => collect($requests)->every(
                fn ($request) => $request['category'] === SupportRequest::CATEGORY_FEATURE_REQUEST
            )));
});

it('updates the support request status', function () {
    $admin = supportAdminUser();
    $supportRequest = SupportRequest::factory()->create([
        'status' => SupportRequest::STATUS_NEW,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.support-requests.update', $supportRequest->uuid), [
            'status' => SupportRequest::STATUS_IN_PROGRESS,
        ])
        ->assertRedirect(route('admin.support-requests.show', $supportRequest->uuid))
        ->assertSessionHas('success');

    expect($supportRequest->fresh()->status)->toBe(SupportRequest::STATUS_IN_PROGRESS);
});

it('forbids non admin users from accessing support requests admin pages', function () {
    $user = standardUser();
    $supportRequest = SupportRequest::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.support-requests.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.support-requests.show', $supportRequest->uuid))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('admin.support-requests.update', $supportRequest->uuid), [
            'status' => SupportRequest::STATUS_CLOSED,
        ])
        ->assertForbidden();
});

it('redirects guests away from support requests admin pages', function () {
    $supportRequest = SupportRequest::factory()->create();

    $this->get(route('admin.support-requests.index'))
        ->assertRedirect(route('login'));

    $this->get(route('admin.support-requests.show', $supportRequest->uuid))
        ->assertRedirect(route('login'));
});
