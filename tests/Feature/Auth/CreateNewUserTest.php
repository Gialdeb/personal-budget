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

    $this->assertDatabaseHas('categories', [
        'user_id' => $provisioned->id,
        'name' => 'Investimenti',
    ]);

    $this->assertDatabaseHas('user_years', [
        'user_id' => $provisioned->id,
        'year' => 2026,
        'is_closed' => false,
    ]);
});

test('new users receive the expected default foundation subcategories', function () {
    $user = User::factory()->create([
        'email' => 'mario-foundations@example.com',
    ]);

    $provisioned = app(UserProvisioningService::class)->provisionApplicationUser($user);

    expect(findCategoryByPath($provisioned, ['Entrate', 'Stipendio']))->not->toBeNull()
        ->and(findCategoryByPath($provisioned, ['Entrate', 'Altre entrate']))->not->toBeNull()
        ->and(findCategoryByPath($provisioned, ['Spese', 'Auto']))->not->toBeNull()
        ->and(findCategoryByPath($provisioned, ['Spese', 'Auto'])?->is_selectable)->toBeFalse()
        ->and(findCategoryByPath($provisioned, ['Spese', 'Auto', 'Assicurazione']))->not->toBeNull()
        ->and(findCategoryByPath($provisioned, ['Spese', 'Moto', 'Bollo']))->not->toBeNull()
        ->and(findCategoryByPath($provisioned, ['Spese', 'Abbonamenti'])?->is_selectable)->toBeFalse()
        ->and(findCategoryByPath($provisioned, ['Spese', 'Abbonamenti', 'Streaming']))->not->toBeNull()
        ->and(findCategoryByPath($provisioned, ['Spese', 'Abbonamenti', 'App e software']))->not->toBeNull()
        ->and(findCategoryByPath($provisioned, ['Spese', 'Abbonamenti', 'Altri abbonamenti']))->not->toBeNull()
        ->and(findCategoryByPath($provisioned, ['Bollette', 'Internet']))->not->toBeNull()
        ->and(findCategoryByPath($provisioned, ['Debiti', 'Carta di credito']))->not->toBeNull()
        ->and(findCategoryByPath($provisioned, ['Risparmi', 'Investimenti']))->not->toBeNull();
});

test('foundation default subcategories are not duplicated when provisioning runs twice', function () {
    $user = User::factory()->create([
        'email' => 'mario-duplicate-foundations@example.com',
    ]);

    $service = app(UserProvisioningService::class);

    $service->provisionApplicationUser($user);
    $service->provisionApplicationUser($user->fresh());

    expect(countCategoriesByPath($user, ['Spese', 'Auto']))->toBe(1)
        ->and(countCategoriesByPath($user, ['Spese', 'Auto', 'Assicurazione']))->toBe(1)
        ->and(countCategoriesByPath($user, ['Spese', 'Moto', 'Manutenzioni']))->toBe(1)
        ->and(countCategoriesByPath($user, ['Spese', 'Abbonamenti']))->toBe(1)
        ->and(countCategoriesByPath($user, ['Spese', 'Abbonamenti', 'Streaming']))->toBe(1)
        ->and(countCategoriesByPath($user, ['Entrate', 'Stipendio']))->toBe(1);
});

test('new users have expected admin and subscription defaults', function () {
    $user = User::factory()->create()->fresh();

    expect($user)->not->toBeNull()
        ->and($user->status)->toBe('active')
        ->and($user->plan_code)->toBe('free')
        ->and($user->subscription_status)->toBe('active')
        ->and($user->is_impersonable)->toBeFalse();
});

function findCategoryByPath(User $user, array $path): ?Category
{
    $parentId = null;
    $category = null;

    foreach ($path as $segment) {
        $category = Category::query()
            ->where('user_id', $user->id)
            ->whereNull('account_id')
            ->where('parent_id', $parentId)
            ->where('name', $segment)
            ->first();

        if (! $category instanceof Category) {
            return null;
        }

        $parentId = $category->id;
    }

    return $category;
}

function countCategoriesByPath(User $user, array $path): int
{
    $parentId = null;
    $count = 0;

    foreach ($path as $index => $segment) {
        $query = Category::query()
            ->where('user_id', $user->id)
            ->whereNull('account_id')
            ->where('parent_id', $parentId)
            ->where('name', $segment);

        $count = $query->count();

        if ($count === 0) {
            return 0;
        }

        if ($index === array_key_last($path)) {
            return $count;
        }

        $parentId = $query->value('id');
    }

    return $count;
}
