<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannelEnum;
use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationPreferenceModeEnum;
use App\Models\CommunicationCategory;
use App\Models\CommunicationCategoryChannelTemplate;
use App\Models\CommunicationTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class CommunicationCategoryChannelService
{
    public function __construct(
        protected CommunicationChannelRegistry $channelRegistry,
    ) {}

    /**
     * @return Builder<CommunicationCategory>
     */
    public function categoriesQuery(): Builder
    {
        return CommunicationCategory::query()
            ->with([
                'channelTemplates' => fn ($query) => $query
                    ->with('template')
                    ->orderByDesc('is_default')
                    ->orderByDesc('is_active')
                    ->orderBy('channel'),
            ])
            ->orderBy('name')
            ->orderBy('key');
    }

    /**
     * @return Collection<int, CommunicationCategoryChannelTemplate>
     */
    public function defaultMappings(CommunicationCategory $category): Collection
    {
        $mappings = $category->relationLoaded('channelTemplates')
            ? $category->channelTemplates
            : $category->channelTemplates()->with('template')->get();

        return $mappings
            ->filter(fn (CommunicationCategoryChannelTemplate $mapping) => $mapping->is_default)
            ->values();
    }

    /**
     * @return Collection<int, CommunicationCategoryChannelTemplate>
     */
    public function activeDefaultMappings(CommunicationCategory $category): Collection
    {
        return $this->defaultMappings($category)
            ->filter(function (CommunicationCategoryChannelTemplate $mapping): bool {
                $channelValue = $mapping->channel instanceof CommunicationChannelEnum
                    ? $mapping->channel->value
                    : (string) $mapping->channel;

                return $mapping->is_active
                    && $mapping->template?->is_active
                    && $this->channelRegistry->isGloballyAvailable($channelValue);
            })
            ->values();
    }

    /**
     * @return array<int, CommunicationChannelEnum>
     */
    public function activeDefaultChannels(CommunicationCategory $category): array
    {
        return $this->activeDefaultMappings($category)
            ->map(function (CommunicationCategoryChannelTemplate $mapping): CommunicationChannelEnum {
                return $mapping->channel instanceof CommunicationChannelEnum
                    ? $mapping->channel
                    : CommunicationChannelEnum::from($mapping->channel);
            })
            ->unique(fn (CommunicationChannelEnum $channel) => $channel->value)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function displayChannelValues(): array
    {
        return $this->channelRegistry->values();
    }

    /**
     * @return array<int, string>
     */
    public function selectableChannelValues(CommunicationCategory $category): array
    {
        return array_map(
            static fn (CommunicationChannelEnum $channel): string => $channel->value,
            $this->activeDefaultChannels($category),
        );
    }

    public function fixedChannelValue(CommunicationCategory $category): ?string
    {
        $selectableChannels = $this->selectableChannelValues($category);

        if (
            $category->preference_mode === NotificationPreferenceModeEnum::MANDATORY
            && count($selectableChannels) === 1
        ) {
            return $selectableChannels[0];
        }

        return null;
    }

    public function availableForManualSend(CommunicationCategory $category): bool
    {
        if (! $category->is_active) {
            return false;
        }

        if (! in_array($category->audience?->value, [
            NotificationAudienceEnum::USER->value,
            NotificationAudienceEnum::BOTH->value,
        ], true)) {
            return false;
        }

        if ($category->context_type !== 'user') {
            return false;
        }

        return $this->activeDefaultChannels($category) !== [];
    }

    /**
     * @return array<int, array{
     *     value:string,
     *     label:string,
     *     is_globally_available:bool,
     *     is_globally_enabled:bool,
     *     is_transport_ready:bool,
     *     is_supported:bool,
     *     is_selectable:bool,
     *     is_disabled:bool,
     *     is_fixed:bool,
     *     mapping_uuid:?string,
     *     template:?array{uuid:string,key:string,name:string}
     * }>
     */
    public function channelOptions(CommunicationCategory $category): array
    {
        $mappings = $this->defaultMappings($category)->keyBy(
            fn (CommunicationCategoryChannelTemplate $mapping) => $mapping->channel instanceof CommunicationChannelEnum
                ? $mapping->channel->value
                : (string) $mapping->channel,
        );
        $selectableChannelValues = $this->selectableChannelValues($category);
        $fixedChannelValue = $this->fixedChannelValue($category);

        return collect($this->displayChannelValues())
            ->map(function (string $channelValue) use ($mappings, $selectableChannelValues, $fixedChannelValue): array {
                /** @var CommunicationCategoryChannelTemplate|null $mapping */
                $mapping = $mappings->get($channelValue);
                $isSelectable = in_array($channelValue, $selectableChannelValues, true);
                $isFixed = $fixedChannelValue === $channelValue;

                return [
                    'value' => $channelValue,
                    'label' => $this->channelRegistry->label($channelValue),
                    'is_globally_available' => $this->channelRegistry->isGloballyAvailable($channelValue),
                    'is_globally_enabled' => $this->channelRegistry->isEnabled($channelValue),
                    'is_transport_ready' => $this->channelRegistry->isTransportReady($channelValue),
                    'is_supported' => $isSelectable,
                    'is_selectable' => $isSelectable && ! $isFixed,
                    'is_disabled' => ! $isSelectable || $isFixed,
                    'is_fixed' => $isFixed,
                    'mapping_uuid' => $mapping?->uuid,
                    'template' => $mapping?->template
                        ? [
                            'uuid' => $mapping->template->uuid,
                            'key' => $mapping->template->key,
                            'name' => $mapping->template->name,
                        ]
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    public function resolveDefaultMapping(
        CommunicationCategory $category,
        CommunicationChannelEnum $channel,
    ): ?CommunicationCategoryChannelTemplate {
        return $this->activeDefaultMappings($category)
            ->first(fn (CommunicationCategoryChannelTemplate $mapping) => $mapping->channel === $channel);
    }

    /**
     * @return Collection<int, CommunicationTemplate>
     */
    public function templateOptionsForChannel(string|CommunicationChannelEnum $channel): Collection
    {
        $channelValue = $channel instanceof CommunicationChannelEnum ? $channel->value : $channel;

        if (! $this->channelRegistry->has($channelValue)) {
            return collect();
        }

        return CommunicationTemplate::query()
            ->where('channel', $channelValue)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int, array{value:string, enabled:bool, template_uuid:?string}>  $channels
     */
    public function syncDefaultMappings(CommunicationCategory $category, array $channels): void
    {
        foreach ($channels as $channelPayload) {
            $channelValue = (string) Arr::get($channelPayload, 'value');
            $enabled = (bool) Arr::get($channelPayload, 'enabled', false);
            $templateUuid = Arr::get($channelPayload, 'template_uuid');

            if (! $this->channelRegistry->has($channelValue)) {
                throw new InvalidArgumentException("Unsupported channel [{$channelValue}].");
            }

            $category->channelTemplates()
                ->where('channel', $channelValue)
                ->update([
                    'is_default' => false,
                    'is_active' => false,
                ]);

            if (! $enabled || ! is_string($templateUuid) || $templateUuid === '') {
                continue;
            }

            if (! $this->channelRegistry->isGloballyAvailable($channelValue)) {
                throw new InvalidArgumentException(
                    __('admin.communication_categories.validation.channel_globally_unavailable', [
                        'channel' => $this->channelRegistry->label($channelValue),
                    ]),
                );
            }

            $template = CommunicationTemplate::query()
                ->where('uuid', $templateUuid)
                ->where('channel', $channelValue)
                ->where('is_active', true)
                ->first();

            if (! $template) {
                throw new InvalidArgumentException(
                    __('admin.communication_categories.validation.template_invalid', [
                        'channel' => $this->channelRegistry->label($channelValue),
                    ]),
                );
            }

            CommunicationCategoryChannelTemplate::query()->updateOrCreate(
                [
                    'communication_category_id' => $category->id,
                    'communication_template_id' => $template->id,
                    'channel' => $channelValue,
                ],
                [
                    'is_default' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
