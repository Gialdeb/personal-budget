<?php

namespace App\Services\Admin;

use App\Models\PushBroadcast;
use App\Models\User;
use App\Services\Push\DeviceTokenService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\WebPushConfig;
use RuntimeException;

class AdminTargetedPushBroadcastService
{
    public function __construct(
        protected DeviceTokenService $deviceTokenService,
        protected Container $container,
    ) {}

    public function findEligibleUserByUuid(string $uuid): ?User
    {
        $user = User::query()
            ->with('settings')
            ->where('uuid', $uuid)
            ->first();

        if (! $user instanceof User) {
            return null;
        }

        return $this->prepareRecipients(collect([$user]))->first();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{eligible_users_count: int, target_tokens_count: int}
     */
    public function summarizeRecipients(Collection $users): array
    {
        $eligibleUsers = $this->prepareRecipients($users);

        return [
            'eligible_users_count' => $eligibleUsers->count(),
            'target_tokens_count' => $eligibleUsers->sum(
                fn (User $user): int => $user->deviceTokens->count(),
            ),
        ];
    }

    /**
     * @return array{eligible_users_count: int, target_tokens_count: int, sent_count: int, failed_count: int, invalidated_count: int}
     */
    public function send(PushBroadcast $broadcast): array
    {
        if (! config('features.push_notifications.enabled')) {
            throw new RuntimeException('Push notifications are disabled.');
        }

        $targetUserUuids = collect(data_get($broadcast->payload_snapshot, 'target.user_uuids', []))
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values();

        $users = User::query()
            ->with('settings')
            ->whereIn('uuid', $targetUserUuids)
            ->get();

        $eligibleUsers = $this->prepareRecipients($users);
        $tokens = $eligibleUsers
            ->flatMap(fn (User $user) => $user->deviceTokens->pluck('token'))
            ->values();

        $summary = [
            'eligible_users_count' => $eligibleUsers->count(),
            'target_tokens_count' => $tokens->count(),
            'sent_count' => 0,
            'failed_count' => 0,
            'invalidated_count' => 0,
        ];

        if ($tokens->isEmpty()) {
            return $summary;
        }

        $message = $this->messageForBroadcast($broadcast);
        $messaging = $this->messaging();

        foreach ($tokens->chunk(500) as $tokenChunk) {
            $report = $messaging->sendMulticast($message, $tokenChunk->all());
            $unknownTokens = array_values(array_unique($report->unknownTokens()));
            $invalidTokens = array_values(array_unique($report->invalidTokens()));
            $tokensToInvalidate = array_values(array_unique([
                ...$unknownTokens,
                ...$invalidTokens,
            ]));

            $summary['sent_count'] += $report->successes()->count();
            $summary['failed_count'] += $report->failures()->count();
            $summary['invalidated_count'] += count($tokensToInvalidate);

            if ($tokensToInvalidate !== []) {
                Log::warning('Targeted push broadcast delivery invalidated Firebase tokens.', [
                    'broadcast_uuid' => $broadcast->uuid,
                    'unknown_tokens' => $unknownTokens,
                    'invalid_tokens' => $invalidTokens,
                    'invalidated_tokens' => $tokensToInvalidate,
                    'chunk_size' => $tokenChunk->count(),
                ]);

                if ($unknownTokens !== []) {
                    $this->deviceTokenService->invalidateTokens(
                        $unknownTokens,
                        'firebase_unknown_token',
                    );
                }

                if ($invalidTokens !== []) {
                    $this->deviceTokenService->invalidateTokens(
                        $invalidTokens,
                        'firebase_invalid_token',
                    );
                }
            }
        }

        return $summary;
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    protected function prepareRecipients(Collection $users): Collection
    {
        $recipients = User::query()
            ->with('settings')
            ->whereKey(
                $users
                    ->filter(fn (mixed $user): bool => $user instanceof User)
                    ->map(fn (User $user): int => $user->getKey())
                    ->values()
                    ->all(),
            )
            ->get();

        return $recipients
            ->unique(fn (User $user): int => $user->getKey())
            ->values()
            ->filter(function (User $user): bool {
                if (! $user->pushNotificationsEnabled()) {
                    return false;
                }

                $broadcastTokens = $this->deviceTokenService->activeBroadcastTokensForUser($user);

                if ($broadcastTokens->isEmpty()) {
                    return false;
                }

                $user->setRelation('deviceTokens', $broadcastTokens);

                return true;
            })
            ->values();
    }

    protected function messageForBroadcast(PushBroadcast $broadcast): CloudMessage
    {
        $webPushConfig = WebPushConfig::fromArray(array_filter([
            'headers' => array_filter(config('push-notifications.webpush.headers', [])),
            'data' => array_filter([
                'title' => $broadcast->title,
                'body' => $broadcast->body,
                'icon' => $this->webPushIconUrl(),
                'badge' => $this->webPushBadgeUrl(),
                'require_interaction' => config(
                    'push-notifications.webpush.notification.require_interaction',
                ) ? 'true' : 'false',
                'broadcast_uuid' => $broadcast->uuid,
                'url' => $broadcast->url,
            ], fn (?string $value): bool => is_string($value) && $value !== ''),
            'fcm_options' => is_string($broadcast->url) && $broadcast->url !== ''
                ? ['link' => $broadcast->url]
                : null,
        ], fn (mixed $value): bool => $value !== null && $value !== []));

        return CloudMessage::new()
            ->withData(array_filter([
                'title' => $broadcast->title,
                'body' => $broadcast->body,
                'icon' => $this->webPushIconUrl(),
                'badge' => $this->webPushBadgeUrl(),
                'require_interaction' => config(
                    'push-notifications.webpush.notification.require_interaction',
                ) ? 'true' : 'false',
                'broadcast_uuid' => $broadcast->uuid,
                'url' => $broadcast->url,
            ], fn (?string $value): bool => is_string($value) && $value !== ''))
            ->withWebPushConfig($webPushConfig);
    }

    protected function messaging(): Messaging
    {
        return $this->container->make(Messaging::class);
    }

    protected function webPushIconUrl(): string
    {
        $configured = config('push-notifications.webpush.notification.icon');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return URL::asset('pwa/icons/icon-192.png');
    }

    protected function webPushBadgeUrl(): string
    {
        $configured = config('push-notifications.webpush.notification.badge');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return URL::asset('pwa/icons/icon-maskable-192.png');
    }
}
