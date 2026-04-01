<?php

use App\Models\User;
use App\Notifications\AuthResetPasswordNotification;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->withSession(['locale' => 'it'])->get(route('password.request'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ForgotPassword')
            ->where('locale.current', 'it')
        );
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, AuthResetPasswordNotification::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, AuthResetPasswordNotification::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, AuthResetPasswordNotification::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('password reset email is localized in italian', function () {
    $user = User::factory()->create([
        'locale' => 'it',
    ]);

    App::setLocale('en');
    $mail = new AuthResetPasswordNotification('token123')->toMail($user);
    $html = app(Markdown::class)->render($mail->markdown, $mail->viewData)->toHtml();

    expect($mail->subject)->toBe('Reimposta la tua password')
        ->and($mail->markdown)->toBe('emails.notifications.base')
        ->and($html)->toContain('Pianificazione, movimenti e conti')
        ->and($html)->toContain('Hai ricevuto questa email perché è stata richiesta la reimpostazione della password del tuo account.')
        ->and($html)->toContain('Questo link scadrà')
        ->and($html)->not->toContain('Email transazionale inviata da');
});

test('password reset email is localized in english when user locale is english', function () {
    $user = User::factory()->create([
        'locale' => 'en',
    ]);

    App::setLocale('it');
    $mail = new AuthResetPasswordNotification('token123')->toMail($user);
    $html = app(Markdown::class)->render($mail->markdown, $mail->viewData)->toHtml();

    expect($mail->subject)->toBe('Reset your password')
        ->and($mail->markdown)->toBe('emails.notifications.base')
        ->and($html)->toContain('Planning, transactions and accounts')
        ->and($html)->toContain('You are receiving this email because we received a password reset request for your account.')
        ->and($html)->toContain('This link will expire in')
        ->and($html)->not->toContain('Transactional email from');
});
