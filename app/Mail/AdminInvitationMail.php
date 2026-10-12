<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminInvitationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $invitationUuid, public string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('admin_invitation.mail_subject'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.admin-invitation');
    }
}
