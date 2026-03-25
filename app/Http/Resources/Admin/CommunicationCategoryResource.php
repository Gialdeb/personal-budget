<?php

namespace App\Http\Resources\Admin;

use App\Models\CommunicationCategory;
use App\Services\Communication\CommunicationCategoryChannelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CommunicationCategory $category */
        $category = $this->resource;
        /** @var CommunicationCategoryChannelService $channelService */
        $channelService = app(CommunicationCategoryChannelService::class);
        $channelOptions = $channelService->channelOptions($category);

        return [
            'uuid' => $category->uuid,
            'key' => $category->key,
            'name' => $category->name,
            'description' => $category->description,
            'audience' => $category->audience?->value,
            'delivery_mode' => $category->delivery_mode?->value,
            'preference_mode' => $category->preference_mode?->value,
            'context_type' => $category->context_type,
            'is_active' => $category->is_active,
            'fixed_channel' => $channelService->fixedChannelValue($category),
            'channels' => $channelOptions,
        ];
    }
}
