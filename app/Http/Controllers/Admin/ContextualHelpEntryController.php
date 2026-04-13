<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertContextualHelpEntryRequest;
use App\Http\Resources\Admin\ContextualHelpEntryDetailResource;
use App\Http\Resources\Admin\ContextualHelpEntryResource;
use App\Models\ContextualHelpEntry;
use App\Models\KnowledgeArticle;
use App\Services\ContextualHelp\ContextualHelpEntryUpsertService;
use App\Support\ContextualHelp\CurrentContextualHelpResolver;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContextualHelpEntryController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = ContextualHelpEntry::query()
            ->with(['translations', 'knowledgeArticle.translations'])
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/ContextualHelp/Index', [
            'entries' => ContextualHelpEntryResource::collection($entries),
            'pageKeyOptions' => app(CurrentContextualHelpResolver::class)->options(),
        ]);
    }

    public function create(
        LocaleResolver $localeResolver,
        CurrentContextualHelpResolver $contextualHelpResolver,
    ): Response {
        return Inertia::render('admin/ContextualHelp/Edit', [
            'entry' => null,
            'supportedLocales' => $localeResolver->available(),
            'pageKeyOptions' => $contextualHelpResolver->options(),
            'knowledgeArticles' => $this->knowledgeArticleOptions(),
        ]);
    }

    public function store(
        UpsertContextualHelpEntryRequest $request,
        ContextualHelpEntryUpsertService $upsertService,
    ): RedirectResponse {
        $entry = $upsertService->upsert(null, $request->validated());

        return redirect()
            ->route('admin.contextual-help.edit', $entry->uuid)
            ->with('success', __('admin.contextual_help.flash.saved'));
    }

    public function edit(
        ContextualHelpEntry $contextualHelpEntry,
        LocaleResolver $localeResolver,
        CurrentContextualHelpResolver $contextualHelpResolver,
    ): Response {
        $contextualHelpEntry->load([
            'translations',
            'knowledgeArticle.translations',
            'knowledgeArticle.section.translations',
        ]);

        return Inertia::render('admin/ContextualHelp/Edit', [
            'entry' => (new ContextualHelpEntryDetailResource($contextualHelpEntry))->resolve(),
            'supportedLocales' => $localeResolver->available(),
            'pageKeyOptions' => $contextualHelpResolver->options(),
            'knowledgeArticles' => $this->knowledgeArticleOptions(),
        ]);
    }

    public function update(
        UpsertContextualHelpEntryRequest $request,
        ContextualHelpEntry $contextualHelpEntry,
        ContextualHelpEntryUpsertService $upsertService,
    ): RedirectResponse {
        $entry = $upsertService->upsert($contextualHelpEntry, $request->validated());

        return redirect()
            ->route('admin.contextual-help.edit', $entry->uuid)
            ->with('success', __('admin.contextual_help.flash.saved'));
    }

    /**
     * @return array<int, array{id: int, uuid: string, slug: string, title: string|null, is_published: bool}>
     */
    protected function knowledgeArticleOptions(): array
    {
        return KnowledgeArticle::query()
            ->with('translations')
            ->ordered()
            ->get()
            ->map(fn (KnowledgeArticle $article): array => [
                'id' => $article->id,
                'uuid' => $article->uuid,
                'slug' => $article->slug,
                'title' => $article->translations->firstWhere('locale', 'it')?->title
                    ?? $article->translations->sortBy('locale')->first()?->title,
                'is_published' => (bool) $article->is_published,
            ])
            ->all();
    }
}
