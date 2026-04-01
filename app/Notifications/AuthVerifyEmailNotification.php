<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\App;

class AuthVerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.topics.auth_verify_email.subject'))
            ->markdown('emails.notifications.base', [
                'title' => __('notifications.topics.auth_verify_email.title'),
                'message' => __('notifications.topics.auth_verify_email.message'),
                'details' => [],
                'detailsTitle' => __('notifications.common.details'),
                'actionLabel' => __('notifications.topics.auth_verify_email.cta'),
                'actionUrl' => $url,
                'notes' => [],
                'footer' => __('notifications.common.footer', ['app' => config('app.name')]),
                'brandTagline' => __('notifications.common.brand_tagline'),
            ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = null;

        if (method_exists($notifiable, 'preferredLocale')) {
            $locale = $notifiable->preferredLocale();
        }

        $locale ??= $notifiable->locale ?? App::getLocale();

        $previousLocale = App::getLocale();
        App::setLocale($locale);

        try {
            $url = $this->verificationUrl($notifiable);

            return $this->buildMailMessage($url);
        } finally {
            App::setLocale($previousLocale);
        }
    }
}
