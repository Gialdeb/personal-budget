<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannelEnum;
use App\Models\CommunicationCategory;
use App\Models\CommunicationCategoryChannelTemplate;
use InvalidArgumentException;

class CommunicationCategoryTemplateResolver
{
    public function __construct(
        protected CommunicationCategoryChannelService $categoryChannelService,
    ) {}

    public function resolve(string $categoryKey, CommunicationChannelEnum $channel): CommunicationCategoryChannelTemplate
    {
        $category = CommunicationCategory::query()
            ->where('key', $categoryKey)
            ->where('is_active', true)
            ->with(['channelTemplates' => fn ($query) => $query->with('template')])
            ->first();

        if (! $category) {
            throw new InvalidArgumentException("Communication category [{$categoryKey}] does not exist or is not active.");
        }

        $mapping = $this->categoryChannelService->resolveDefaultMapping($category, $channel);

        if (! $mapping || ! $mapping->template || ! $mapping->template->is_active) {
            throw new InvalidArgumentException(
                "No active default template mapping found for category [{$categoryKey}] and channel [{$channel->value}]."
            );
        }

        return $mapping;
    }
}
