<?php

namespace App\Notifications;

use App\Models\OutboundMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

class DeliveredOutboundMailNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected OutboundMessage $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $previousLocale = App::currentLocale();
        $locale = method_exists($notifiable, 'preferredLocale')
            ? $notifiable->preferredLocale()
            : null;

        App::setLocale($locale ?: $previousLocale);

        try {
            return (new MailMessage)
                ->subject($this->message->subject_resolved ?? $this->message->title_resolved ?? 'Notification')
                ->markdown('emails.notifications.base', [
                    'title' => $this->message->title_resolved ?: ($this->message->subject_resolved ?? 'Notification'),
                    'message' => $this->message->body_resolved,
                    'details' => [],
                    'detailsTitle' => __('notifications.common.details'),
                    'actionLabel' => $this->message->cta_label_resolved,
                    'actionUrl' => $this->message->cta_url_resolved,
                    'notes' => [],
                    'footer' => __('notifications.common.footer', ['app' => config('app.name')]),
                    'brandTagline' => __('notifications.common.brand_tagline'),
                ]);
        } finally {
            App::setLocale($previousLocale);
        }
    }
}
