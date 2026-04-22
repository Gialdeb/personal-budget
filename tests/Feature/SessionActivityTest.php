<?php

use App\Events\UserSessionStateUpdated;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

function expectedSessionLifetimeSeconds(): int
{
    return max(60, (int) config('session.lifetime', 180) * 60);
}

function expectedAutoKeepAliveThresholdSeconds(): int
{
    return min(
        expectedSessionLifetimeSeconds(),
        max(60, (int) config('session.auto_keep_alive_threshold_seconds', 900)),
    );
}

test('session warning shared props expose expiry metadata to authenticated users', function () {
    $user = User::factory()->create();
    $sessionLifetimeSeconds = expectedSessionLifetimeSeconds();
    $autoKeepAliveThresholdSeconds = expectedAutoKeepAliveThresholdSeconds();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where('sessionWarning.enabled', true)
            ->where('sessionWarning.warning_window_seconds', 300)
            ->where('sessionWarning.session_lifetime_seconds', $sessionLifetimeSeconds)
            ->where('sessionWarning.auto_keep_alive_enabled', true)
            ->where('sessionWarning.auto_keep_alive_threshold_seconds', $autoKeepAliveThresholdSeconds),
    );
});

test('warning trigger broadcasts a realtime warning for the authenticated user', function () {
    Event::fake([UserSessionStateUpdated::class]);

    $user = User::factory()->create();
    $sessionLifetimeSeconds = expectedSessionLifetimeSeconds();
    $autoKeepAliveThresholdSeconds = expectedAutoKeepAliveThresholdSeconds();

    $this->actingAs($user)
        ->postJson(route('session.warning.trigger'))
        ->assertOk()
        ->assertJson([
            'status' => 'warning',
            'warning_window_seconds' => 300,
            'session_lifetime_seconds' => $sessionLifetimeSeconds,
            'auto_keep_alive_enabled' => true,
            'auto_keep_alive_threshold_seconds' => $autoKeepAliveThresholdSeconds,
        ]);

    Event::assertDispatched(UserSessionStateUpdated::class, function (
        UserSessionStateUpdated $event,
    ) use ($user): bool {
        return $event->userUuid === $user->uuid && $event->state === 'warning';
    });
});

test('session status confirms the session is still active for authenticated users', function () {
    $user = User::factory()->create();
    $sessionLifetimeSeconds = expectedSessionLifetimeSeconds();
    $autoKeepAliveThresholdSeconds = expectedAutoKeepAliveThresholdSeconds();

    $this->actingAs($user)
        ->getJson(route('session.status'))
        ->assertOk()
        ->assertJson([
            'status' => 'active',
            'warning_window_seconds' => 300,
            'session_lifetime_seconds' => $sessionLifetimeSeconds,
            'auto_keep_alive_enabled' => true,
            'auto_keep_alive_threshold_seconds' => $autoKeepAliveThresholdSeconds,
        ]);
});

test('keep alive renews the session through http and broadcasts a refreshed state', function () {
    Event::fake([UserSessionStateUpdated::class]);

    $user = User::factory()->create();
    $sessionLifetimeSeconds = expectedSessionLifetimeSeconds();
    $autoKeepAliveThresholdSeconds = expectedAutoKeepAliveThresholdSeconds();

    $response = $this->actingAs($user)
        ->postJson(route('session.keep-alive'))
        ->assertOk()
        ->assertJson([
            'status' => 'refreshed',
            'warning_window_seconds' => 300,
            'session_lifetime_seconds' => $sessionLifetimeSeconds,
            'auto_keep_alive_enabled' => true,
            'auto_keep_alive_threshold_seconds' => $autoKeepAliveThresholdSeconds,
        ]);

    expect(app('session.store')->get('_soamco_session_keep_alive_at'))->not->toBeNull();

    Event::assertDispatched(UserSessionStateUpdated::class, function (
        UserSessionStateUpdated $event,
    ) use ($response, $user): bool {
        return $event->userUuid === $user->uuid &&
            $event->state === 'refreshed' &&
            $event->expiresAt === $response->json('expires_at');
    });
});

test('guests cannot trigger session warning endpoints', function () {
    $this->getJson(route('session.status'))->assertUnauthorized();
    $this->postJson(route('session.warning.trigger'))->assertUnauthorized();
    $this->postJson(route('session.keep-alive'))->assertUnauthorized();
});
