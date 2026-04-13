<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleTranslation;
use App\Support\ContextualHelp\ContextualHelpCache;
use Illuminate\Support\Facades\DB;

class KnowledgeArticleUpsertService
{
    public function __construct(
        protected ContextualHelpCache $contextualHelpCache,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsert(?KnowledgeArticle $knowledgeArticle, array $payload): KnowledgeArticle
    {
        $article = DB::transaction(function () use ($knowledgeArticle, $payload): KnowledgeArticle {
            $knowledgeArticle ??= new KnowledgeArticle;
            $isPublished = (bool) $payload['is_published'];

            $knowledgeArticle->fill([
                'section_id' => (int) $payload['section_id'],
                'slug' => (string) $payload['slug'],
                'sort_order' => (int) $payload['sort_order'],
                'is_published' => $isPublished,
                'published_at' => $isPublished
                    ? ($payload['published_at'] ?? $knowledgeArticle->published_at ?? now())
                    : null,
            ]);
            $knowledgeArticle->save();

            $knowledgeArticle->translations()->delete();

            foreach ((array) $payload['translations'] as $translationPayload) {
                $translation = new KnowledgeArticleTranslation;
                $translation->locale = (string) $translationPayload['locale'];
                $translation->title = (string) $translationPayload['title'];
                $translation->excerpt = $translationPayload['excerpt'] ?? null;
                $translation->body = (string) $translationPayload['body'];

                $knowledgeArticle->translations()->save($translation);
            }

            return $knowledgeArticle->fresh(['translations', 'section.translations']);
        });

        $this->contextualHelpCache->forgetForKnowledgeArticle($article);

        return $article;
    }
}
