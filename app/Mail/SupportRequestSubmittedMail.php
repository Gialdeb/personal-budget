<?php

namespace App\Mail;

use App\Models\SupportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportRequest $supportRequest,
        public ?string $activityMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        $userName = $this->supportRequest->user?->name ?? 'User';

        $category = __("support.categories.{$this->supportRequest->category}.label", [], $this->supportRequest->locale);

        return new Envelope(
            subject: "[Support][{$category}] {$this->supportRequest->subject}",
            replyTo: $this->supportRequest->user?->email
                ? [new Address($this->supportRequest->user->email, $userName)]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.support-request-submitted',
        );
    }
}
