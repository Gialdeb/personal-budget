<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\App;

class LocalizedVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $previousLocale = App::currentLocale();
        $locale = method_exists($notifiable, 'preferredLocale')
            ? $notifiable->preferredLocale()
            : $previousLocale;

        App::setLocale($locale ?: $previousLocale);

        try {
            return parent::toMail($notifiable);
        } finally {
            App::setLocale($previousLocale);
        }
    }
}
