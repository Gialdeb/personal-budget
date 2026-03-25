<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannelEnum;
use App\Enums\OutboundMessageStatusEnum;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Notifications\DeliveredOutboundDatabaseNotification;
use App\Notifications\DeliveredOutboundMailNotification;
use Illuminate\Support\Carbon;
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

            if (! $recipient instanceof User) {
                throw new InvalidArgumentException('Only user recipients are currently supported.');
            }

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

    protected function deliverMail(User $recipient, OutboundMessage $message): void
    {
        $recipient->notify(new DeliveredOutboundMailNotification($message));
    }

    protected function deliverDatabase(User $recipient, OutboundMessage $message): void
    {
        $recipient->notify(new DeliveredOutboundDatabaseNotification($message));
    }
}
