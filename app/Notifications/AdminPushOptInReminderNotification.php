<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminPushOptInReminderNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'category' => [
                'key' => 'admin.push_opt_in_reminder',
                'name' => 'Push activation reminder',
            ],
            'channel' => 'database',
            'presentation' => [
                'layout' => 'standard_card',
                'icon' => 'notification',
                'image_url' => null,
            ],
            'content' => [
                'title' => __('ui.enable_push_notifications_9b7f3622'),
                'message' => __('ui.enable_push_notifications_so_you_do_not_miss_important_updates_dd42a339'),
                'cta_label' => __('ui.open_notification_preferences_3a385961'),
                'cta_url' => route('profile.edit', [], false),
            ],
            'payload_snapshot' => [
                'source' => 'admin_push_broadcasts',
                'kind' => 'push_opt_in_reminder',
            ],
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
