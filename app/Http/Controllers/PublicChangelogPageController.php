<?php

namespace App\Http\Controllers;

use App\Http\Resources\Changelog\PublicChangelogReleaseResource;
use App\Models\ChangelogRelease;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class PublicChangelogPageController extends Controller
{
    public function index(Request $request, LocaleResolver $localeResolver): Response
    {
        $locale = $this->resolveLocale($request, $localeResolver);
        $releases = ChangelogRelease::query()
            ->where('is_published', true)
            ->with([
                'translations',
                'sections.translations',
                'sections.items.translations',
            ])
            ->ordered()
            ->get();

        $request->attributes->set('changelog_locale', $locale);
        $request->attributes->set('changelog_fallback_locale', $localeResolver->fallback());

        return Inertia::render('changelog/Index', [
            'canRegister' => Features::enabled(Features::registration()),
            'initialReleases' => PublicChangelogReleaseResource::collection($releases)->resolve($request),
        ]);
    }

    public function show(
        Request $request,
        string $versionLabel,
        LocaleResolver $localeResolver,
    ): Response {
        $locale = $this->resolveLocale($request, $localeResolver);

        $release = ChangelogRelease::query()
            ->where('is_published', true)
            ->where('version_label', $versionLabel)
            ->with([
                'translations',
                'sections.translations',
                'sections.items.translations',
            ])
            ->firstOrFail();

        $relatedReleases = ChangelogRelease::query()
            ->where('is_published', true)
            ->whereKeyNot($release->getKey())
            ->with([
                'translations',
                'sections.translations',
                'sections.items.translations',
            ])
            ->ordered()
            ->get();

        $request->attributes->set('changelog_locale', $locale);
        $request->attributes->set('changelog_fallback_locale', $localeResolver->fallback());

        return Inertia::render('changelog/Show', [
            'canRegister' => Features::enabled(Features::registration()),
            'versionLabel' => $versionLabel,
            'initialRelease' => (new PublicChangelogReleaseResource($release))->resolve($request),
            'initialRelatedReleases' => PublicChangelogReleaseResource::collection($relatedReleases)->resolve($request),
        ]);
    }

    protected function resolveLocale(Request $request, LocaleResolver $localeResolver): string
    {
        return $localeResolver->current($request);
    }
}
