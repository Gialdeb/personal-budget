<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertChangelogReleaseRequest;
use App\Http\Resources\Admin\ChangelogReleaseDetailResource;
use App\Http\Resources\Admin\ChangelogReleaseResource;
use App\Models\ChangelogRelease;
use App\Services\Changelog\ChangelogReleaseUpsertService;
use App\Services\Changelog\ChangelogVersionSuggestionService;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChangelogReleaseController extends Controller
{
    public function index(
        Request $request,
        ChangelogVersionSuggestionService $versionSuggestionService,
        LocaleResolver $localeResolver,
    ): Response {
        $releases = ChangelogRelease::query()
            ->with('translations')
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/Changelog/Index', [
            'releases' => ChangelogReleaseResource::collection($releases),
            'latestRelease' => ChangelogRelease::query()->ordered()->first()?->version_label,
            'versionSuggestions' => $versionSuggestionService->suggestions(),
            'supportedLocales' => $localeResolver->available(),
        ]);
    }

    public function create(
        ChangelogVersionSuggestionService $versionSuggestionService,
        LocaleResolver $localeResolver,
    ): Response {
        return Inertia::render('admin/Changelog/Edit', [
            'release' => null,
            'latestRelease' => ChangelogRelease::query()->ordered()->first()?->version_label,
            'versionSuggestions' => $versionSuggestionService->suggestions(),
            'supportedLocales' => $localeResolver->available(),
        ]);
    }

    public function store(
        UpsertChangelogReleaseRequest $request,
        ChangelogReleaseUpsertService $upsertService,
    ): RedirectResponse {
        $release = $upsertService->upsert(null, $request->validated());

        return redirect()
            ->route('admin.changelog.edit', $release->uuid)
            ->with('success', __('admin.changelog.flash.saved'));
    }

    public function edit(
        ChangelogRelease $changelogRelease,
        ChangelogVersionSuggestionService $versionSuggestionService,
        LocaleResolver $localeResolver,
    ): Response {
        $changelogRelease->load([
            'translations',
            'sections.translations',
            'sections.items.translations',
        ]);

        return Inertia::render('admin/Changelog/Edit', [
            'release' => (new ChangelogReleaseDetailResource($changelogRelease))->resolve(),
            'latestRelease' => ChangelogRelease::query()
                ->whereKeyNot($changelogRelease->getKey())
                ->ordered()
                ->first()?->version_label,
            'versionSuggestions' => $versionSuggestionService->suggestions($changelogRelease),
            'supportedLocales' => $localeResolver->available(),
        ]);
    }

    public function update(
        UpsertChangelogReleaseRequest $request,
        ChangelogRelease $changelogRelease,
        ChangelogReleaseUpsertService $upsertService,
    ): RedirectResponse {
        $release = $upsertService->upsert($changelogRelease, $request->validated());

        return redirect()
            ->route('admin.changelog.edit', $release->uuid)
            ->with('success', __('admin.changelog.flash.saved'));
    }
}
