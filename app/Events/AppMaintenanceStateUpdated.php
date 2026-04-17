<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppMaintenanceStateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly bool $active,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('app.maintenance'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'maintenance.state.updated';
    }

    /**
     * @return array{
     *     active: bool,
     *     status: 'active'|'inactive',
     *     checked_at: string
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'active' => $this->active,
            'status' => $this->active ? 'active' : 'inactive',
            'checked_at' => now()->toJSON(),
        ];
    }
}
