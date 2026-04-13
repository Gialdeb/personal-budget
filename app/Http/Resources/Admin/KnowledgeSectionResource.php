<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translations = $this->translations->sortBy('locale')->values();
        $primaryTranslation = $translations->firstWhere('locale', 'it')
            ?? $translations->first();

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'is_published' => (bool) $this->is_published,
            'article_count' => (int) ($this->articles_count ?? 0),
            'published_article_count' => (int) ($this->published_articles_count ?? 0),
            'locales' => $translations->pluck('locale')->all(),
            'title' => $primaryTranslation?->title,
            'description' => $primaryTranslation?->description,
        ];
    }
}
