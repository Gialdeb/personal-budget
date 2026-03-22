<?php

use App\Models\Account;
use App\Models\User;
use App\Notifications\Auth\LocalizedVerifyEmail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('registration screen can be rendered', function () {
    $response = $this->withSession(['locale' => 'it'])->get(route('register'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->where('locale.current', 'it')
        );
});

test('new users can register', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Notification::fake();
    $this->travelTo(now()->setDate(2026, 3, 22));

    $password = 'Password123!';

    $response = $this->withSession(['locale' => 'en'])->post(route('register.store'), [
        'name' => 'Test User',
        'surname' => 'Rossi',
        'email' => 'test@example.com',
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'surname' => 'Rossi',
        'locale' => 'en',
    ]);

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Cassa contanti',
    ]);

    $cashAccount = Account::query()
        ->where('user_id', $user->id)
        ->where('name', 'Cassa contanti')
        ->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse();
    expect(data_get($cashAccount->settings, 'allow_negative_balance'))->toBeFalse();
    expect($user->settings?->active_year)->toBe(2026);
    expect(session('locale'))->toBe('en');

    $this->assertDatabaseHas('user_years', [
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    Notification::assertSentTo($user, LocalizedVerifyEmail::class);
});

test('guest in english keeps english locale after registration and email verification redirect', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->travelTo(now()->setDate(2026, 3, 22));

    $this->withSession(['locale' => 'en'])->post(route('register.store'), [
        'name' => 'Jane',
        'surname' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('verification.notice', absolute: false));

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/VerifyEmail')
            ->where('locale.current', 'en')
        );

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('locale.current', 'en')
        );
});

test('registration verification email content uses request locale when user locale is missing', function () {
    $user = User::factory()->create([
        'locale' => 'it',
    ]);
    $user->locale = null;

    App::setLocale('en');
    $mail = (new LocalizedVerifyEmail)->toMail($user);

    expect($mail->subject)->toBe('Verify your email address')
        ->and($mail->introLines)->toContain('Please click the button below to verify your email address.');
});
