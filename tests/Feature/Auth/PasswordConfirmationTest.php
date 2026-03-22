<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/ConfirmPassword'),
    );
});

test('confirm password screen resolves english locale when selected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ConfirmPassword')
            ->where('locale.current', 'en')
        );
});

test('password confirmation requires authentication', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});
