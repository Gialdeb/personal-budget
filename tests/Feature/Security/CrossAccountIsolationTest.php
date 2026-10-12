<?php

use App\Models\DeviceToken;
use App\Models\User;
use App\Support\AuthenticatedSsrGateway;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Ssr\Gateway;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['recaptcha.enabled' => false]);
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('dynamic pages and session identity cannot be reused by shared caches', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    foreach ([route('dashboard'), route('recurring-entries.index'), route('shared-expenses.index'), route('support.index'), route('session.identity')] as $url) {
        $response = $this->actingAs($user)->get($url)->assertSuccessful();
        expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
        expect($response->baseResponse->getVary())->toContain('Cookie', 'Authorization', 'X-Inertia');
        $response->assertHeader('Pragma', 'no-cache')->assertHeader('Expires', '0');
        expect($response->headers->has('ETag'))->toBeFalse();
    }
});

test('login logout and another login resolve identity from the current session', function () {
    $users = User::factory()->count(3)->create();
    $users[0]->assignRole('admin');
    $users[1]->assignRole('user');
    $users[2]->assignRole('user');

    foreach ($users as $user) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => true])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->getJson(route('session.identity'))->assertExactJson(['user_uuid' => $user->uuid]);
        $dashboard = $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.uuid', $user->uuid)
            ->where('auth.user.email', $user->email));
        expect($dashboard->viewData('page')['encryptHistory'])->toBeTrue();
        expect($dashboard->viewData('page')['clearHistory'])->toBeTrue();
        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
        $this->getJson(route('session.identity'))->assertExactJson(['user_uuid' => null]);
    }
});

test('authenticated payloads never enter the shared SSR process', function () {
    Http::preventStrayRequests();
    $gateway = app(Gateway::class);
    expect($gateway)->toBeInstanceOf(AuthenticatedSsrGateway::class);
    expect($gateway->dispatch(['props' => ['auth' => ['user' => ['uuid' => 'private-user']]]]))->toBeNull();
    Http::assertNothingSent();
});

test('authorization errors and server failures also forbid response caching', function (int $status) {
    config(['app.debug' => false]);
    Route::get('/__security/status', fn () => abort($status));
    $response = $this->getJson('/__security/status')->assertStatus($status);
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
})->with([401, 403, 419, 500, 503]);

test('maintenance responses remain neutral even with an authenticated admin request', function () {
    $admin = User::factory()->create(['name' => 'PRIVATE ADMIN SENTINEL']);
    $admin->assignRole('admin');
    $maintenance = Mockery::mock(MaintenanceMode::class);
    $maintenance->shouldReceive('active')->andReturn(true);
    $maintenance->shouldReceive('data')->andReturn(['refresh' => 5, 'retry' => 15]);
    app()->instance(MaintenanceMode::class, $maintenance);
    $response = $this->withHeader('Accept-Language', 'it')->actingAs($admin)->get(route('dashboard'))->assertStatus(503)
        ->assertSee('Stiamo aggiornando Soamco')->assertDontSee('PRIVATE ADMIN SENTINEL')
        ->assertDontSee($admin->email)->assertDontSee('data-page');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('logout revokes only the push device associated with the current user session', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $current = DeviceToken::factory()->create(['user_id' => $user->id]);
    $otherDevice = DeviceToken::factory()->create(['user_id' => $user->id]);
    $foreign = DeviceToken::factory()->create();
    $this->actingAs($user)->withSession(['push_device_token_uuid' => $current->uuid])->post(route('logout'))->assertRedirect();
    expect($current->fresh()->is_active)->toBeFalse()
        ->and($otherDevice->fresh()->is_active)->toBeTrue()
        ->and($foreign->fresh()->is_active)->toBeTrue();
});

test('the same push device can register again after logout and another account login', function () {
    config(['features.push_notifications.enabled' => true]);
    $users = User::factory()->count(2)->create();
    foreach ($users as $user) {
        $user->assignRole('user');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->postJson(route('settings.profile.push-tokens.store'), [
            'token' => 'security-test-device-token', 'platform' => 'web', 'locale' => 'it', 'device_identifier' => 'security-test-browser',
        ])->assertOk();
        expect(DeviceToken::query()->where('token', 'security-test-device-token')->sole()->user_id)->toBe($user->id);
        expect(DeviceToken::query()->where('token', 'security-test-device-token')->sole()->is_active)->toBeTrue();
        $this->post(route('logout'))->assertRedirect();
        expect(DeviceToken::query()->where('token', 'security-test-device-token')->sole()->is_active)->toBeFalse();
    }
});

test('public and guest pages do not require session verification even for a signed in user', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('sessionVerificationRequired', false));
    $this->get(route('password.request'))->assertInertia(fn (Assert $page) => $page->where('sessionVerificationRequired', false));
    $user = User::factory()->create();
    $user->assignRole('user');
    $this->actingAs($user)->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('sessionVerificationRequired', false));
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('sessionVerificationRequired', true));
});
