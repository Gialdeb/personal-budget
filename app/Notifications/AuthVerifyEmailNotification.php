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
            ->line(__('notifications.topics.auth_verify_email.message'))
            ->action(__('notifications.topics.auth_verify_email.cta'), $url);
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
