<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContextualHelpEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translations = $this->translations->sortBy('locale')->values();
        $primaryTranslation = $translations->firstWhere('locale', 'it')
            ?? $translations->first();
        $knowledgeTranslation = $this->knowledgeArticle?->translations?->firstWhere('locale', 'it')
            ?? $this->knowledgeArticle?->translations?->sortBy('locale')->first();

        return [
            'uuid' => $this->uuid,
            'page_key' => $this->page_key,
            'sort_order' => $this->sort_order,
            'is_published' => (bool) $this->is_published,
            'locales' => $translations->pluck('locale')->all(),
            'title' => $primaryTranslation?->title,
            'knowledge_article' => $this->knowledgeArticle === null ? null : [
                'id' => $this->knowledgeArticle->id,
                'uuid' => $this->knowledgeArticle->uuid,
                'slug' => $this->knowledgeArticle->slug,
                'title' => $knowledgeTranslation?->title,
                'is_published' => (bool) $this->knowledgeArticle->is_published,
            ],
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
