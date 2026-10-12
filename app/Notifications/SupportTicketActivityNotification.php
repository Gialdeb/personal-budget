<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SupportTicketActivityNotification extends Notification
{
    public function __construct(public string $ticketUuid, public bool $forAdmin, public string $category, public string $subject, public bool $inactivityWarning = false) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $locale = $notifiable->preferredLocale();

        return ['category' => ['key' => 'support_ticket', 'name' => __('support.title', [], $locale)],
            'channel' => 'database', 'presentation' => ['layout' => 'standard_card', 'icon' => 'notification', 'image_url' => null],
            'content' => ['title' => __('support.title', [], $locale),
                'message' => $this->inactivityWarning ? __('support.inactivity_warning', ['subject' => $this->subject], $locale) : ($this->forAdmin ? __('support.admin_activity', ['subject' => $this->subject, 'type' => __('support.categories.'.$this->category.'.label', [], $locale)], $locale) : __('support.user_activity', [], $locale)),
                'cta_label' => __('support.open_ticket', [], $locale), 'cta_url' => $this->forAdmin ? route('admin.support-requests.show', $this->ticketUuid, false) : route('support.index', ['ticket' => $this->ticketUuid], false)],
            'payload_snapshot' => ['ticket_uuid' => $this->ticketUuid, 'type' => $this->category], 'created_at' => now()->toIso8601String()];
    }
}
