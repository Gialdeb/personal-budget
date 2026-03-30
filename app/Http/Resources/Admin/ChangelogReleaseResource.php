<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangelogReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'version_label' => $this->version_label,
            'channel' => $this->channel,
            'is_published' => (bool) $this->is_published,
            'is_pinned' => (bool) $this->is_pinned,
            'published_at' => $this->published_at?->toJSON(),
            'sort_order' => $this->sort_order,
            'locales' => $this->availableLocales(),
            'title' => $this->translations->first()?->title,
        ];
    }
}
