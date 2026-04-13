<?php

namespace App\Http\Controllers;

use App\Events\UserSessionStateUpdated;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SessionActivityController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        return response()->json($this->sessionPayload(
            status: 'active',
        ));
    }

    public function triggerWarning(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $warningWindowSeconds = $this->warningWindowSeconds();
        $expiresAt = $this->resolveExpiryTimestamp();
        $lockKey = $this->warningCacheKey($user);

        $broadcasted = Cache::add(
            $lockKey,
            now(config('app.timezone'))->timestamp,
            now(config('app.timezone'))->addSeconds(max(15, min($warningWindowSeconds, 45))),
        );

        if ($broadcasted) {
            event(new UserSessionStateUpdated(
                userUuid: $user->uuid,
                state: 'warning',
                expiresAt: $expiresAt,
                warningWindowSeconds: $warningWindowSeconds,
                sessionLifetimeSeconds: $this->sessionLifetimeSeconds(),
            ));
        }

        return response()->json($this->sessionPayload(
            status: 'warning',
            extras: ['broadcasted' => $broadcasted],
        ));
    }

    public function keepAlive(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->session()->put(
            '_soamco_session_keep_alive_at',
            now(config('app.timezone'))->toIso8601String(),
        );

        Cache::forget($this->warningCacheKey($user));

        $expiresAt = $this->resolveExpiryTimestamp();

        event(new UserSessionStateUpdated(
            userUuid: $user->uuid,
            state: 'refreshed',
            expiresAt: $expiresAt,
            warningWindowSeconds: $this->warningWindowSeconds(),
            sessionLifetimeSeconds: $this->sessionLifetimeSeconds(),
        ));

        return response()->json($this->sessionPayload(
            status: 'refreshed',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function sessionPayload(string $status, array $extras = []): array
    {
        return [
            'status' => $status,
            'expires_at' => $this->resolveExpiryTimestamp(),
            'warning_window_seconds' => $this->warningWindowSeconds(),
            'session_lifetime_seconds' => $this->sessionLifetimeSeconds(),
            ...$extras,
        ];
    }

    protected function sessionLifetimeSeconds(): int
    {
        return max(60, (int) config('session.lifetime', 120) * 60);
    }

    protected function warningWindowSeconds(): int
    {
        return min(300, max(30, (int) config('session.warning_window_seconds', 300)));
    }

    protected function resolveExpiryTimestamp(): string
    {
        return now(config('app.timezone'))
            ->addSeconds($this->sessionLifetimeSeconds())
            ->toIso8601String();
    }

    protected function warningCacheKey(User $user): string
    {
        return "session-warning-broadcast:{$user->uuid}";
    }
}
