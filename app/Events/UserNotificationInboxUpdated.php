<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationInboxUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $userUuid,
        public readonly int $unreadCount,
        /** @var array<string, mixed> */
        public readonly array $notification,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("users.{$this->userUuid}.notifications"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.inbox.updated';
    }

    /**
     * @return array{
     *     unread_count: int,
     *     notification: array<string, mixed>
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'unread_count' => $this->unreadCount,
            'notification' => $this->notification,
        ];
    }
}
