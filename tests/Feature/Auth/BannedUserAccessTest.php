<?php

use App\Enums\UserStatusEnum;
use App\Models\User;

test('banned user is logged out when accessing an authenticated page', function () {
    $user = User::factory()->create([
        'status' => UserStatusEnum::BANNED->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('active user can still access authenticated pages', function () {
    $user = User::factory()->create([
        'status' => UserStatusEnum::ACTIVE->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});
test('banned user cannot log in', function () {
    $user = User::factory()->create([
        'email' => 'banned@example.com',
        'password' => bcrypt('Password123!'),
        'status' => UserStatusEnum::BANNED->value,
    ]);

    $this->post(route('login'), [
        'email' => 'banned@example.com',
        'password' => 'Password123!',
    ])->assertSessionHasErrors([
        'email' => __('auth.banned'),
    ]);

    $this->assertGuest();
});
