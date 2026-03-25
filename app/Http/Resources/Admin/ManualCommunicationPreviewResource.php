<?php

namespace App\Http\Resources\Admin;

use App\Data\Communication\ComposedCommunicationData;
use App\Enums\CommunicationChannelEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManualCommunicationPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{
         *  category: array<string, mixed>,
         *  sample_recipient: array<string, mixed>,
         *  recipient_count: int,
         *  locale: array<string, mixed>,
         *  content_mode: string,
         *  previews: array<int, array{composed: ComposedCommunicationData, context: array<string, mixed>}>
         * } $resource
         */
        $resource = $this->resource;

        return [
            'category' => $resource['category'],
            'sample_recipient' => $resource['sample_recipient'],
            'recipient_count' => $resource['recipient_count'],
            'locale' => $resource['locale'],
            'content_mode' => $resource['content_mode'],
            'previews' => collect($resource['previews'])
                ->map(function (array $entry): array {
                    $composed = $entry['composed'];
                    $channel = $composed->channel;

                    return [
                        'channel' => [
                            'value' => $channel->value,
                            'label' => $this->translatedValue("admin.communication_composer.channels.{$channel->value}"),
                        ],
                        'template' => [
                            'uuid' => $composed->template->uuid,
                            'key' => $composed->template->key,
                            'name' => $composed->template->name,
                        ],
                        'context' => $entry['context'],
                        'content' => [
                            'subject' => $composed->subject,
                            'title' => $composed->title,
                            'body' => $composed->body,
                            'cta_label' => $composed->ctaLabel,
                            'cta_url' => $composed->ctaUrl,
                        ],
                        'presentation' => [
                            'layout' => $channel === CommunicationChannelEnum::MAIL ? 'mail' : 'notification',
                        ],
                    ];
                })
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
