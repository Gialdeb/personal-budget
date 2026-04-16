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
            ->where('preferences.number_thousands_separator', '.')
            ->where('preferences.number_decimal_separator', ',')
            ->where('preferences.date_format', 'D MMM YYYY')
            ->where('preferences.base_currency_code', $user->base_currency_code)
            ->where('preferences.can_update_base_currency', true)
            ->where('preferences.base_currency_lock_message', null)
            ->where('options.base_currencies.0.code', 'EUR')
            ->where('options.base_currencies.0.name', 'Euro')
            ->where('options.base_currencies.0.symbol', '€')
            ->where(
                'options.base_currencies.0.label',
                'EUR — Euro (€)',
            )
            ->where('options.format_locales.0.code', 'it-IT')
            ->where('options.format_locales.0.label', 'Italia (1.234,56)')
            ->where('options.number_thousands_separators.0.value', '.')
            ->where('options.number_decimal_separators.0.value', ',')
            ->where('options.date_formats.0.value', 'DD/MM/YYYY')
        );
});

test('settings root renders the mobile settings entry point without redirecting to profile route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
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
                'La valuta non può più essere modificata dopo l’inserimento delle prime transazioni.',
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
            'number_thousands_separator' => ',',
            'number_decimal_separator' => '.',
            'date_format' => 'YYYY-MM-DD',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->format_locale)->toBe('en-US');
    expect($user->number_thousands_separator)->toBe(',')
        ->and($user->number_decimal_separator)->toBe('.')
        ->and($user->date_format)->toBe('YYYY-MM-DD');
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
            'number_thousands_separator' => $user->number_thousands_separator,
            'number_decimal_separator' => $user->number_decimal_separator,
            'date_format' => $user->date_format,
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
            'number_thousands_separator' => '.',
            'number_decimal_separator' => ',',
            'date_format' => 'D MMM YYYY',
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
            'number_thousands_separator' => 'space',
            'number_decimal_separator' => '.',
            'date_format' => 'DD/MM/YYYY',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->format_locale)->toBe('en-GB')
        ->and($user->fresh()->number_thousands_separator)->toBe('space')
        ->and($user->fresh()->number_decimal_separator)->toBe('.')
        ->and($user->fresh()->date_format)->toBe('DD/MM/YYYY')
        ->and($user->fresh()->locale)->toBe('en')
        ->and($user->fresh()->base_currency_code)->toBe('GBP');
});

test('user cannot save the same separator for thousands and decimals', function () {
    $user = User::factory()->create([
        'number_thousands_separator' => '.',
        'number_decimal_separator' => ',',
    ]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'format_locale' => $user->format_locale,
            'number_thousands_separator' => '.',
            'number_decimal_separator' => '.',
            'date_format' => 'D MMM YYYY',
        ])
        ->assertSessionHasErrors('number_thousands_separator')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->number_thousands_separator)->toBe('.')
        ->and($user->fresh()->number_decimal_separator)->toBe(',');
});

test('user cannot save unsupported date format', function () {
    $user = User::factory()->create([
        'date_format' => 'D MMM YYYY',
    ]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'format_locale' => $user->format_locale,
            'number_thousands_separator' => '.',
            'number_decimal_separator' => ',',
            'date_format' => 'YYYY/MM/DD',
        ])
        ->assertSessionHasErrors('date_format')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->date_format)->toBe('D MMM YYYY');
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
