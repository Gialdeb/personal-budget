<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Notification::fake();

    $password = 'Password123!';

    $response = $this->post(route('register.store'), [
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

    Notification::assertSentTo($user, VerifyEmail::class);
});
