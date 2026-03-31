<?php

namespace App\Events\Admin;

use App\Models\AutomationRun;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class AutomationRunUpdated implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(
        public AutomationRun $automationRun,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.automation.runs')];
    }

    public function broadcastAs(): string
    {
        return 'automation.run.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run' => [
                'uuid' => $this->automationRun->uuid,
                'automation_key' => $this->automationRun->automation_key,
                'status' => $this->automationRun->status?->value,
                'trigger_type' => $this->automationRun->trigger_type?->value,
                'started_at' => $this->automationRun->started_at?->toDateTimeString(),
                'finished_at' => $this->automationRun->finished_at?->toDateTimeString(),
                'created_at' => $this->automationRun->created_at?->toDateTimeString(),
                'updated_at' => $this->automationRun->updated_at?->toDateTimeString(),
                'error_message' => $this->automationRun->error_message,
            ],
        ];
    }
}
