<?php

use App\Models\User;
use App\Models\UserYear;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('years page is displayed', function () {
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
            ->where('years.data.0.year', 2026),
        );
});
