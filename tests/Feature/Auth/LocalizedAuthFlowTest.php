<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('guest forgot password screen resolves english locale from browser', function () {
    $this->get(route('password.request'), [
        'Accept-Language' => 'en-US,en;q=0.9',
    ])->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ForgotPassword')
            ->where('locale.current', 'en')
        );
});

test('verify email page follows authenticated user locale', function () {
    $this->skipUnlessFortifyFeature(Features::emailVerification());

    $user = User::factory()->unverified()->create([
        'locale' => 'en',
    ]);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/VerifyEmail')
            ->where('locale.current', 'en')
        );
});
