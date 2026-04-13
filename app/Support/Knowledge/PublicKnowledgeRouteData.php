<?php

namespace App\Support\Knowledge;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeSection;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class PublicKnowledgeRouteData
{
    public function __construct(
        protected LocaleResolver $localeResolver,
    ) {}

    /**
     * @return Collection<int, KnowledgeSection>
     */
    public function indexSections(Request $request): Collection
    {
        $this->prepareRequest($request);

        return KnowledgeSection::query()
            ->published()
            ->with([
                'translations',
                'articles' => fn ($query) => $query
                    ->published()
                    ->ordered()
                    ->with(['translations', 'section.translations']),
            ])
            ->ordered()
            ->get();
    }

    public function resolveSection(Request $request, KnowledgeSection $section): KnowledgeSection
    {
        $this->prepareRequest($request);

        abort_unless($section->is_published, 404);

        $section->load([
            'translations',
            'articles' => fn ($query) => $query
                ->published()
                ->ordered()
                ->with(['translations', 'section.translations']),
        ]);

        return $section;
    }

    public function resolveArticle(Request $request, KnowledgeArticle $article): KnowledgeArticle
    {
        $this->prepareRequest($request);

        $article->loadMissing([
            'translations',
            'section.translations',
        ]);

        abort_unless($article->is_published && (bool) $article->section?->is_published, 404);

        return $article;
    }

    /**
     * @return Collection<int, KnowledgeArticle>
     */
    public function relatedArticles(KnowledgeArticle $article, Request $request): Collection
    {
        $this->prepareRequest($request);

        return KnowledgeArticle::query()
            ->where('section_id', $article->section_id)
            ->whereKeyNot($article->getKey())
            ->published()
            ->ordered()
            ->with(['translations', 'section.translations'])
            ->limit(3)
            ->get();
    }

    protected function prepareRequest(Request $request): void
    {
        $request->attributes->set(
            'knowledge_locale',
            $this->localeResolver->current($request),
        );
        $request->attributes->set(
            'knowledge_fallback_locale',
            $this->localeResolver->fallback(),
        );
    }
}
