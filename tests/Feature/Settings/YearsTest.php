<?php

use App\Models\User;
use App\Models\UserYear;
use App\Services\UserYearService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('years page is displayed', function () {
    $this->travelTo(now()->setDate(2026, 10, 31));

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    $this->actingAs($user)
        ->get(route('years.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Years')
            ->where('years.summary.total_count', 1)
            ->where('years.data.0.uuid', fn (string $uuid) => Str::isUuid($uuid))
            ->missing('years.data.0.id')
            ->where('years.data.0.year', 2026)
            ->where('years.meta.maximum_creatable_year', 2026)
            ->where('years.meta.can_create_next_year', false),
        );
});

test('years page enables next year creation from november', function () {
    $this->travelTo(now()->setDate(2026, 11, 1));

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    UserYear::query()->create([
        'user_id' => $user->id,
        'year' => 2026,
        'is_closed' => false,
    ]);

    $this->actingAs($user)
        ->get(route('years.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('years.meta.next_year', 2027)
            ->where('years.meta.maximum_creatable_year', 2027)
            ->where('years.meta.can_create_next_year', true)
        );
});

test('automatically prepared next year stays hidden and cannot be activated before november', function () {
    $this->travelTo(now()->setDate(2026, 10, 31));
    $user = User::factory()->create(['email_verified_at' => now()]);
    $service = app(UserYearService::class);
    $service->ensureYearExists($user, 2026);
    $future = $service->ensureYearExists($user, 2027);
    $this->actingAs($user)->getJson(route('years.edit'))->assertOk()->assertJsonCount(1, 'years.data')->assertJsonPath('years.data.0.year', 2026);
    $this->patchJson(route('years.activate', $future))->assertUnprocessable();
    $this->patchJson(route('years.update', $future), ['is_closed' => false])->assertUnprocessable();
    expect($future->fresh())->not->toBeNull();
    $this->travelTo(now()->setDate(2026, 11, 1));
    $this->getJson(route('years.edit'))->assertOk()->assertJsonCount(2, 'years.data');
    $this->patch(route('years.activate', $future))->assertRedirect();
    expect($user->fresh()->settings->active_year)->toBe(2027);
});
