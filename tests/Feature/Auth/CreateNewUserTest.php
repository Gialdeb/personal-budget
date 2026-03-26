<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Services\UserProvisioningService;
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
        ->and($user->base_currency_code)->toBe('EUR')
        ->and($user->format_locale)->toBe('it-IT')
        ->and((float) $cashAccount->opening_balance)->toBe(0.0)
        ->and((float) $cashAccount->current_balance)->toBe(0.0)
        ->and($cashAccount->currency_code)->toBe('EUR')
        ->and(data_get($cashAccount->settings, 'allow_negative_balance'))->toBeFalse()
        ->and($user->settings?->active_year)->toBe(2026);

    $foundations = Category::query()
        ->where('user_id', $user->id)
        ->where('is_system', true)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();

    expect($foundations)->toBe([
        'Entrate',
        'Spese',
        'Bollette',
        'Debiti',
        'Risparmi',
    ]);

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'foundation_key' => 'income',
        'icon' => 'circle-dollar-sign',
        'color' => '#15803d',
    ]);

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'foundation_key' => 'expense',
        'icon' => 'credit-card',
        'color' => '#e11d48',
    ]);

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

test('application user provisioning centralizes defaults and foundations for user accounts', function () {
    $this->travelTo(now()->setDate(2026, 3, 22));

    $user = User::query()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario-provisioned@example.com',
        'password' => 'Password123!',
        'locale' => 'it',
        'base_currency_code' => 'EUR',
        'format_locale' => 'it-IT',
    ]);

    $provisioned = app(UserProvisioningService::class)->provisionApplicationUser($user);

    expect($provisioned->hasRole('user'))->toBeTrue()
        ->and($provisioned->locale)->toBe('it')
        ->and($provisioned->base_currency_code)->toBe('EUR')
        ->and($provisioned->format_locale)->toBe('it-IT')
        ->and($provisioned->settings?->active_year)->toBe(2026);

    $this->assertDatabaseHas('accounts', [
        'user_id' => $provisioned->id,
        'name' => 'Cassa contanti',
    ]);

    $this->assertDatabaseHas('categories', [
        'user_id' => $provisioned->id,
        'foundation_key' => 'income',
        'name' => 'Entrate',
        'is_system' => true,
    ]);

    $this->assertDatabaseHas('categories', [
        'user_id' => $provisioned->id,
        'foundation_key' => 'saving',
        'name' => 'Risparmi',
        'icon' => 'piggy-bank',
        'color' => '#ca8a04',
        'is_system' => true,
    ]);

    $this->assertDatabaseMissing('categories', [
        'user_id' => $provisioned->id,
        'name' => 'Tasse',
    ]);

    $this->assertDatabaseMissing('categories', [
        'user_id' => $provisioned->id,
        'name' => 'Investimenti',
    ]);

    $this->assertDatabaseHas('user_years', [
        'user_id' => $provisioned->id,
        'year' => 2026,
        'is_closed' => false,
    ]);
});

test('new users have expected admin and subscription defaults', function () {
    $user = User::factory()->create()->fresh();

    expect($user)->not->toBeNull()
        ->and($user->status)->toBe('active')
        ->and($user->plan_code)->toBe('free')
        ->and($user->subscription_status)->toBe('active')
        ->and($user->is_impersonable)->toBeFalse();
});
