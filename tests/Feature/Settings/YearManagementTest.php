<?php

use App\Enums\BudgetTypeEnum;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserYear;
use App\Services\UserYearService;
use Carbon\CarbonImmutable;

function verifiedYearUser(): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
    ]);
}

function createUserYear(User $user, int $year, bool $isClosed = false): UserYear
{
    return UserYear::query()->create([
        'user_id' => $user->id,
        'year' => $year,
        'is_closed' => $isClosed,
    ]);
}

test('creating the first year sets it as active', function () {
    $this->travelTo(now()->setDate(2026, 3, 22));

    $user = verifiedYearUser();

    $this->actingAs($user)
        ->post(route('years.store'), [
            'year' => 2026,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseHas('user_years', [
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'active_year' => 2026,
    ]);
});

test('user can create a previous management year', function () {
    $this->travelTo(now()->setDate(2026, 3, 22));

    $user = verifiedYearUser();

    $this->actingAs($user)
        ->post(route('years.store'), [
            'year' => 2025,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseHas('user_years', [
        'user_id' => $user->id,
        'year' => 2025,
    ]);
});

test('user can create the current management year when it is missing', function () {
    $this->travelTo(now()->setDate(2026, 3, 22));

    $user = verifiedYearUser();

    $this->actingAs($user)
        ->post(route('years.store'), [
            'year' => 2026,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseHas('user_years', [
        'user_id' => $user->id,
        'year' => 2026,
    ]);
});

test('user cannot create a future management year', function () {
    $this->travelTo(now()->setDate(2026, 3, 22));

    $user = verifiedYearUser();

    $this->actingAs($user)
        ->from(route('years.edit'))
        ->post(route('years.store'), [
            'year' => 2027,
        ])
        ->assertSessionHasErrors('year')
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseMissing('user_years', [
        'user_id' => $user->id,
        'year' => 2027,
    ]);
});

test('user can prepare the next management year from november without changing the active year', function () {
    $this->travelTo(now()->setDate(2026, 11, 1));

    $user = verifiedYearUser();
    createUserYear($user, 2026);
    UserSetting::query()->create([
        'user_id' => $user->id,
        'active_year' => 2026,
        'base_currency' => 'EUR',
    ]);

    $this->actingAs($user)
        ->post(route('years.store'), [
            'year' => 2027,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseHas('user_years', [
        'user_id' => $user->id,
        'year' => 2027,
        'is_closed' => false,
    ]);
    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'active_year' => 2026,
    ]);
});

test('user cannot prepare the next management year before november', function () {
    $this->travelTo(now()->setDate(2026, 10, 31));

    $user = verifiedYearUser();
    createUserYear($user, 2026);

    $this->actingAs($user)
        ->post(route('years.store'), [
            'year' => 2027,
        ])
        ->assertSessionHasErrors('year');

    $this->assertDatabaseMissing('user_years', [
        'user_id' => $user->id,
        'year' => 2027,
    ]);
});

test('user cannot skip the current management year or create more than one year ahead', function () {
    $this->travelTo(now()->setDate(2026, 11, 1));

    $userWithoutCurrentYear = verifiedYearUser();
    createUserYear($userWithoutCurrentYear, 2025);

    $this->actingAs($userWithoutCurrentYear)
        ->post(route('years.store'), [
            'year' => 2027,
        ])
        ->assertSessionHasErrors('year');

    $userWithCurrentYear = verifiedYearUser();
    createUserYear($userWithCurrentYear, 2026);

    $this->actingAs($userWithCurrentYear)
        ->post(route('years.store'), [
            'year' => 2028,
        ])
        ->assertSessionHasErrors('year');

    $this->assertDatabaseMissing('user_years', [
        'user_id' => $userWithoutCurrentYear->id,
        'year' => 2027,
    ]);
    $this->assertDatabaseMissing('user_years', [
        'user_id' => $userWithCurrentYear->id,
        'year' => 2028,
    ]);
});

test('user cannot create a duplicate management year', function () {
    $user = verifiedYearUser();

    createUserYear($user, 2026);

    $this->actingAs($user)
        ->from(route('years.edit'))
        ->post(route('years.store'), [
            'year' => 2026,
        ])
        ->assertSessionHasErrors('year')
        ->assertRedirect(route('years.edit'));
});

test('user can set another year as active and sync settings', function () {
    $user = verifiedYearUser();

    $year2025 = createUserYear($user, 2025);
    createUserYear($user, 2026);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => 2026,
        'base_currency' => 'EUR',
    ]);

    $this->actingAs($user)
        ->patch(route('years.activate', $year2025))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'active_year' => 2025,
    ]);
});

test('distant recurring management years remain registered but are not selectable', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-31'));

    $user = verifiedYearUser();
    createUserYear($user, 2025);
    createUserYear($user, 2026);
    createUserYear($user, 2027);
    $distantYear = createUserYear($user, 2046);
    UserSetting::query()->create([
        'user_id' => $user->id,
        'active_year' => 2026,
        'base_currency' => 'EUR',
    ]);

    expect(app(UserYearService::class)->registeredYears($user))
        ->toBe([2025, 2026, 2027, 2046])
        ->and(app(UserYearService::class)->availableYears($user))
        ->toBe([2025, 2026, 2027]);

    $this->actingAs($user)
        ->get(route('years.edit'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('years.data', fn ($years) => collect($years)
                ->pluck('year')
                ->values()
                ->all() === [2027, 2026, 2025]));

    $this->actingAs($user)
        ->from(route('years.edit'))
        ->patch(route('years.activate', $distantYear))
        ->assertSessionHasErrors('year')
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseHas('user_settings', [
        'user_id' => $user->id,
        'active_year' => 2026,
    ]);
});

test('user can close and reopen a management year', function () {
    $user = verifiedYearUser();

    $year = createUserYear($user, 2026);

    $this->actingAs($user)
        ->patch(route('years.update', $year), [
            'is_closed' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseHas('user_years', [
        'id' => $year->id,
        'is_closed' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('years.update', $year), [
            'is_closed' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseHas('user_years', [
        'id' => $year->id,
        'is_closed' => false,
    ]);
});

test('active year cannot be deleted', function () {
    $user = verifiedYearUser();

    $year2025 = createUserYear($user, 2025);
    createUserYear($user, 2026);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => 2025,
    ]);

    $this->actingAs($user)
        ->from(route('years.edit'))
        ->delete(route('years.destroy', $year2025))
        ->assertSessionHasErrors('delete')
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseHas('user_years', [
        'id' => $year2025->id,
    ]);
});

test('used year cannot be deleted', function () {
    $user = verifiedYearUser();

    createUserYear($user, 2025);
    $year2026 = createUserYear($user, 2026);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => 2025,
    ]);

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Casa',
        'slug' => 'casa',
        'direction_type' => 'expense',
        'group_type' => 'expense',
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'year' => 2026,
        'month' => 1,
        'amount' => 100,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    $this->actingAs($user)
        ->from(route('years.edit'))
        ->delete(route('years.destroy', $year2026))
        ->assertSessionHasErrors('delete')
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseHas('user_years', [
        'id' => $year2026->id,
    ]);
});

test('unused non active year can be deleted', function () {
    $user = verifiedYearUser();

    createUserYear($user, 2025);
    $year2026 = createUserYear($user, 2026);

    UserSetting::query()->updateOrCreate([
        'user_id' => $user->id,
    ], [
        'active_year' => 2025,
    ]);

    $this->actingAs($user)
        ->delete(route('years.destroy', $year2026))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('years.edit'));

    $this->assertDatabaseMissing('user_years', [
        'id' => $year2026->id,
    ]);
});
