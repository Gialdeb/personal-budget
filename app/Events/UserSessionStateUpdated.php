<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserSessionStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $userUuid,
        public readonly string $state,
        public readonly string $expiresAt,
        public readonly int $warningWindowSeconds,
        public readonly int $sessionLifetimeSeconds,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("users.{$this->userUuid}.session"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.state.updated';
    }

    /**
     * @return array{
     *     state: string,
     *     expires_at: string,
     *     warning_window_seconds: int,
     *     session_lifetime_seconds: int
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'state' => $this->state,
            'expires_at' => $this->expiresAt,
            'warning_window_seconds' => $this->warningWindowSeconds,
            'session_lifetime_seconds' => $this->sessionLifetimeSeconds,
        ];
    }
}
