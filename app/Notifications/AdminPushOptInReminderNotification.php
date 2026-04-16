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
                'title' => 'Attiva le notifiche push',
                'message' => 'Attiva le notifiche push per non perdere aggiornamenti importanti.',
                'cta_label' => 'Apri preferenze notifiche',
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
