<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeSectionDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'is_published' => (bool) $this->is_published,
            'article_count' => (int) ($this->articles_count ?? 0),
            'translations' => $this->translations
                ->sortBy('locale')
                ->values()
                ->map(fn ($translation): array => [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'description' => $translation->description,
                ])
                ->all(),
        ];
    }
}
