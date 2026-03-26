<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannelEnum;
use App\Enums\OutboundMessageStatusEnum;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Notifications\DeliveredOutboundDatabaseNotification;
use App\Notifications\DeliveredOutboundMailNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Throwable;

class OutboundMessageDeliveryService
{
    public function deliver(OutboundMessage $message): OutboundMessage
    {
        if ($message->status !== OutboundMessageStatusEnum::QUEUED) {
            return $message;
        }

        try {
            $recipient = $message->recipient;

            match ($message->channel) {
                CommunicationChannelEnum::MAIL => $this->deliverMail($recipient, $message),
                CommunicationChannelEnum::DATABASE => $this->deliverDatabase($recipient, $message),
                default => throw new InvalidArgumentException("Unsupported outbound channel [{$message->channel->value}]."),
            };

            $message->forceFill([
                'status' => OutboundMessageStatusEnum::SENT,
                'sent_at' => Carbon::now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();
        } catch (Throwable $e) {
            $message->forceFill([
                'status' => OutboundMessageStatusEnum::FAILED,
                'failed_at' => Carbon::now(),
                'error_message' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        return $message->fresh();
    }

    protected function deliverMail(mixed $recipient, OutboundMessage $message): void
    {
        if ($recipient instanceof User) {
            $recipient->notify(new DeliveredOutboundMailNotification($message));

            return;
        }

        $email = data_get($message->payload_snapshot, 'recipient.email')
            ?? data_get($message->context, 'email');

        if (! is_string($email) || trim($email) === '') {
            throw new InvalidArgumentException('Mail delivery requires a recipient email address.');
        }

        Notification::route('mail', $email)
            ->notify(new DeliveredOutboundMailNotification($message));
    }

    protected function deliverDatabase(User $recipient, OutboundMessage $message): void
    {
        $recipient->notify(new DeliveredOutboundDatabaseNotification($message));
    }
}
