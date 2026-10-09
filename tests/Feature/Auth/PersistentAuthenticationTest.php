<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Services\Auth\ActiveSessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['session.driver' => 'database', 'session.lottery' => [0, 100], 'session.secure' => true]);
    $this->withCredentials();
    restartPersistentAuthClient();
});

function restartPersistentAuthClient(): void
{
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
}

function persistentLogin(User $user, bool $remember = false): TestResponse
{
    return test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => $remember])
        ->assertRedirect();
}

function reopenPersistentClient(TestResponse $response, bool $remember = false): void
{
    $names = [config('session.cookie')];
    if ($remember) {
        $names[] = auth()->guard('web')->getRecallerName();
    }
    $cookies = [];
    foreach ($names as $name) {
        $cookies[$name] = $response->getCookie($name, false)->getValue();
    }
    restartPersistentAuthClient();
    test()->withUnencryptedCookies($cookies);
}

test('login issues a secure persistent twelve hour session cookie', function () {
    $response = persistentLogin(User::factory()->create());
    $cookie = $response->getCookie(config('session.cookie'));
    expect(config('session.lifetime'))->toBe(720)
        ->and(config('session.expire_on_close'))->toBeFalse()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBeGreaterThanOrEqual(now()->addHours(12)->timestamp - 2);
    expect($response->getCookie(auth()->guard('web')->getRecallerName()))->toBeNull();
});

test('authentication survives reopening and renews within twelve hours of inactivity', function (int $hours) {
    $response = persistentLogin(User::factory()->create());
    $this->travel($hours)->hours();
    reopenPersistentClient($response);
    $renewed = $this->getJson('/session/status')->assertOk();
    expect($renewed->getCookie(config('session.cookie'))->getExpiresTime())
        ->toBeGreaterThanOrEqual(now()->addHours(12)->timestamp - 2);
    $this->travel(11)->hours();
    reopenPersistentClient($renewed);
    $this->getJson('/session/status')->assertOk();
})->with([0, 1, 6, 11]);

test('a truly expired session cannot authenticate without remember me', function () {
    $response = persistentLogin(User::factory()->create());
    $this->travel(12)->hours();
    $this->travel(1)->seconds();
    reopenPersistentClient($response);
    $this->getJson('/session/status')->assertUnauthorized();
});

test('optional native remember cookie lasts ninety days and restores an expired session', function () {
    $response = persistentLogin(User::factory()->create(), true);
    $cookie = $response->getCookie(auth()->guard('web')->getRecallerName());
    expect($cookie->getExpiresTime())->toBeGreaterThanOrEqual(now()->addDays(90)->timestamp - 2)
        ->and($cookie->isHttpOnly())->toBeTrue()->and($cookie->isSecure())->toBeTrue();
    $this->travel(61)->days();
    reopenPersistentClient($response, true);
    $this->getJson('/session/status')->assertOk();
});

test('login regenerates the anonymous session identifier', function () {
    $user = User::factory()->create();
    $guest = $this->get(route('login'));
    $oldId = $guest->getCookie(config('session.cookie'))->getValue();
    reopenPersistentClient($guest);
    $login = persistentLogin($user);
    expect($login->getCookie(config('session.cookie'))->getValue())->not->toBe($oldId);
    $this->assertDatabaseMissing('sessions', ['id' => $oldId]);
});

test('logout invalidates both session and replayed remember cookie', function () {
    $response = persistentLogin(User::factory()->create(), true);
    reopenPersistentClient($response, true);
    $this->post(route('logout'))->assertRedirect();
    reopenPersistentClient($response, true);
    $this->getJson('/session/status')->assertUnauthorized();
});

test('revocation prevents remember cookie from recreating the revoked session', function () {
    $user = User::factory()->create();
    $response = persistentLogin($user, true);
    $id = $response->getCookie(config('session.cookie'))->getValue();
    expect(app(ActiveSessionService::class)->revokeUserSession($user->refresh(), $id, 'another-device'))->toBeTrue();
    reopenPersistentClient($response, true);
    $this->getJson('/session/status')->assertUnauthorized();
});

test('password reset invalidates stored sessions and remember cookies', function () {
    $user = User::factory()->create();
    $response = persistentLogin($user, true);
    app(ResetUserPassword::class)->reset($user->refresh(), ['password' => 'new-password', 'password_confirmation' => 'new-password']);
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    reopenPersistentClient($response, true);
    $this->getJson('/session/status')->assertUnauthorized();
});

test('password change retains only the regenerated current session', function () {
    $user = User::factory()->create();
    $response = persistentLogin($user, true);
    $oldId = $response->getCookie(config('session.cookie'))->getValue();
    reopenPersistentClient($response, true);
    $changed = $this->put(route('user-password.update'), ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($changed->getCookie(config('session.cookie'))->getValue())->not->toBe($oldId);
    $this->assertDatabaseMissing('sessions', ['id' => $oldId]);
    reopenPersistentClient($changed);
    $this->getJson('/session/status')->assertOk();
});

test('password hash mismatch terminates an otherwise valid session', function () {
    $user = User::factory()->create();
    $response = persistentLogin($user);
    reopenPersistentClient($response);
    $response = $this->getJson('/session/status')->assertOk();
    $user->forceFill(['password' => 'changed-password'])->save();
    reopenPersistentClient($response);
    $this->getJson('/session/status')->assertUnauthorized();
});

test('persistent authentication does not bypass csrf protection', function () {
    $response = persistentLogin(User::factory()->create());
    reopenPersistentClient($response);
    app()->instance('env', 'production');
    try {
        $this->post('/session/keep-alive')->assertStatus(419);
    } finally {
        app()->instance('env', 'testing');
    }
});
