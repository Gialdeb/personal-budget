<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeArticleDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sectionTranslation = $this->section?->translations?->firstWhere('locale', 'it')
            ?? $this->section?->translations?->sortBy('locale')->first();

        return [
            'uuid' => $this->uuid,
            'section_id' => $this->section_id,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'is_published' => (bool) $this->is_published,
            'published_at' => $this->published_at?->format('Y-m-d\TH:i'),
            'translations' => $this->translations
                ->sortBy('locale')
                ->values()
                ->map(fn ($translation): array => [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'excerpt' => $translation->excerpt,
                    'body' => $translation->body,
                ])
                ->all(),
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
