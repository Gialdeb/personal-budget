<?php

namespace App\Http\Controllers;

use App\Http\Resources\Changelog\PublicChangelogReleaseResource;
use App\Models\ChangelogRelease;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangelogFeedController extends Controller
{
    public function index(Request $request, LocaleResolver $localeResolver): JsonResponse
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

        return response()->json([
            'data' => PublicChangelogReleaseResource::collection($releases)->resolve($request),
        ]);
    }

    public function show(Request $request, string $versionLabel, LocaleResolver $localeResolver): JsonResponse
    {
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

        $request->attributes->set('changelog_locale', $locale);
        $request->attributes->set('changelog_fallback_locale', $localeResolver->fallback());

        return response()->json([
            'data' => (new PublicChangelogReleaseResource($release))->resolve($request),
        ]);
    }

    protected function resolveLocale(Request $request, LocaleResolver $localeResolver): string
    {
        $requestedLocale = $request->query('locale');

        if (is_string($requestedLocale) && $localeResolver->isSupported($requestedLocale)) {
            return $requestedLocale;
        }

        return $localeResolver->current($request);
    }
}
