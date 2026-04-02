<?php

namespace App\Support\Seo;

use App\Http\Resources\Changelog\PublicChangelogReleaseResource;
use App\Models\ChangelogRelease;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\Request;

class PublicPageSeoResolver
{
    public function __construct(
        protected LocaleResolver $localeResolver,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(Request $request): ?array
    {
        $routeName = $request->route()?->getName();

        if (! is_string($routeName) || ! $this->isIndexablePublicRoute($routeName)) {
            return null;
        }

        $locale = $this->localeResolver->current($request);
        $canonicalUrl = $this->canonicalUrl($routeName, $request);
        $pageMeta = $this->pageMeta($routeName, $request, $locale);

        return [
            'title' => $pageMeta['title'],
            'description' => $pageMeta['description'],
            'canonical_url' => $canonicalUrl,
            'robots' => 'index,follow',
            'og_type' => $pageMeta['og_type'] ?? 'website',
            'locale' => $locale,
            'alternates' => [],
            'json_ld' => $this->jsonLd($routeName, $request, $locale, $canonicalUrl, $pageMeta),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function staticIndexableRouteNames(): array
    {
        return [
            'home',
            'features',
            'pricing',
            'about-me',
            'customers',
            'download-app',
            'changelog.index',
            'terms-of-service',
            'privacy',
        ];
    }

    public function isIndexablePublicRoute(string $routeName): bool
    {
        return in_array($routeName, [
            ...$this->staticIndexableRouteNames(),
            'changelog.show',
        ], true);
    }

    /**
     * @return array<int, array{url: string, lastmod: string|null}>
     */
    public function sitemapEntries(): array
    {
        $entries = collect($this->staticIndexableRouteNames())
            ->map(fn (string $routeName): array => [
                'url' => route($routeName),
                'lastmod' => null,
            ]);

        $changelogEntries = ChangelogRelease::query()
            ->where('is_published', true)
            ->ordered()
            ->get(['version_label', 'updated_at', 'published_at'])
            ->map(fn (ChangelogRelease $release): array => [
                'url' => route('changelog.show', ['versionLabel' => $release->version_label]),
                'lastmod' => $release->updated_at?->toAtomString()
                    ?? $release->published_at?->toAtomString(),
            ]);

        return $entries
            ->concat($changelogEntries)
            ->unique('url')
            ->values()
            ->all();
    }

    protected function appUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    protected function canonicalUrl(string $routeName, Request $request): string
    {
        if ($routeName === 'changelog.show') {
            return route('changelog.show', [
                'versionLabel' => (string) $request->route('versionLabel'),
            ]);
        }

        return route($routeName);
    }

    /**
     * @return array{title: string, description: string, og_type?: string}
     */
    protected function pageMeta(string $routeName, Request $request, string $locale): array
    {
        if ($routeName === 'changelog.show') {
            return $this->changelogShowMeta($request, $locale);
        }

        $translationKey = 'seo.pages.'.$routeName;

        return [
            'title' => (string) __($translationKey.'.title'),
            'description' => (string) __($translationKey.'.description'),
            'og_type' => $routeName === 'home' ? 'website' : 'article',
        ];
    }

    /**
     * @return array{title: string, description: string, og_type: string}
     */
    protected function changelogShowMeta(Request $request, string $locale): array
    {
        $release = $this->resolveChangelogRelease($request, $locale);
        $versionLabel = (string) $request->route('versionLabel');

        if ($release === null) {
            return [
                'title' => __('seo.pages.changelog.show.fallback_title', [
                    'version' => $versionLabel,
                ]),
                'description' => (string) __('seo.pages.changelog.show.fallback_description'),
                'og_type' => 'article',
            ];
        }

        return [
            'title' => $release['title'] !== null && $release['title'] !== ''
                ? sprintf('%s (%s)', $release['title'], $release['version_label'])
                : __('seo.pages.changelog.show.fallback_title', [
                    'version' => $versionLabel,
                ]),
            'description' => (string) ($release['excerpt']
                ?? $release['summary']
                ?? __('seo.pages.changelog.show.fallback_description')),
            'og_type' => 'article',
        ];
    }

    /**
     * @param  array{title: string, description: string, og_type?: string}  $pageMeta
     * @return array<int, array<string, mixed>>
     */
    protected function jsonLd(
        string $routeName,
        Request $request,
        string $locale,
        string $canonicalUrl,
        array $pageMeta,
    ): array {
        $schemas = [
            [
                '@context' => 'https://schema.org',
                '@type' => $routeName === 'changelog.index' ? 'CollectionPage' : 'WebPage',
                'name' => $pageMeta['title'],
                'description' => $pageMeta['description'],
                'url' => $canonicalUrl,
                'inLanguage' => $locale,
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => config('app.name'),
                    'url' => $this->appUrl(),
                ],
            ],
        ];

        if ($routeName === 'home') {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => config('app.name'),
                'url' => $this->appUrl(),
                'logo' => $this->appUrl().'/favicon.svg',
                'description' => __('seo.organization.description'),
            ];

            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => $this->appUrl(),
                'inLanguage' => $locale,
                'description' => __('seo.website.description'),
            ];
        }

        if (in_array($routeName, ['home', 'features', 'pricing', 'customers', 'download-app'], true)) {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => config('app.name'),
                'url' => $this->appUrl(),
                'applicationCategory' => __('seo.software_application.category'),
                'operatingSystem' => __('seo.software_application.operating_system'),
                'description' => __('seo.software_application.description'),
                'featureList' => array_values((array) __('seo.software_application.feature_list')),
                'inLanguage' => $locale,
            ];
        }

        return $schemas;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveChangelogRelease(Request $request, string $locale): ?array
    {
        $versionLabel = $request->route('versionLabel');

        if (! is_string($versionLabel) || $versionLabel === '') {
            return null;
        }

        $release = ChangelogRelease::query()
            ->where('is_published', true)
            ->where('version_label', $versionLabel)
            ->with([
                'translations',
                'sections.translations',
                'sections.items.translations',
            ])
            ->first();

        if (! $release instanceof ChangelogRelease) {
            return null;
        }

        $request->attributes->set('changelog_locale', $locale);
        $request->attributes->set(
            'changelog_fallback_locale',
            $this->localeResolver->fallback(),
        );

        return (new PublicChangelogReleaseResource($release))->resolve($request);
    }
}
