<?php

use App\Models\User;
use App\Services\Security\RecaptchaV3Verifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    config()->set('recaptcha.log_results', false);
    config()->set('recaptcha.expected_hostnames', []);
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
            'hostname' => 'soamco.lo',
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

test('login verifies recaptcha only once even when two factor authentication redirects the flow', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $recaptchaVerifier = Mockery::mock(RecaptchaV3Verifier::class);
    $recaptchaVerifier->shouldReceive('assertValid')
        ->once()
        ->with(Mockery::type(Request::class), 'login');

    app()->instance(RecaptchaV3Verifier::class, $recaptchaVerifier);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'recaptcha_token' => 'login-token',
    ])->assertRedirect(route('two-factor.login'));
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

test('login is rejected when recaptcha hostname does not match the expected hostnames', function () {
    config()->set('recaptcha.expected_hostnames', ['app.soamco.com']);

    $user = User::factory()->create();

    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'login',
            'hostname' => 'other.example.com',
            'challenge_ts' => now()->toIso8601String(),
            'error-codes' => [],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'recaptcha_token' => 'wrong-hostname-token',
    ])->assertSessionHasErrors('recaptcha_token');

    $this->assertGuest();
});

test('login logs the recaptcha provider result when debug logging is enabled', function () {
    config()->set('recaptcha.log_results', true);

    $user = User::factory()->create();

    Log::spy();

    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true,
            'score' => 0.9,
            'action' => 'login',
            'hostname' => 'soamco.lo',
            'challenge_ts' => '2026-04-14T10:00:00Z',
            'error-codes' => [],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'recaptcha_token' => 'logging-token',
    ])->assertRedirect(route('dashboard', absolute: false));

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'reCAPTCHA verification result'
                && $context['flow'] === 'login'
                && $context['success'] === true
                && $context['score'] === 0.9
                && $context['action'] === 'login'
                && $context['hostname'] === 'soamco.lo'
                && $context['challenge_ts'] === '2026-04-14T10:00:00Z'
                && $context['error_codes'] === [];
        });
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
            'hostname' => 'soamco.lo',
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

test('registration verifies recaptcha only once during user creation', function () {
    $this->skipUnlessFortifyFeature(Features::registration());
    $this->withoutMiddleware(PreventRequestForgery::class);
    Notification::fake();

    $recaptchaVerifier = Mockery::mock(RecaptchaV3Verifier::class);
    $recaptchaVerifier->shouldReceive('assertValid')
        ->once()
        ->with(Mockery::type(Request::class), 'register');

    app()->instance(RecaptchaV3Verifier::class, $recaptchaVerifier);

    $this->post(route('register.store'), [
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'recaptcha_token' => 'register-token',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
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
