<?php

use App\Models\User;

test('authenticated user can enable impersonation consent', function () {
    $user = User::factory()->create([
        'is_impersonable' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('settings.profile.impersonation-consent.update'), [
            'is_impersonable' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh()->is_impersonable)->toBeTrue();
});

test('authenticated user can disable impersonation consent', function () {
    $user = User::factory()->create([
        'is_impersonable' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('settings.profile.impersonation-consent.update'), [
            'is_impersonable' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh()->is_impersonable)->toBeFalse();
});

test('guest cannot update impersonation consent', function () {
    $this->patch(route('settings.profile.impersonation-consent.update'), [
        'is_impersonable' => true,
    ])->assertRedirect(route('login'));
});

test('impersonation consent requires a boolean value', function () {
    $user = User::factory()->create([
        'is_impersonable' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('settings.profile.impersonation-consent.update'), [
            'is_impersonable' => 'not-a-boolean',
        ])
        ->assertSessionHasErrors('is_impersonable');

    expect($user->fresh()->is_impersonable)->toBeFalse();
});
