<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeSection;
use App\Models\KnowledgeSectionTranslation;
use App\Support\ContextualHelp\ContextualHelpCache;
use Illuminate\Support\Facades\DB;

class KnowledgeSectionUpsertService
{
    public function __construct(
        protected ContextualHelpCache $contextualHelpCache,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsert(?KnowledgeSection $knowledgeSection, array $payload): KnowledgeSection
    {
        $section = DB::transaction(function () use ($knowledgeSection, $payload): KnowledgeSection {
            $knowledgeSection ??= new KnowledgeSection;

            $knowledgeSection->fill([
                'slug' => (string) $payload['slug'],
                'sort_order' => (int) $payload['sort_order'],
                'is_published' => (bool) $payload['is_published'],
            ]);
            $knowledgeSection->save();

            $knowledgeSection->translations()->delete();

            foreach ((array) $payload['translations'] as $translationPayload) {
                $translation = new KnowledgeSectionTranslation;
                $translation->locale = (string) $translationPayload['locale'];
                $translation->title = (string) $translationPayload['title'];
                $translation->description = $translationPayload['description'] ?? null;

                $knowledgeSection->translations()->save($translation);
            }

            return $knowledgeSection->fresh(['translations']);
        });

        $this->contextualHelpCache->forgetForKnowledgeSection($section);

        return $section;
    }
}
