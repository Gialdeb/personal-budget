<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Account;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('create new user persists surname and provisions a default cash account', function () {
    $this->travelTo(now()->setDate(2026, 3, 22));

    $action = app(CreateNewUser::class);

    $user = $action->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    expect($user->surname)->toBe('Rossi');

    $this->assertDatabaseHas('users', [
        'email' => 'mario@example.com',
        'surname' => 'Rossi',
    ]);

    $cashAccount = Account::query()
        ->where('user_id', $user->id)
        ->where('name', 'Cassa contanti')
        ->first();

    expect($cashAccount)->not->toBeNull()
        ->and((float) $cashAccount->opening_balance)->toBe(0.0)
        ->and((float) $cashAccount->current_balance)->toBe(0.0)
        ->and(data_get($cashAccount->settings, 'allow_negative_balance'))->toBeFalse()
        ->and($user->settings?->active_year)->toBe(2026);

    $this->assertDatabaseHas('user_years', [
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);
});

test('newly registered user receives the user role', function () {
    $response = $this
        ->withoutMiddleware(PreventRequestForgery::class)
        ->post(route('register'), [
            'name' => 'Mario',
            'surname' => 'Rossi',
            'email' => 'mario@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

    $response->assertRedirect();

    $user = User::where('email', 'mario@example.com')->firstOrFail();

    expect($user->hasRole('user'))->toBeTrue();
});

test('new users have expected admin and subscription defaults', function () {
    $user = User::factory()->create()->fresh();

    expect($user)->not->toBeNull()
        ->and($user->status)->toBe('active')
        ->and($user->plan_code)->toBe('free')
        ->and($user->subscription_status)->toBe('active')
        ->and($user->is_impersonable)->toBeFalse();
});
