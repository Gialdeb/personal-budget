<?php

use App\Models\User;
use App\Notifications\AuthVerifyEmailNotification;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::emailVerification());
});

test('sends verification notification', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Notification::assertSentTo($user, AuthVerifyEmailNotification::class);
});

test('does not send verification notification if email is verified', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    Notification::assertNothingSent();
});

test('verification email is localized in italian', function () {
    $user = User::factory()->unverified()->create([
        'locale' => 'it',
    ]);

    App::setLocale('en');
    $mail = (new AuthVerifyEmailNotification)->toMail($user);

    expect($mail->subject)->toBe('Verifica il tuo indirizzo email')
        ->and($mail->introLines)->toContain('Fai clic sul pulsante qui sotto per verificare il tuo indirizzo email.');
});

test('verification email is localized in english', function () {
    $user = User::factory()->unverified()->create([
        'locale' => 'en',
    ]);

    App::setLocale('it');
    $mail = (new AuthVerifyEmailNotification)->toMail($user);

    expect($mail->subject)->toBe('Verify your email address')
        ->and($mail->introLines)->toContain('Please click the button below to verify your email address.');
});
