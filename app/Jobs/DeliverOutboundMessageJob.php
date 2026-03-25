<?php

namespace App\Jobs;

use App\Models\OutboundMessage;
use App\Services\Communication\OutboundMessageDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverOutboundMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $outboundMessageId,
    ) {}

    public function handle(OutboundMessageDeliveryService $deliveryService): void
    {
        $message = OutboundMessage::query()->find($this->outboundMessageId);

        if (! $message) {
            return;
        }

        $deliveryService->deliver($message);
    }
}
