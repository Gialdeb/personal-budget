<?php

namespace App\Http\Resources\Admin;

use App\Enums\CommunicationChannelEnum;
use App\Models\CommunicationCategory;
use App\Services\Communication\CommunicationCategoryChannelService;
use App\Services\Communication\ManualCommunicationCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManualCommunicationCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CommunicationCategory $category */
        $category = $this->resource;
        /** @var ManualCommunicationCatalogService $catalog */
        $catalog = app(ManualCommunicationCatalogService::class);
        /** @var CommunicationCategoryChannelService $channelService */
        $channelService = app(CommunicationCategoryChannelService::class);
        $channels = $catalog->availableChannels($category);

        return [
            'uuid' => $category->uuid,
            'key' => $category->key,
            'name' => $this->translatedValue(
                "admin.communication_composer.categories.{$category->key}.name",
                $category->name,
            ),
            'description' => $this->translatedValue(
                "admin.communication_composer.categories.{$category->key}.description",
                $category->description,
            ),
            'context_type' => $category->context_type,
            'channels' => array_map(function (CommunicationChannelEnum $channel): array {
                return [
                    'value' => $channel->value,
                    'label' => $this->translatedValue("admin.communication_composer.channels.{$channel->value}"),
                ];
            }, $channels),
            'channel_options' => collect($channelService->channelOptions($category))
                ->map(fn (array $channel): array => [
                    ...$channel,
                    'label' => $this->translatedValue("admin.communication_composer.channels.{$channel['value']}"),
                ])
                ->values()
                ->all(),
            'default_channel' => $channels[0]->value ?? null,
            'fixed_channel' => $channelService->fixedChannelValue($category),
            'available_variables' => $this->availableVariables($category),
            'supported_content_modes' => ['template', 'custom'],
            'flags' => [
                'available_for_manual_send' => $channelService->availableForManualSend($category),
                'can_preview' => $channels !== [],
                'can_send' => $channels !== [],
                'requires_context' => $category->context_type !== 'user',
            ],
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

    /**
     * @return array<int, string>
     */
    protected function availableVariables(CommunicationCategory $category): array
    {
        $variables = [];

        foreach ($category->channelTemplates as $mapping) {
            foreach ([
                $mapping->template?->subject_template,
                $mapping->template?->title_template,
                $mapping->template?->body_template,
                $mapping->template?->cta_label_template,
                $mapping->template?->cta_url_template,
            ] as $value) {
                if (! is_string($value) || $value === '') {
                    continue;
                }

                preg_match_all('/(?::|\\{)([a-zA-Z0-9_\\.]+)\\}?/', $value, $matches);

                foreach ($matches[1] ?? [] as $variable) {
                    $variables[$variable] = $variable;
                }
            }
        }

        ksort($variables);

        return array_values($variables);
    }
}
