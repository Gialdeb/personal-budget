<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ActiveSessionService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user, string $currentSessionId): array
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(function (object $session) use ($currentSessionId): array {
                $parsedAgent = $this->parseUserAgent($session->user_agent);
                $lastActivity = CarbonImmutable::createFromTimestamp((int) $session->last_activity);

                return [
                    'id' => (string) $session->id,
                    'ip_address' => $session->ip_address ?: 'Unknown',
                    'user_agent' => $session->user_agent,
                    'browser' => $parsedAgent['browser'],
                    'operating_system' => $parsedAgent['operating_system'],
                    'device_type' => $parsedAgent['device_type'],
                    'device_label' => collect([
                        $parsedAgent['browser'],
                        $parsedAgent['operating_system'],
                        $parsedAgent['device_type'],
                    ])->filter()->implode(' • '),
                    'last_activity_at' => $lastActivity->toIso8601String(),
                    'last_activity_human' => $lastActivity->diffForHumans(),
                    'is_current' => hash_equals($currentSessionId, (string) $session->id),
                    'is_revocable' => ! hash_equals($currentSessionId, (string) $session->id),
                ];
            })
            ->values()
            ->all();
    }

    public function revokeUserSession(User $user, string $sessionId, string $currentSessionId): bool
    {
        if (hash_equals($currentSessionId, $sessionId)) {
            return false;
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    public function revokeOtherUserSessions(User $user, string $currentSessionId): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    /**
     * @return array{browser:string, operating_system:string, device_type:string}
     */
    protected function parseUserAgent(?string $userAgent): array
    {
        $agent = strtolower((string) $userAgent);

        return [
            'browser' => $this->detectBrowser($agent),
            'operating_system' => $this->detectOperatingSystem($agent),
            'device_type' => $this->detectDeviceType($agent),
        ];
    }

    protected function detectBrowser(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'edg/') => 'Edge',
            str_contains($agent, 'opr/'), str_contains($agent, 'opera') => 'Opera',
            str_contains($agent, 'chrome/') && ! str_contains($agent, 'edg/') => 'Chrome',
            str_contains($agent, 'firefox/') => 'Firefox',
            str_contains($agent, 'safari/') && ! str_contains($agent, 'chrome/') => 'Safari',
            str_contains($agent, 'curl/') => 'cURL',
            $agent === '' => 'Unknown browser',
            default => 'Unknown browser',
        };
    }

    protected function detectOperatingSystem(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'iphone'), str_contains($agent, 'ipad'), str_contains($agent, 'ios') => 'iOS',
            str_contains($agent, 'mac os x'), str_contains($agent, 'macintosh') => 'macOS',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'linux') => 'Linux',
            $agent === '' => 'Unknown OS',
            default => 'Unknown OS',
        };
    }

    protected function detectDeviceType(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'ipad'), str_contains($agent, 'tablet') => 'Tablet',
            str_contains($agent, 'mobile'), str_contains($agent, 'iphone'), str_contains($agent, 'android') => 'Mobile',
            $agent === '' => 'Unknown device',
            default => 'Desktop',
        };
    }
}
