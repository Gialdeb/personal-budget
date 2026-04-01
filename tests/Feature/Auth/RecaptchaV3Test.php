<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);

    config()->set('recaptcha.enabled', true);
    config()->set('recaptcha.site_key', 'site-key');
    config()->set('recaptcha.secret_key', 'secret-key');
    config()->set('recaptcha.threshold', 0.5);
    config()->set('recaptcha.actions.login.threshold', 0.5);
    config()->set('recaptcha.actions.register.threshold', 0.7);
    config()->set('recaptcha.verify_url', 'https://www.google.com/recaptcha/api/siteverify');
    config()->set('recaptcha.timeout', 5);
});

test('login and register screens expose recaptcha frontend config', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('recaptcha.enabled', true)
            ->where('recaptcha.siteKey', 'site-key')
        );

    $this->skipUnlessFortifyFeature(Features::registration());

    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->where('recaptcha.enabled', true)
            ->where('recaptcha.siteKey', 'site-key')
        );
});

test('users can authenticate when recaptcha verification succeeds with the correct action', function () {
    $user = User::factory()->create();

    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'login',
            'error-codes' => [],
        ]),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'recaptcha_token' => 'login-token',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('login is rejected when recaptcha action does not match the expected login action', function () {
    $user = User::factory()->create();

    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'register',
            'error-codes' => [],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'recaptcha_token' => 'wrong-action-token',
    ])->assertSessionHasErrors('recaptcha_token');

    $this->assertGuest();
});

test('login is rejected when the recaptcha provider fails', function () {
    $user = User::factory()->create();

    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([], 500),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'recaptcha_token' => 'provider-failure-token',
    ])->assertSessionHasErrors('recaptcha_token');

    $this->assertGuest();
});

test('registration is rejected when recaptcha score is below the configured threshold', function () {
    $this->skipUnlessFortifyFeature(Features::registration());
    $this->withoutMiddleware(PreventRequestForgery::class);

    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.2,
            'action' => 'register',
            'error-codes' => [],
        ]),
    ]);

    $this->post(route('register.store'), [
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'recaptcha_token' => 'low-score-token',
    ])->assertSessionHasErrors('recaptcha_token');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
        'email' => 'mario@example.com',
    ]);
});

test('new users can register when recaptcha verification succeeds with the correct action', function () {
    $this->skipUnlessFortifyFeature(Features::registration());
    $this->withoutMiddleware(PreventRequestForgery::class);
    Notification::fake();

    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.95,
            'action' => 'register',
            'error-codes' => [],
        ]),
    ]);

    $response = $this->post(route('register.store'), [
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'recaptcha_token' => 'register-token',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'mario@example.com',
    ]);
});

test('recaptcha verification is bypassed when the feature is disabled', function () {
    config()->set('recaptcha.enabled', false);

    $user = User::factory()->create();

    Http::fake();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
    Http::assertNothingSent();
});
