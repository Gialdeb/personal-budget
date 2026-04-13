<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translations = $this->translations->sortBy('locale')->values();
        $primaryTranslation = $translations->firstWhere('locale', 'it')
            ?? $translations->first();
        $sectionTranslation = $this->section?->translations?->firstWhere('locale', 'it')
            ?? $this->section?->translations?->sortBy('locale')->first();

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'is_published' => (bool) $this->is_published,
            'published_at' => $this->published_at?->toJSON(),
            'locales' => $translations->pluck('locale')->all(),
            'title' => $primaryTranslation?->title,
            'excerpt' => $primaryTranslation?->excerpt,
            'section' => $this->section === null ? null : [
                'id' => $this->section->id,
                'uuid' => $this->section->uuid,
                'slug' => $this->section->slug,
                'title' => $sectionTranslation?->title,
                'is_published' => (bool) $this->section->is_published,
            ],
        ];
    }
}
