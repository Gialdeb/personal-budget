<?php

namespace App\Services\ContextualHelp;

use App\Models\ContextualHelpEntry;
use App\Models\ContextualHelpEntryTranslation;
use App\Support\ContextualHelp\ContextualHelpCache;
use Illuminate\Support\Facades\DB;

class ContextualHelpEntryUpsertService
{
    public function __construct(
        protected ContextualHelpCache $contextualHelpCache,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsert(?ContextualHelpEntry $contextualHelpEntry, array $payload): ContextualHelpEntry
    {
        $originalPageKey = $contextualHelpEntry?->page_key;

        $entry = DB::transaction(function () use ($contextualHelpEntry, $payload): ContextualHelpEntry {
            $contextualHelpEntry ??= new ContextualHelpEntry;

            $contextualHelpEntry->fill([
                'page_key' => (string) $payload['page_key'],
                'knowledge_article_id' => $payload['knowledge_article_id'] ?: null,
                'sort_order' => (int) $payload['sort_order'],
                'is_published' => (bool) $payload['is_published'],
            ]);
            $contextualHelpEntry->save();

            $contextualHelpEntry->translations()->delete();

            foreach ((array) $payload['translations'] as $translationPayload) {
                $translation = new ContextualHelpEntryTranslation;
                $translation->locale = (string) $translationPayload['locale'];
                $translation->title = (string) $translationPayload['title'];
                $translation->body = (string) $translationPayload['body'];

                $contextualHelpEntry->translations()->save($translation);
            }

            return $contextualHelpEntry->fresh([
                'translations',
                'knowledgeArticle.translations',
                'knowledgeArticle.section.translations',
            ]);
        });

        if (is_string($originalPageKey) && $originalPageKey !== $entry->page_key) {
            $this->contextualHelpCache->forgetPageKey($originalPageKey);
        }

        $this->contextualHelpCache->forgetEntry($entry);

        return $entry;
    }
}
