<?php

use App\Models\User;
use App\Services\Auth\ActiveSessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

function currentSessionIdFor(TestCase $testCase, User $user): string
{
    /** @var TestResponse $response */
    $response = $testCase
        ->actingAs($user)
        ->get(route('profile.edit'));

    return data_get($response->viewData('page'), 'props.active_sessions.current_session_id');
}

function createSessionRecord(User $user, string $id, array $overrides = []): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/122.0 Safari/537.36',
        'payload' => base64_encode('payload'),
        'last_activity' => now()->timestamp,
        ...$overrides,
    ]);
}

test('user sees only their own active sessions in profile settings', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $currentSessionId = currentSessionIdFor($this, $user);

    createSessionRecord($user, $currentSessionId, ['ip_address' => '10.0.0.1']);
    createSessionRecord($user, 'user-other-session', ['ip_address' => '10.0.0.2']);
    createSessionRecord($otherUser, 'another-user-session', ['ip_address' => '10.0.0.3']);

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('active_sessions.items.0.id', $currentSessionId)
            ->where('active_sessions.current_session_id', fn ($id) => is_string($id) && $id !== '')
            ->where('active_sessions.items', fn ($sessions) => count($sessions) === 2
                && collect($sessions)->contains(fn (array $session) => $session['id'] === 'user-other-session' && $session['is_revocable'] === true)
                && ! collect($sessions)->contains(fn (array $session) => $session['id'] === 'another-user-session'))
        );
});

test('user can revoke a single non current session', function () {
    $user = User::factory()->create();
    $currentSessionId = currentSessionIdFor($this, $user);

    createSessionRecord($user, $currentSessionId);
    createSessionRecord($user, 'revoke-me');

    $this->delete(route('settings.profile.sessions.destroy', ['sessionId' => 'revoke-me']))
        ->assertRedirect(route('profile.edit'));

    $this->assertDatabaseMissing('sessions', [
        'id' => 'revoke-me',
        'user_id' => $user->id,
    ]);
    $this->assertDatabaseHas('sessions', [
        'id' => $currentSessionId,
        'user_id' => $user->id,
    ]);
});

test('user cannot revoke another users session through single revoke endpoint', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $currentSessionId = currentSessionIdFor($this, $user);

    createSessionRecord($user, $currentSessionId);
    createSessionRecord($otherUser, 'other-users-session');

    $this->delete(route('settings.profile.sessions.destroy', ['sessionId' => 'other-users-session']))
        ->assertNotFound();

    $this->assertDatabaseHas('sessions', [
        'id' => 'other-users-session',
        'user_id' => $otherUser->id,
    ]);
});

test('service does not revoke the current session when single revoke is requested', function () {
    $user = User::factory()->create();
    $service = app(ActiveSessionService::class);
    $currentSessionId = 'current-session-protected';
    createSessionRecord($user, $currentSessionId);

    $deleted = $service->revokeUserSession($user, $currentSessionId, $currentSessionId);

    expect($deleted)->toBeFalse();
    $this->assertDatabaseHas('sessions', [
        'id' => $currentSessionId,
        'user_id' => $user->id,
    ]);
});

test('service revokes all other sessions while keeping the current one and preserving other users sessions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $service = app(ActiveSessionService::class);
    $currentSessionId = 'current-session-keep';

    createSessionRecord($user, $currentSessionId);
    createSessionRecord($user, 'other-session-a');
    createSessionRecord($user, 'other-session-b');
    createSessionRecord($otherUser, 'another-users-session');

    $deletedCount = $service->revokeOtherUserSessions($user, $currentSessionId);

    expect($deletedCount)->toBe(2);
    $this->assertDatabaseHas('sessions', [
        'id' => $currentSessionId,
        'user_id' => $user->id,
    ]);
    $this->assertDatabaseMissing('sessions', [
        'id' => 'other-session-a',
        'user_id' => $user->id,
    ]);
    $this->assertDatabaseMissing('sessions', [
        'id' => 'other-session-b',
        'user_id' => $user->id,
    ]);
    $this->assertDatabaseHas('sessions', [
        'id' => 'another-users-session',
        'user_id' => $otherUser->id,
    ]);
});
