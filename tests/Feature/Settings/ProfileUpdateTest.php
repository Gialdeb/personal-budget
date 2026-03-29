<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create([
        'surname' => 'Rossi',
        'is_impersonable' => true,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('auth.user.surname', 'Rossi')
            ->where('auth.user.is_impersonable', true)
            ->where('preferences.locale', $user->locale)
            ->where('preferences.format_locale', $user->format_locale)
            ->where('preferences.base_currency_code', $user->base_currency_code)
            ->where('preferences.can_update_base_currency', true)
            ->where('preferences.base_currency_lock_message', null)
            ->where('options.format_locales.0.code', 'it-IT')
            ->where('options.format_locales.0.label', 'Italia (1.234,56)')
        );
});

test('profile page keeps base currency editable when only the default cash account exists and there are no transactions', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    userAccount($user, [
        'name' => 'Cassa contanti',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('preferences.can_update_base_currency', true)
            ->where('preferences.base_currency_lock_message', null)
        );
});

test('profile page exposes base currency lock state when transactions exist', function () {
    $user = User::factory()->create([
        'base_currency_code' => 'EUR',
    ]);

    $account = userAccount($user, [
        'currency' => 'EUR',
        'currency_code' => 'EUR',
    ]);

    userTransaction($user, $account, [
        'amount' => '100.00',
        'type' => 'expense',
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('preferences.can_update_base_currency', false)
            ->where(
                'preferences.base_currency_lock_message',
                'La valuta base non può essere modificata dopo la creazione di conti o transazioni.',
            )
        );
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'format_locale' => 'en-US',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->format_locale)->toBe('en-US');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
            'format_locale' => $user->format_locale,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user cannot update format locale with an unsupported value', function () {
    $user = User::factory()->create([
        'format_locale' => 'it-IT',
    ]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'format_locale' => 'fr-FR',
        ])
        ->assertSessionHasErrors('format_locale')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->format_locale)->toBe('it-IT');
});

test('updating format locale does not modify locale or base currency', function () {
    $user = User::factory()->create([
        'locale' => 'en',
        'format_locale' => 'it-IT',
        'base_currency_code' => 'GBP',
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'format_locale' => 'en-GB',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->format_locale)->toBe('en-GB')
        ->and($user->fresh()->locale)->toBe('en')
        ->and($user->fresh()->base_currency_code)->toBe('GBP');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('user can upload a profile avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'avatar_image' => UploadedFile::fake()->image('avatar.png', 900, 900),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar_path);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('auth.user.avatar', fn (?string $avatar) => is_string($avatar) && str_contains($avatar, '/settings/profile/avatar/'))
        );
});

test('user can replace an existing profile avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $oldPath = UploadedFile::fake()->image('old-avatar.png', 600, 600)
        ->store('avatars/'.$user->uuid, 'public');

    $user->forceFill([
        'avatar_path' => $oldPath,
    ])->save();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'avatar_image' => UploadedFile::fake()->image('new-avatar.png', 900, 900),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatar_path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($user->avatar_path);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('auth.user.avatar', fn (?string $avatar) => is_string($avatar) && str_contains($avatar, '/settings/profile/avatar/'))
        );
});

test('user can remove a profile avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $avatarPath = UploadedFile::fake()->image('avatar.png', 600, 600)
        ->store('avatars/'.$user->uuid, 'public');

    $user->forceFill([
        'avatar_path' => $avatarPath,
    ])->save();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'avatar_remove' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($avatarPath);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('auth.user.avatar', null)
        );
});

test('authenticated user can fetch their avatar through the profile avatar route', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $avatarPath = UploadedFile::fake()->image('avatar.png', 600, 600)
        ->store('avatars/'.$user->uuid, 'public');

    $user->forceFill([
        'avatar_path' => $avatarPath,
    ])->save();

    $this->actingAs($user)
        ->get(route('profile.avatar.show', ['user' => $user->uuid]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});
