<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('categories page is displayed', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Categories'),
        );
});
