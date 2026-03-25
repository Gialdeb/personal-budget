<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManualCommunicationDispatchResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $messages = collect($this->resource['messages'] ?? []);

        return [
            'outbound_count' => $messages->count(),
            'recipient_count' => $messages->pluck('recipient_id')->unique()->count(),
            'channel_count' => $messages->pluck('channel')->unique()->count(),
            'messages' => $messages
                ->map(fn ($message) => [
                    'uuid' => $message->uuid,
                    'channel' => $message->channel?->value,
                    'channel_label' => $this->translatedValue(
                        "admin.communication_composer.channels.{$message->channel?->value}"
                    ),
                    'status' => $message->status?->value,
                    'queued_at' => $message->queued_at?->toJSON(),
                    'subject' => $message->subject_resolved,
                    'title' => $message->title_resolved,
                ])
                ->values()
                ->all(),
        ];
    }

    protected function translatedValue(string $key, ?string $fallback = null): string
    {
        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return $fallback ?? $key;
    }
}
