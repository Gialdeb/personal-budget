<?php

namespace App\Services\Push;

use App\Models\PushBroadcast;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\WebPushConfig;
use RuntimeException;

class PushNotificationService
{
    public function __construct(
        protected DeviceTokenService $deviceTokenService,
        protected Container $container,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function eligibleUsers(): Collection
    {
        return User::query()
            ->whereHas('deviceTokens', fn ($query) => $query->active())
            ->get()
            ->load('settings')
            ->filter(function (User $user): bool {
                if (! $user->pushNotificationsEnabled()) {
                    return false;
                }

                $broadcastTokens = $this->deviceTokenService->activeBroadcastTokensForUser($user);

                $user->setRelation('deviceTokens', $broadcastTokens);

                return $broadcastTokens->isNotEmpty();
            })
            ->values();
    }

    /**
     * @return array{eligible_users_count: int, target_tokens_count: int}
     */
    public function eligibleAudienceSummary(): array
    {
        $users = $this->eligibleUsers();

        return [
            'eligible_users_count' => $users->count(),
            'target_tokens_count' => $users->sum(
                fn (User $user): int => $user->deviceTokens->count(),
            ),
        ];
    }

    /**
     * @return array{eligible_users_count: int, target_tokens_count: int, sent_count: int, failed_count: int, invalidated_count: int}
     */
    public function sendBroadcast(PushBroadcast $broadcast): array
    {
        if (! config('features.push_notifications.enabled')) {
            throw new RuntimeException('Push notifications are disabled.');
        }

        $eligibleUsers = $this->eligibleUsers();
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
                Log::warning('Push broadcast delivery invalidated Firebase tokens.', [
                    'broadcast_uuid' => $broadcast->uuid,
                    'firebase' => $this->firebaseProjectContext(),
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

        Log::info('Push broadcast delivery completed.', [
            'broadcast_uuid' => $broadcast->uuid,
            ...$summary,
        ]);

        return $summary;
    }

    protected function messageForBroadcast(PushBroadcast $broadcast): CloudMessage
    {
        $webPushConfig = WebPushConfig::fromArray(array_filter([
            'headers' => array_filter(config('push-notifications.webpush.headers', [])),
            'notification' => array_filter([
                'title' => $broadcast->title,
                'body' => $broadcast->body,
                'icon' => $this->webPushIconUrl(),
                'badge' => $this->webPushBadgeUrl(),
                'requireInteraction' => config(
                    'push-notifications.webpush.notification.require_interaction',
                ),
                'data' => array_filter([
                    'url' => $broadcast->url,
                ], fn (?string $value): bool => is_string($value) && $value !== ''),
            ]),
            'data' => array_filter([
                'broadcast_uuid' => $broadcast->uuid,
                'url' => $broadcast->url,
            ], fn (?string $value): bool => is_string($value) && $value !== ''),
            'fcm_options' => is_string($broadcast->url) && $broadcast->url !== ''
                ? ['link' => $broadcast->url]
                : null,
        ], fn (mixed $value): bool => $value !== null && $value !== []));

        return CloudMessage::new()
            ->withNotification(Notification::create($broadcast->title, $broadcast->body))
            ->withData(array_filter([
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

    /**
     * @return array{default_project: string|null, credentials_path: string|null, credentials_project_id: string|null, credentials_client_email: string|null}
     */
    protected function firebaseProjectContext(): array
    {
        $defaultProject = config('firebase.default');
        $credentialsPath = config("firebase.projects.{$defaultProject}.credentials");

        if (! is_string($credentialsPath) || $credentialsPath === '' || ! File::exists($credentialsPath)) {
            return [
                'default_project' => is_string($defaultProject) ? $defaultProject : null,
                'credentials_path' => is_string($credentialsPath) ? $credentialsPath : null,
                'credentials_project_id' => null,
                'credentials_client_email' => null,
            ];
        }

        /** @var array{project_id?: mixed, client_email?: mixed} $credentials */
        $credentials = json_decode(File::get($credentialsPath), true) ?? [];

        return [
            'default_project' => is_string($defaultProject) ? $defaultProject : null,
            'credentials_path' => $credentialsPath,
            'credentials_project_id' => is_string($credentials['project_id'] ?? null)
                ? $credentials['project_id']
                : null,
            'credentials_client_email' => is_string($credentials['client_email'] ?? null)
                ? $credentials['client_email']
                : null,
        ];
    }
}
