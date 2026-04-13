<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContextualHelpEntryDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $knowledgeTranslation = $this->knowledgeArticle?->translations?->firstWhere('locale', 'it')
            ?? $this->knowledgeArticle?->translations?->sortBy('locale')->first();

        return [
            'uuid' => $this->uuid,
            'page_key' => $this->page_key,
            'knowledge_article_id' => $this->knowledge_article_id,
            'sort_order' => $this->sort_order,
            'is_published' => (bool) $this->is_published,
            'translations' => $this->translations
                ->sortBy('locale')
                ->values()
                ->map(fn ($translation): array => [
                    'locale' => $translation->locale,
                    'title' => $translation->title,
                    'body' => $translation->body,
                ])
                ->all(),
            'knowledge_article' => $this->knowledgeArticle === null ? null : [
                'id' => $this->knowledgeArticle->id,
                'uuid' => $this->knowledgeArticle->uuid,
                'slug' => $this->knowledgeArticle->slug,
                'title' => $knowledgeTranslation?->title,
                'is_published' => (bool) $this->knowledgeArticle->is_published,
            ],
        ];
    }
}
