<?php

namespace App\Support\ContextualHelp;

use App\Models\ContextualHelpEntry;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeSection;
use App\Supports\Locale\LocaleResolver;
use Closure;
use Illuminate\Support\Facades\Cache;

class ContextualHelpCache
{
    public function __construct(
        protected LocaleResolver $localeResolver,
    ) {}

    /**
     * @param  Closure(): array<string, mixed>|null  $resolver
     * @return array<string, mixed>|null
     */
    public function remember(string $pageKey, string $locale, Closure $resolver): ?array
    {
        return Cache::remember(
            $this->payloadKey($pageKey, $locale),
            now(config('app.timezone'))->addMinutes(30),
            $resolver,
        );
    }

    public function forgetPageKey(string $pageKey): void
    {
        foreach ($this->locales() as $locale) {
            Cache::forget($this->payloadKey($pageKey, $locale));
        }
    }

    public function forgetEntry(ContextualHelpEntry $entry): void
    {
        $this->forgetPageKey($entry->page_key);
    }

    public function forgetForKnowledgeArticle(KnowledgeArticle $knowledgeArticle): void
    {
        ContextualHelpEntry::query()
            ->where('knowledge_article_id', $knowledgeArticle->id)
            ->pluck('page_key')
            ->each(fn ($pageKey): bool => $this->forgetPageKey((string) $pageKey) || true);
    }

    public function forgetForKnowledgeSection(KnowledgeSection $knowledgeSection): void
    {
        ContextualHelpEntry::query()
            ->whereIn(
                'knowledge_article_id',
                KnowledgeArticle::query()
                    ->where('section_id', $knowledgeSection->id)
                    ->select('id'),
            )
            ->pluck('page_key')
            ->each(fn ($pageKey): bool => $this->forgetPageKey((string) $pageKey) || true);
    }

    protected function payloadKey(string $pageKey, string $locale): string
    {
        return sprintf('contextual_help:%s:%s', $pageKey, $locale);
    }

    /**
     * @return array<int, string>
     */
    protected function locales(): array
    {
        $locales = $this->localeResolver->supportedCodes();
        $fallback = $this->localeResolver->fallback();

        if (! in_array($fallback, $locales, true)) {
            $locales[] = $fallback;
        }

        return array_values(array_unique($locales));
    }
}
