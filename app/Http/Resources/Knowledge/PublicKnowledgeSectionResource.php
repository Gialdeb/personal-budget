<?php

namespace App\Http\Resources\Knowledge;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicKnowledgeSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = (string) $request->attributes->get('knowledge_locale', 'it');
        $fallbackLocale = (string) $request->attributes->get('knowledge_fallback_locale', 'en');
        $translation = $this->resolveTranslation($locale, $fallbackLocale);
        $articles = $this->relationLoaded('articles')
            ? $this->articles
                ->where('is_published', true)
                ->sortBy('sort_order')
                ->values()
            : collect();

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'locale' => $translation?->locale ?? $locale,
            'available_locales' => $this->availableLocales(),
            'title' => $translation?->title,
            'description' => $translation?->description,
            'article_count' => $articles->count(),
            'articles' => PublicKnowledgeArticleResource::collection($articles)->resolve($request),
        ];
    }
}
