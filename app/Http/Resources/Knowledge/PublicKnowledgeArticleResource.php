<?php

namespace App\Http\Resources\Knowledge;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicKnowledgeArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = (string) $request->attributes->get('knowledge_locale', 'it');
        $fallbackLocale = (string) $request->attributes->get('knowledge_fallback_locale', 'en');
        $translation = $this->resolveTranslation($locale, $fallbackLocale);
        $sectionTranslation = $this->section?->resolveTranslation($locale, $fallbackLocale);

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'published_at' => $this->published_at?->toJSON(),
            'locale' => $translation?->locale ?? $locale,
            'available_locales' => $this->availableLocales(),
            'title' => $translation?->title,
            'excerpt' => $translation?->excerpt,
            'body' => $translation?->body,
            'section' => $this->section === null ? null : [
                'uuid' => $this->section->uuid,
                'slug' => $this->section->slug,
                'title' => $sectionTranslation?->title,
            ],
        ];
    }
}
