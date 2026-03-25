<?php

namespace App\Notifications;

use App\Models\OutboundMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        $mail = (new MailMessage)
            ->subject($this->message->subject_resolved ?? $this->message->title_resolved ?? 'Notification');

        if ($this->message->title_resolved) {
            $mail->line($this->message->title_resolved);
        }

        $mail->line($this->message->body_resolved);

        if ($this->message->cta_label_resolved && $this->message->cta_url_resolved) {
            $mail->action($this->message->cta_label_resolved, $this->message->cta_url_resolved);
        }

        return $mail;
    }
}
