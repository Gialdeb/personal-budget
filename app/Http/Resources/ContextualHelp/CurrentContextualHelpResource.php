<?php

namespace App\Http\Resources\ContextualHelp;

use App\Support\ContextualHelp\CurrentContextualHelpResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentContextualHelpResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = (string) $request->attributes->get('contextual_help_locale', 'it');
        $fallbackLocale = (string) $request->attributes->get('contextual_help_fallback_locale', 'en');
        $translation = $this->resolveTranslation($locale, $fallbackLocale);
        $knowledgeArticle = app(CurrentContextualHelpResolver::class)
            ->publicKnowledgeArticle($this->knowledgeArticle);
        $knowledgeTranslation = $knowledgeArticle?->resolveTranslation($locale, $fallbackLocale);

        return [
            'uuid' => $this->uuid,
            'page_key' => $this->page_key,
            'sort_order' => $this->sort_order,
            'locale' => $translation?->locale ?? $locale,
            'available_locales' => $this->availableLocales(),
            'title' => $translation?->title,
            'body' => $translation?->body,
            'knowledge_article' => $knowledgeArticle === null ? null : [
                'uuid' => $knowledgeArticle->uuid,
                'slug' => $knowledgeArticle->slug,
                'title' => $knowledgeTranslation?->title,
                'url' => route('help-center.articles.show', [
                    'knowledgeArticle' => $knowledgeArticle->slug,
                ]),
            ],
        ];
    }
}
