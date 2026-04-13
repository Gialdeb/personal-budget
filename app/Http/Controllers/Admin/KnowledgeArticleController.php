<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertKnowledgeArticleRequest;
use App\Http\Resources\Admin\KnowledgeArticleDetailResource;
use App\Http\Resources\Admin\KnowledgeArticleResource;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeSection;
use App\Services\Knowledge\KnowledgeArticleUpsertService;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeArticleController extends Controller
{
    public function index(Request $request): Response
    {
        $articles = KnowledgeArticle::query()
            ->with(['translations', 'section.translations'])
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/KnowledgeBase/Articles/Index', [
            'articles' => KnowledgeArticleResource::collection($articles),
        ]);
    }

    public function create(LocaleResolver $localeResolver): Response
    {
        return Inertia::render('admin/KnowledgeBase/Articles/Edit', [
            'article' => null,
            'sections' => $this->sectionOptions(),
            'supportedLocales' => $localeResolver->available(),
        ]);
    }

    public function store(
        UpsertKnowledgeArticleRequest $request,
        KnowledgeArticleUpsertService $upsertService,
    ): RedirectResponse {
        $knowledgeArticle = $upsertService->upsert(null, $request->validated());

        return redirect()
            ->route('admin.knowledge-articles.edit', $knowledgeArticle->uuid)
            ->with('success', __('admin.knowledge_articles.flash.saved'));
    }

    public function edit(
        KnowledgeArticle $knowledgeArticle,
        LocaleResolver $localeResolver,
    ): Response {
        $knowledgeArticle->load(['translations', 'section.translations']);

        return Inertia::render('admin/KnowledgeBase/Articles/Edit', [
            'article' => (new KnowledgeArticleDetailResource($knowledgeArticle))->resolve(),
            'sections' => $this->sectionOptions(),
            'supportedLocales' => $localeResolver->available(),
        ]);
    }

    public function update(
        UpsertKnowledgeArticleRequest $request,
        KnowledgeArticle $knowledgeArticle,
        KnowledgeArticleUpsertService $upsertService,
    ): RedirectResponse {
        $knowledgeArticle = $upsertService->upsert($knowledgeArticle, $request->validated());

        return redirect()
            ->route('admin.knowledge-articles.edit', $knowledgeArticle->uuid)
            ->with('success', __('admin.knowledge_articles.flash.saved'));
    }

    public function destroy(KnowledgeArticle $knowledgeArticle): RedirectResponse
    {
        $knowledgeArticle->delete();

        return redirect()
            ->route('admin.knowledge-articles.index')
            ->with('success', __('admin.knowledge_articles.flash.deleted'));
    }

    /**
     * @return array<int, array{id: int, uuid: string, slug: string, title: string|null, is_published: bool}>
     */
    protected function sectionOptions(): array
    {
        return KnowledgeSection::query()
            ->with('translations')
            ->ordered()
            ->get()
            ->map(fn (KnowledgeSection $section): array => [
                'id' => $section->id,
                'uuid' => $section->uuid,
                'slug' => $section->slug,
                'title' => $section->translations->firstWhere('locale', 'it')?->title
                    ?? $section->translations->sortBy('locale')->first()?->title,
                'is_published' => (bool) $section->is_published,
            ])
            ->all();
    }
}
