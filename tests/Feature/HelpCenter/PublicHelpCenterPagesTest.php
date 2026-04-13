<?php

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleTranslation;
use App\Models\KnowledgeSection;
use App\Models\KnowledgeSectionTranslation;
use Database\Seeders\KnowledgeBaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the public help center index page with seeded database content', function () {
    $this->seed(KnowledgeBaseSeeder::class);

    $this->get(route('help-center.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help-center/Index')
            ->where('sections.0.slug', 'getting-started')
            ->where('sections.1.slug', 'accounts-and-workflows')
            ->where('sections.2.slug', 'billing-and-access')
            ->where('articleCount', 6)
            ->where('publicSeo.canonical_url', route('help-center.index'))
        );
});

it('renders a public help center section page', function () {
    $section = createPublishedKnowledgeSection('getting-started');
    $article = createPublishedKnowledgeArticle($section, 'first-public-article');

    $this->get(route('help-center.sections.show', ['knowledgeSection' => $section->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help-center/Section')
            ->where('section.slug', $section->slug)
            ->where('section.articles.0.slug', $article->slug)
            ->where('publicSeo.canonical_url', route('help-center.sections.show', ['knowledgeSection' => $section->slug]))
        );
});

it('renders a public help center article page', function () {
    $section = createPublishedKnowledgeSection('getting-started');
    $article = createPublishedKnowledgeArticle($section, 'public-help-article');

    $this->get(route('help-center.articles.show', ['knowledgeArticle' => $article->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('help-center/Article')
            ->where('article.slug', $article->slug)
            ->where('article.body', '<p>Article body in English.</p>')
            ->where('publicSeo.canonical_url', route('help-center.articles.show', ['knowledgeArticle' => $article->slug]))
        );
});

it('excludes unpublished sections and articles from public access', function () {
    $section = createPublishedKnowledgeSection('visible-section');
    $visibleArticle = createPublishedKnowledgeArticle($section, 'visible-article');

    $hiddenSection = createPublishedKnowledgeSection('hidden-section', false);
    createPublishedKnowledgeArticle($hiddenSection, 'hidden-section-article', true);

    $hiddenArticle = createPublishedKnowledgeArticle($section, 'hidden-article', false);

    $this->get(route('help-center.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections', fn ($sections): bool => collect($sections)->pluck('slug')->all() === ['visible-section'])
        );

    $this->get(route('help-center.sections.show', ['knowledgeSection' => $hiddenSection->slug]))
        ->assertNotFound();

    $this->get(route('help-center.articles.show', ['knowledgeArticle' => $hiddenArticle->slug]))
        ->assertNotFound();

    $this->get(route('help-center.articles.show', ['knowledgeArticle' => $visibleArticle->slug]))
        ->assertOk();
});

it('falls back to the first available translation when the requested locale is missing', function () {
    $section = createPublishedKnowledgeSection('fallback-section');
    $article = createPublishedKnowledgeArticle($section, 'fallback-article', true, [
        'it' => [
            'title' => 'Titolo solo italiano',
            'excerpt' => 'Estratto solo italiano',
            'body' => '<p>Corpo solo italiano.</p>',
        ],
    ]);

    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('help-center.articles.show', ['knowledgeArticle' => $article->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('article.locale', 'it')
            ->where('article.title', 'Titolo solo italiano')
            ->where('publicSeo.description', 'Estratto solo italiano')
        );
});

it('exposes public seo metadata for help center routes without hreflang alternates', function () {
    $section = createPublishedKnowledgeSection('seo-section');
    $article = createPublishedKnowledgeArticle($section, 'seo-article');

    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('help-center.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('publicSeo.title', 'Help Center and public guide')
            ->where('publicSeo.alternates', [])
        );

    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('help-center.sections.show', ['knowledgeSection' => $section->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('publicSeo.title', 'Section title EN')
            ->where('publicSeo.og_type', 'website')
        );

    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('help-center.articles.show', ['knowledgeArticle' => $article->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('publicSeo.title', 'Article title EN | Section title EN')
            ->where('publicSeo.og_type', 'article')
        );
});

it('includes help center routes in the public sitemap', function () {
    $section = createPublishedKnowledgeSection('sitemap-section');
    $article = createPublishedKnowledgeArticle($section, 'sitemap-article');

    $response = $this->get(route('sitemap'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = $response->getContent();

    expect($xml)->toContain(route('help-center.index'))
        ->toContain(route('help-center.sections.show', ['knowledgeSection' => $section->slug]))
        ->toContain(route('help-center.articles.show', ['knowledgeArticle' => $article->slug]));
});

function createPublishedKnowledgeSection(string $slug, bool $isPublished = true): KnowledgeSection
{
    $section = KnowledgeSection::factory()->create([
        'slug' => $slug,
        'is_published' => $isPublished,
        'sort_order' => 1,
    ]);

    KnowledgeSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'it',
        'title' => 'Titolo sezione IT',
        'description' => 'Descrizione sezione IT',
    ]);

    KnowledgeSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'en',
        'title' => 'Section title EN',
        'description' => 'Section description EN',
    ]);

    return $section;
}

/**
 * @param  array<string, array{title: string, excerpt: string, body: string}>|null  $translations
 */
function createPublishedKnowledgeArticle(
    KnowledgeSection $section,
    string $slug,
    bool $isPublished = true,
    ?array $translations = null,
): KnowledgeArticle {
    $article = KnowledgeArticle::factory()->create([
        'section_id' => $section->id,
        'slug' => $slug,
        'is_published' => $isPublished,
        'published_at' => $isPublished ? now() : null,
        'sort_order' => 1,
    ]);

    $translations ??= [
        'it' => [
            'title' => 'Titolo articolo IT',
            'excerpt' => 'Estratto articolo IT',
            'body' => '<p>Corpo articolo in italiano.</p>',
        ],
        'en' => [
            'title' => 'Article title EN',
            'excerpt' => 'Article excerpt EN',
            'body' => '<p>Article body in English.</p>',
        ],
    ];

    foreach ($translations as $locale => $translation) {
        KnowledgeArticleTranslation::query()->create([
            'article_id' => $article->id,
            'locale' => $locale,
            'title' => $translation['title'],
            'excerpt' => $translation['excerpt'],
            'body' => $translation['body'],
        ]);
    }

    return $article;
}
