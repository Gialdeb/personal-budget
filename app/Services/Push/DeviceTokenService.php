<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeviceTokenService
{
    public function registerOrUpdate(
        User $user,
        string $token,
        string $platform = 'web',
        ?string $locale = null,
        ?string $deviceIdentifier = null,
        ?string $serviceWorkerVersion = null,
    ): DeviceToken {
        return $this->registerCurrentDevice(
            $user,
            $token,
            $platform,
            $locale,
            $deviceIdentifier,
            $serviceWorkerVersion,
        )['device_token'];
    }

    /**
     * @return array{
     *     device_token: DeviceToken,
     *     lifecycle: 'registered'|'reused'|'reactivated'|'rotated'|'realigned',
     *     previous_token: string|null,
     *     recovered_from_invalidation: bool
     * }
     */
    public function registerCurrentDevice(
        User $user,
        string $token,
        string $platform = 'web',
        ?string $locale = null,
        ?string $deviceIdentifier = null,
        ?string $serviceWorkerVersion = null,
    ): array {
        $result = DB::transaction(function () use (
            $user,
            $token,
            $platform,
            $locale,
            $deviceIdentifier,
            $serviceWorkerVersion,
        ): array {
            $now = now();
            $normalizedDeviceIdentifier = $this->normalizeDeviceIdentifier($deviceIdentifier);
            $deviceToken = $normalizedDeviceIdentifier !== null
                ? DeviceToken::query()
                    ->forUser($user)
                    ->forPlatform($platform)
                    ->forDeviceIdentifier($normalizedDeviceIdentifier)
                    ->latest('id')
                    ->first()
                : null;

            $matchingToken = DeviceToken::query()
                ->where('token', $token)
                ->latest('id')
                ->first();

            $lifecycle = 'registered';
            $previousToken = null;
            $recoveredFromInvalidation = false;

            if ($deviceToken instanceof DeviceToken) {
                $previousToken = $deviceToken->token;
                $recoveredFromInvalidation = $deviceToken->invalidated_at !== null;

                if ($deviceToken->token === $token) {
                    $lifecycle = $deviceToken->is_active ? 'reused' : 'reactivated';
                } else {
                    $lifecycle = 'rotated';
                }
            } elseif ($matchingToken instanceof DeviceToken) {
                $deviceToken = $matchingToken;
                $recoveredFromInvalidation = $deviceToken->invalidated_at !== null;
                $lifecycle = $deviceToken->is_active ? 'realigned' : 'reactivated';
            } else {
                $deviceToken = new DeviceToken;
            }

            $deviceToken->forceFill([
                'user_id' => $user->getKey(),
                'token' => $token,
                'platform' => $platform,
                'device_identifier' => $normalizedDeviceIdentifier,
                'locale' => $locale,
                'is_active' => true,
                'last_seen_at' => $now,
                'last_registered_at' => $now,
                'invalidated_at' => null,
                'invalidation_reason' => null,
                'service_worker_version' => $serviceWorkerVersion,
            ])->save();

            if ($normalizedDeviceIdentifier !== null) {
                DeviceToken::query()
                    ->forUser($user)
                    ->forPlatform($platform)
                    ->forDeviceIdentifier($normalizedDeviceIdentifier)
                    ->where($deviceToken->getKeyName(), '!=', $deviceToken->getKey())
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'invalidated_at' => $now,
                        'invalidation_reason' => 'superseded_by_device_realignment',
                        'updated_at' => $now,
                    ]);
            }

            return [
                'device_token' => $deviceToken->fresh() ?? $deviceToken,
                'lifecycle' => $lifecycle,
                'previous_token' => $previousToken,
                'recovered_from_invalidation' => $recoveredFromInvalidation,
            ];
        });

        Log::info('Push device token synchronized.', [
            'user_id' => $user->getKey(),
            'platform' => $platform,
            'device_identifier' => $this->redactedDeviceIdentifier($deviceIdentifier),
            'token_hash' => $this->hashToken($token),
            'previous_token_hash' => $this->hashToken($result['previous_token']),
            'lifecycle' => $result['lifecycle'],
            'recovered_from_invalidation' => $result['recovered_from_invalidation'],
            'service_worker_version' => $serviceWorkerVersion,
        ]);

        return $result;
    }

    public function markInactive(string $token): ?DeviceToken
    {
        $deviceToken = DeviceToken::query()->where('token', $token)->first();

        if (! $deviceToken instanceof DeviceToken) {
            return null;
        }

        $deviceToken->forceFill([
            'is_active' => false,
            'invalidated_at' => now(),
            'invalidation_reason' => 'manually_inactivated',
        ])->save();

        return $deviceToken;
    }

    public function markInactiveForUser(User $user, string $token): ?DeviceToken
    {
        return $this->markInactiveForUserAndPlatform($user, $token);
    }

    public function markInactiveForUserAndPlatform(
        User $user,
        string $token,
        ?string $platform = null,
    ): ?DeviceToken {
        $deviceToken = DeviceToken::query()
            ->forUser($user)
            ->when(
                $platform !== null,
                fn ($query) => $query->forPlatform($platform),
            )
            ->where('token', $token)
            ->first();

        if (! $deviceToken instanceof DeviceToken) {
            return null;
        }

        $deviceToken->forceFill([
            'is_active' => false,
            'invalidated_at' => now(),
            'invalidation_reason' => 'user_disabled',
        ])->save();

        return $deviceToken;
    }

    public function markInactiveCurrentDevice(
        User $user,
        ?string $deviceIdentifier,
        ?string $token = null,
        string $platform = 'web',
        string $reason = 'user_disabled',
    ): ?DeviceToken {
        $deviceToken = $this->currentDeviceTokenForUser(
            $user,
            $deviceIdentifier,
            $token,
            $platform,
        );

        if (! $deviceToken instanceof DeviceToken) {
            return null;
        }

        $deviceToken->forceFill([
            'is_active' => false,
            'invalidated_at' => now(),
            'invalidation_reason' => $reason,
        ])->save();

        return $deviceToken;
    }

    public function deactivateActiveTokensForUser(User $user, string $platform = 'web'): int
    {
        return DeviceToken::query()
            ->forUser($user)
            ->forPlatform($platform)
            ->active()
            ->update([
                'is_active' => false,
                'invalidated_at' => now(),
                'invalidation_reason' => 'bulk_deactivation',
                'updated_at' => now(),
            ]);
    }

    public function activeTokenForUser(
        User $user,
        string $token,
        ?string $platform = null,
    ): ?DeviceToken {
        return DeviceToken::query()
            ->forUser($user)
            ->active()
            ->when(
                $platform !== null,
                fn ($query) => $query->forPlatform($platform),
            )
            ->where('token', $token)
            ->first();
    }

    public function touch(string $token, ?Carbon $seenAt = null): ?DeviceToken
    {
        $deviceToken = DeviceToken::query()->where('token', $token)->first();

        if (! $deviceToken instanceof DeviceToken) {
            return null;
        }

        $deviceToken->forceFill([
            'last_seen_at' => $seenAt ?? now(),
            'is_active' => true,
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ])->save();

        return $deviceToken;
    }

    /**
     * @param  iterable<int, string>  $tokens
     */
    public function cleanupInvalidTokens(iterable $tokens): int
    {
        return $this->invalidateTokens($tokens, 'firebase_invalid');
    }

    /**
     * @param  iterable<int, string>  $tokens
     */
    public function invalidateTokens(
        iterable $tokens,
        string $reason = 'firebase_invalid',
    ): int {
        $normalizedTokens = collect($tokens)
            ->filter(fn (mixed $token): bool => is_string($token) && $token !== '')
            ->values();

        if ($normalizedTokens->isEmpty()) {
            return 0;
        }

        return DeviceToken::query()
            ->whereIn('token', $normalizedTokens->all())
            ->update([
                'is_active' => false,
                'invalidated_at' => now(),
                'invalidation_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return Collection<int, DeviceToken>
     */
    public function activeTokensForUser(User $user, ?string $platform = null): Collection
    {
        return DeviceToken::query()
            ->forUser($user)
            ->active()
            ->when($platform !== null, fn ($query) => $query->forPlatform($platform))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, DeviceToken>
     */
    public function activeBroadcastTokensForUser(
        User $user,
        string $platform = 'web',
    ): Collection {
        $this->pruneDuplicateActiveTokensForUser($user, $platform);

        return DeviceToken::query()
            ->forUser($user)
            ->forPlatform($platform)
            ->active()
            ->orderByDesc('last_registered_at')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (DeviceToken $deviceToken): string => $this->broadcastDeviceKey($deviceToken))
            ->values();
    }

    public function activeBroadcastTokenCountForUser(
        User $user,
        string $platform = 'web',
    ): int {
        return $this->activeBroadcastTokensForUser($user, $platform)->count();
    }

    public function pruneDuplicateActiveTokensForUser(
        User $user,
        string $platform = 'web',
    ): int {
        $activeTokens = DeviceToken::query()
            ->forUser($user)
            ->forPlatform($platform)
            ->active()
            ->orderByDesc('last_registered_at')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();

        $tokensToDeactivate = $activeTokens
            ->groupBy(fn (DeviceToken $deviceToken): string => $this->broadcastDeviceKey($deviceToken))
            ->flatMap(
                fn (Collection $group): Collection => $group
                    ->slice(1)
                    ->pluck('id'),
            )
            ->filter()
            ->values();

        if ($tokensToDeactivate->isEmpty()) {
            return 0;
        }

        $affectedRows = DeviceToken::query()
            ->whereIn('id', $tokensToDeactivate->all())
            ->update([
                'is_active' => false,
                'invalidated_at' => now(),
                'invalidation_reason' => 'superseded_duplicate_active_token',
                'updated_at' => now(),
            ]);

        Log::info('Pruned duplicate active push tokens for user.', [
            'user_id' => $user->getKey(),
            'platform' => $platform,
            'duplicates_pruned' => $affectedRows,
        ]);

        return $affectedRows;
    }

    public function currentDeviceTokenForUser(
        User $user,
        ?string $deviceIdentifier,
        ?string $token = null,
        ?string $platform = null,
    ): ?DeviceToken {
        $normalizedDeviceIdentifier = $this->normalizeDeviceIdentifier($deviceIdentifier);

        if ($normalizedDeviceIdentifier !== null) {
            return DeviceToken::query()
                ->forUser($user)
                ->active()
                ->when(
                    $platform !== null,
                    fn ($query) => $query->forPlatform($platform),
                )
                ->forDeviceIdentifier($normalizedDeviceIdentifier)
                ->latest('id')
                ->first();
        }

        if (! is_string($token) || $token === '') {
            return null;
        }

        return $this->activeTokenForUser($user, $token, $platform);
    }

    protected function normalizeDeviceIdentifier(?string $deviceIdentifier): ?string
    {
        $normalized = is_string($deviceIdentifier) ? trim($deviceIdentifier) : '';

        return $normalized !== '' ? $normalized : null;
    }

    protected function hashToken(?string $token): ?string
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return substr(hash('sha256', $token), 0, 12);
    }

    protected function redactedDeviceIdentifier(?string $deviceIdentifier): ?string
    {
        if (! is_string($deviceIdentifier) || trim($deviceIdentifier) === '') {
            return null;
        }

        return substr(hash('sha256', trim($deviceIdentifier)), 0, 12);
    }

    protected function broadcastDeviceKey(DeviceToken $deviceToken): string
    {
        if (is_string($deviceToken->device_identifier) && $deviceToken->device_identifier !== '') {
            return sprintf(
                'device:%s:%s',
                $deviceToken->platform,
                $deviceToken->device_identifier,
            );
        }

        return sprintf(
            'legacy:%s:%s',
            $deviceToken->platform,
            Str::lower($deviceToken->token),
        );
    }
}
