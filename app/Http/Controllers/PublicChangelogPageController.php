<?php

namespace App\Http\Controllers;

use App\Http\Resources\Changelog\PublicChangelogReleaseResource;
use App\Models\ChangelogRelease;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'app' => $this->sharedChangelogMeta(),
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
            'app' => $this->sharedChangelogMeta(),
            'versionLabel' => $versionLabel,
            'initialRelease' => (new PublicChangelogReleaseResource($release))->resolve($request),
            'initialRelatedReleases' => PublicChangelogReleaseResource::collection($relatedReleases)->resolve($request),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedChangelogMeta(): array
    {
        return Cache::remember(
            'inertia:shared:changelog-meta',
            now(config('app.timezone'))->addMinutes(5),
            function (): array {
                $latestPublishedRelease = ChangelogRelease::query()
                    ->where('is_published', true)
                    ->ordered()
                    ->first(['version_label', 'channel']);

                $indexUrl = route('changelog.index');
                $latestReleaseUrl = $latestPublishedRelease === null
                    ? $indexUrl
                    : route('changelog.show', ['versionLabel' => $latestPublishedRelease->version_label]);

                return [
                    'changelog_url' => $latestReleaseUrl,
                    'changelog' => [
                        'index_url' => $indexUrl,
                        'latest_release_label' => $latestPublishedRelease?->version_label,
                        'latest_release_channel' => $latestPublishedRelease?->channel,
                        'latest_release_url' => $latestReleaseUrl,
                        'has_published_release' => $latestPublishedRelease !== null,
                    ],
                ];
            },
        );
    }

    protected function resolveLocale(Request $request, LocaleResolver $localeResolver): string
    {
        return $localeResolver->current($request);
    }
}
