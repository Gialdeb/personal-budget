<?php

namespace App\Services\Communication;

use App\Models\NotificationTopic;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class CommunicationService
{
    public function __construct(
        protected NotificationRecipientResolver $recipientResolver,
        protected NotificationPreferenceResolver $preferenceResolver,
        protected NotificationClassResolver $notificationClassResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, array{
     *     user: User,
     *     channels: array<int, string>,
     *     topic: string,
     *     payload: array<string, mixed>
     * }>
     */
    public function prepare(
        string $topicKey,
        array $payload = [],
        User|iterable|null $target = null,
    ): Collection {
        $topic = NotificationTopic::query()
            ->where('key', $topicKey)
            ->where('is_active', true)
            ->first();

        if (! $topic) {
            throw new InvalidArgumentException("Notification topic [{$topicKey}] is not active or does not exist.");
        }

        $recipients = $this->recipientResolver->resolveRecipients($topic, $target);

        return $recipients
            ->map(function (User $user) use ($topic, $payload) {
                $channels = $this->preferenceResolver->resolveChannels($user, $topic);

                return [
                    'user' => $user,
                    'channels' => array_map(fn ($channel) => $channel->value, $channels),
                    'topic' => $topic->key,
                    'payload' => $payload,
                ];
            })
            ->filter(fn (array $entry) => $entry['channels'] !== [])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(
        string $topicKey,
        array $payload = [],
        User|iterable|null $target = null,
    ): Collection {
        $plan = $this->prepare($topicKey, $payload, $target);

        foreach ($plan as $entry) {
            Notification::send(
                $entry['user'],
                $this->notificationClassResolver->resolve($topicKey, $payload),
            );
        }

        return $plan;
    }
}
