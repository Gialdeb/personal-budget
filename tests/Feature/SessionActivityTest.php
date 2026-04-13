<?php

use App\Events\UserSessionStateUpdated;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('session warning shared props expose expiry metadata to authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where('sessionWarning.enabled', true)
            ->where('sessionWarning.warning_window_seconds', 300)
            ->where('sessionWarning.session_lifetime_seconds', 7200),
    );
});

test('warning trigger broadcasts a realtime warning for the authenticated user', function () {
    Event::fake([UserSessionStateUpdated::class]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('session.warning.trigger'))
        ->assertOk()
        ->assertJson([
            'status' => 'warning',
            'warning_window_seconds' => 300,
            'session_lifetime_seconds' => 7200,
        ]);

    Event::assertDispatched(UserSessionStateUpdated::class, function (
        UserSessionStateUpdated $event,
    ) use ($user): bool {
        return $event->userUuid === $user->uuid && $event->state === 'warning';
    });
});

test('session status confirms the session is still active for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('session.status'))
        ->assertOk()
        ->assertJson([
            'status' => 'active',
            'warning_window_seconds' => 300,
            'session_lifetime_seconds' => 7200,
        ]);
});

test('keep alive renews the session through http and broadcasts a refreshed state', function () {
    Event::fake([UserSessionStateUpdated::class]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('session.keep-alive'))
        ->assertOk()
        ->assertJson([
            'status' => 'refreshed',
            'warning_window_seconds' => 300,
            'session_lifetime_seconds' => 7200,
        ]);

    expect(session()->get('_soamco_session_keep_alive_at'))->not->toBeNull();

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
