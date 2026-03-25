<?php

namespace App\Notifications;

use App\Models\OutboundMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveredOutboundDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected OutboundMessage $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'outbound_message_uuid' => $this->message->uuid,
            'category' => [
                'key' => $this->message->category?->key,
                'name' => $this->message->category?->name,
            ],
            'channel' => $this->message->channel->value,
            'presentation' => [
                'layout' => 'standard_card',
                'icon' => $this->iconForCategory(),
                'image_url' => null,
            ],
            'content' => [
                'title' => $this->message->title_resolved,
                'message' => $this->message->body_resolved,
                'cta_label' => $this->message->cta_label_resolved,
                'cta_url' => $this->message->cta_url_resolved,
            ],
            'payload_snapshot' => $this->message->payload_snapshot,
            'created_at' => optional($this->message->created_at)?->toIso8601String(),
        ];
    }

    protected function iconForCategory(): string
    {
        return match ($this->message->category?->key) {
            'imports.completed' => 'import',
            'user.welcome_after_verification' => 'welcome',
            'reports.weekly_ready' => 'report',
            default => 'notification',
        };
    }
}
