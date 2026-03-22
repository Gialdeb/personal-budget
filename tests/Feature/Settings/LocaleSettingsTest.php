<?php

use App\Models\User;

test('authenticated user can update locale', function () {
    $user = User::factory()->create([
        'locale' => 'it',
    ]);

    $this->actingAs($user)
        ->patch(route('settings.locale.update'), [
            'locale' => 'en',
        ])
        ->assertStatus(303);

    expect($user->fresh()->locale)->toBe('en');
});

test('locale shared by inertia uses user locale', function () {
    $user = User::factory()->create([
        'locale' => 'en',
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->where('locale.current', 'en')
        ->where('locale.fallback', 'en')
        ->has('locale.available', 2)
    );
});

test('unsupported locale is rejected', function () {
    $user = User::factory()->create([
        'locale' => 'it',
    ]);

    $this->actingAs($user)
        ->patch(route('settings.locale.update'), [
            'locale' => 'fr',
        ])
        ->assertSessionHasErrors('locale');
});
