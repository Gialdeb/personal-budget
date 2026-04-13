<?php

namespace App\Support\Seo;

use App\Http\Resources\Changelog\PublicChangelogReleaseResource;
use App\Models\ChangelogRelease;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeSection;
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
            'help-center.index',
            'terms-of-service',
            'privacy',
        ];
    }

    public function isIndexablePublicRoute(string $routeName): bool
    {
        return in_array($routeName, [
            ...$this->staticIndexableRouteNames(),
            'changelog.show',
            'help-center.sections.show',
            'help-center.articles.show',
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

        $knowledgeSectionEntries = KnowledgeSection::query()
            ->published()
            ->ordered()
            ->get(['slug', 'updated_at'])
            ->map(fn (KnowledgeSection $section): array => [
                'url' => route('help-center.sections.show', ['knowledgeSection' => $section->slug]),
                'lastmod' => $section->updated_at?->toAtomString(),
            ]);

        $knowledgeArticleEntries = KnowledgeArticle::query()
            ->published()
            ->whereHas('section', fn ($query) => $query->published())
            ->ordered()
            ->get(['slug', 'updated_at', 'published_at'])
            ->map(fn (KnowledgeArticle $article): array => [
                'url' => route('help-center.articles.show', ['knowledgeArticle' => $article->slug]),
                'lastmod' => $article->updated_at?->toAtomString()
                    ?? $article->published_at?->toAtomString(),
            ]);

        return $entries
            ->concat($changelogEntries)
            ->concat($knowledgeSectionEntries)
            ->concat($knowledgeArticleEntries)
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

        if ($routeName === 'help-center.sections.show') {
            return route('help-center.sections.show', [
                'knowledgeSection' => $this->routeSlug($request->route('knowledgeSection')),
            ]);
        }

        if ($routeName === 'help-center.articles.show') {
            return route('help-center.articles.show', [
                'knowledgeArticle' => $this->routeSlug($request->route('knowledgeArticle')),
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

        if ($routeName === 'help-center.sections.show') {
            return $this->helpCenterSectionMeta($request, $locale);
        }

        if ($routeName === 'help-center.articles.show') {
            return $this->helpCenterArticleMeta($request, $locale);
        }

        return [
            'title' => $this->pageTranslation($routeName, 'title'),
            'description' => $this->pageTranslation($routeName, 'description'),
            'og_type' => in_array($routeName, ['home', 'help-center.index'], true) ? 'website' : 'article',
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
                'title' => $this->pageTranslation('changelog.show', 'fallback_title', __('seo.defaults.title'), [
                    'version' => $versionLabel,
                ]),
                'description' => $this->pageTranslation('changelog.show', 'fallback_description'),
                'og_type' => 'article',
            ];
        }

        return [
            'title' => $release['title'] !== null && $release['title'] !== ''
                ? sprintf('%s (%s)', $release['title'], $release['version_label'])
                : $this->pageTranslation('changelog.show', 'fallback_title', __('seo.defaults.title'), [
                    'version' => $versionLabel,
                ]),
            'description' => (string) ($release['excerpt']
                ?? $release['summary']
                ?? $this->pageTranslation('changelog.show', 'fallback_description')),
            'og_type' => 'article',
        ];
    }

    /**
     * @return array{title: string, description: string, og_type: string}
     */
    protected function helpCenterSectionMeta(Request $request, string $locale): array
    {
        $section = $this->resolveKnowledgeSection($request, $locale);

        if ($section === null) {
            return [
                'title' => $this->pageTranslation('help-center.sections.show', 'fallback_title'),
                'description' => $this->pageTranslation('help-center.sections.show', 'fallback_description'),
                'og_type' => 'website',
            ];
        }

        return [
            'title' => (string) ($section['title'] ?: $this->pageTranslation('help-center.sections.show', 'fallback_title')),
            'description' => (string) ($section['description']
                ?: $this->pageTranslation('help-center.sections.show', 'fallback_description')),
            'og_type' => 'website',
        ];
    }

    /**
     * @return array{title: string, description: string, og_type: string}
     */
    protected function helpCenterArticleMeta(Request $request, string $locale): array
    {
        $article = $this->resolveKnowledgeArticle($request, $locale);

        if ($article === null) {
            return [
                'title' => $this->pageTranslation('help-center.articles.show', 'fallback_title'),
                'description' => $this->pageTranslation('help-center.articles.show', 'fallback_description'),
                'og_type' => 'article',
            ];
        }

        $sectionTitle = data_get($article, 'section.title');
        $title = $article['title'] ?: $this->pageTranslation('help-center.articles.show', 'fallback_title');

        return [
            'title' => is_string($sectionTitle) && $sectionTitle !== ''
                ? sprintf('%s | %s', $title, $sectionTitle)
                : $title,
            'description' => (string) ($article['excerpt']
                ?: $this->pageTranslation('help-center.articles.show', 'fallback_description')),
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
                '@type' => in_array($routeName, ['changelog.index', 'help-center.index', 'help-center.sections.show'], true)
                    ? 'CollectionPage'
                    : 'WebPage',
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

        if ($routeName === 'help-center.articles.show') {
            $article = $this->resolveKnowledgeArticle($request, $locale);

            if ($article !== null) {
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    'headline' => $pageMeta['title'],
                    'description' => $pageMeta['description'],
                    'url' => $canonicalUrl,
                    'inLanguage' => $article['locale'] ?? $locale,
                    'datePublished' => $article['published_at'],
                    'dateModified' => $article['published_at'],
                    'isPartOf' => [
                        '@type' => 'WebSite',
                        'name' => config('app.name'),
                        'url' => $this->appUrl(),
                    ],
                ];
            }
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

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveKnowledgeSection(Request $request, string $locale): ?array
    {
        $slug = $this->routeSlug($request->route('knowledgeSection'));

        if ($slug === null) {
            return null;
        }

        $section = KnowledgeSection::query()
            ->published()
            ->where('slug', $slug)
            ->with(['translations'])
            ->first();

        if (! $section instanceof KnowledgeSection) {
            return null;
        }

        $translation = $section->resolveTranslation($locale, $this->localeResolver->fallback());

        return [
            'slug' => $section->slug,
            'title' => $translation?->title,
            'description' => $translation?->description,
            'locale' => $translation?->locale ?? $locale,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveKnowledgeArticle(Request $request, string $locale): ?array
    {
        $slug = $this->routeSlug($request->route('knowledgeArticle'));

        if ($slug === null) {
            return null;
        }

        $article = KnowledgeArticle::query()
            ->published()
            ->where('slug', $slug)
            ->with(['translations', 'section.translations'])
            ->first();

        if (! $article instanceof KnowledgeArticle || ! $article->section?->is_published) {
            return null;
        }

        $translation = $article->resolveTranslation($locale, $this->localeResolver->fallback());
        $sectionTranslation = $article->section->resolveTranslation($locale, $this->localeResolver->fallback());

        return [
            'slug' => $article->slug,
            'title' => $translation?->title,
            'excerpt' => $translation?->excerpt,
            'published_at' => $article->published_at?->toAtomString(),
            'locale' => $translation?->locale ?? $locale,
            'section' => [
                'title' => $sectionTranslation?->title,
            ],
        ];
    }

    protected function routeSlug(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if ($value instanceof KnowledgeSection || $value instanceof KnowledgeArticle) {
            return $value->slug;
        }

        return null;
    }

    /**
     * @param  array<string, string>  $replace
     */
    protected function pageTranslation(
        string $routeName,
        string $field,
        string $fallback = '',
        array $replace = [],
    ): string {
        $pages = trans('seo.pages');
        $entry = is_array($pages) ? ($pages[$routeName] ?? null) : null;
        $value = is_array($entry) ? ($entry[$field] ?? null) : null;

        if (! is_string($value) || $value === '') {
            return $fallback;
        }

        foreach ($replace as $key => $replacement) {
            $value = str_replace(':'.$key, $replacement, $value);
        }

        return $value;
    }
}
