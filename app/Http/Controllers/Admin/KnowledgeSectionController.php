<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertKnowledgeSectionRequest;
use App\Http\Resources\Admin\KnowledgeSectionDetailResource;
use App\Http\Resources\Admin\KnowledgeSectionResource;
use App\Models\KnowledgeSection;
use App\Services\Knowledge\KnowledgeSectionUpsertService;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeSectionController extends Controller
{
    public function index(Request $request): Response
    {
        $sections = KnowledgeSection::query()
            ->with('translations')
            ->withCount('articles')
            ->withCount(['articles as published_articles_count' => fn ($query) => $query->published()])
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/KnowledgeBase/Sections/Index', [
            'sections' => KnowledgeSectionResource::collection($sections),
        ]);
    }

    public function create(LocaleResolver $localeResolver): Response
    {
        return Inertia::render('admin/KnowledgeBase/Sections/Edit', [
            'section' => null,
            'supportedLocales' => $localeResolver->available(),
        ]);
    }

    public function store(
        UpsertKnowledgeSectionRequest $request,
        KnowledgeSectionUpsertService $upsertService,
    ): RedirectResponse {
        $knowledgeSection = $upsertService->upsert(null, $request->validated());

        return redirect()
            ->route('admin.knowledge-sections.edit', $knowledgeSection->uuid)
            ->with('success', __('admin.knowledge_sections.flash.saved'));
    }

    public function edit(
        KnowledgeSection $knowledgeSection,
        LocaleResolver $localeResolver,
    ): Response {
        $knowledgeSection->load('translations')
            ->loadCount('articles');

        return Inertia::render('admin/KnowledgeBase/Sections/Edit', [
            'section' => (new KnowledgeSectionDetailResource($knowledgeSection))->resolve(),
            'supportedLocales' => $localeResolver->available(),
        ]);
    }

    public function update(
        UpsertKnowledgeSectionRequest $request,
        KnowledgeSection $knowledgeSection,
        KnowledgeSectionUpsertService $upsertService,
    ): RedirectResponse {
        $knowledgeSection = $upsertService->upsert($knowledgeSection, $request->validated());

        return redirect()
            ->route('admin.knowledge-sections.edit', $knowledgeSection->uuid)
            ->with('success', __('admin.knowledge_sections.flash.saved'));
    }

    public function destroy(KnowledgeSection $knowledgeSection): RedirectResponse
    {
        $knowledgeSection->delete();

        return redirect()
            ->route('admin.knowledge-sections.index')
            ->with('success', __('admin.knowledge_sections.flash.deleted'));
    }
}
