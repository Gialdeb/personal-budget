<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationInboxItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->id,
            'type' => $this->type,
            'category' => [
                'key' => data_get($this->data, 'category.key'),
                'name' => data_get($this->data, 'category.name'),
            ],
            'presentation' => [
                'layout' => data_get($this->data, 'presentation.layout', 'standard_card'),
                'icon' => data_get($this->data, 'presentation.icon', 'notification'),
                'image_url' => data_get($this->data, 'presentation.image_url'),
            ],
            'content' => [
                'title' => data_get($this->data, 'content.title'),
                'message' => data_get($this->data, 'content.message'),
                'cta_label' => data_get($this->data, 'content.cta_label'),
                'cta_url' => data_get($this->data, 'content.cta_url'),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'is_read' => $this->read(),
            'is_unread' => $this->unread(),
        ];
    }
}
