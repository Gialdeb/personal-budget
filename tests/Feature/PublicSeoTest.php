<?php

use App\Models\ChangelogItem;
use App\Models\ChangelogItemTranslation;
use App\Models\ChangelogRelease;
use App\Models\ChangelogReleaseTranslation;
use App\Models\ChangelogSection;
use App\Models\ChangelogSectionTranslation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('home page exposes localized public seo metadata', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('publicSeo.title', 'Bring order to budgets, accounts and transactions')
            ->where('publicSeo.description', 'Soamco Budget helps you track balances, monthly planning and recurring entries with a clear and stable interface.')
            ->where('publicSeo.canonical_url', route('home'))
            ->where('publicSeo.robots', 'index,follow')
        );
});

test('private authenticated pages do not expose public seo metadata', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('publicSeo')
        );
});

test('public changelog show route exposes initial release props and route specific seo', function () {
    $release = seedPublishedChangelogRelease();

    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('changelog.show', ['versionLabel' => $release->version_label]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('changelog/Show')
            ->where('initialRelease.version_label', $release->version_label)
            ->where('initialRelease.title', 'Public release title')
            ->where('publicSeo.title', 'Public release title (v1.2.3)')
            ->where('publicSeo.canonical_url', route('changelog.show', ['versionLabel' => $release->version_label]))
        );
});

test('sitemap includes public urls only and excludes private areas', function () {
    $release = seedPublishedChangelogRelease('v2.0.0');

    $response = $this->get(route('sitemap'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = $response->getContent();

    expect($xml)->toContain(route('home'))
        ->toContain(route('features'))
        ->toContain(route('pricing'))
        ->toContain(route('about-me'))
        ->toContain(route('customers'))
        ->toContain(route('download-app'))
        ->toContain(route('changelog.index'))
        ->toContain(route('privacy'))
        ->toContain(route('terms-of-service'))
        ->toContain(route('changelog.show', ['versionLabel' => $release->version_label]))
        ->not->toContain('/dashboard')
        ->not->toContain('/settings')
        ->not->toContain('/admin')
        ->not->toContain('/profile');
});

test('robots file disallows private sections and references sitemap', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Disallow: /admin')
        ->toContain('Disallow: /dashboard')
        ->toContain('Disallow: /settings')
        ->toContain('Disallow: /login')
        ->toContain('Sitemap: /sitemap.xml');
});

function seedPublishedChangelogRelease(string $versionLabel = 'v1.2.3'): ChangelogRelease
{
    [$versionMajor, $versionMinor, $versionPatch] = array_map(
        static fn (string $value): int => (int) $value,
        explode('.', ltrim($versionLabel, 'v')),
    );

    $release = ChangelogRelease::query()->create([
        'uuid' => (string) str()->uuid(),
        'version_label' => $versionLabel,
        'version_major' => $versionMajor,
        'version_minor' => $versionMinor,
        'version_patch' => $versionPatch,
        'version_suffix' => null,
        'channel' => 'stable',
        'is_published' => true,
        'is_pinned' => false,
        'published_at' => now(),
    ]);

    ChangelogReleaseTranslation::query()->create([
        'release_id' => $release->id,
        'locale' => 'en',
        'title' => 'Public release title',
        'summary' => 'Public release summary',
        'excerpt' => 'Public release excerpt',
    ]);

    ChangelogReleaseTranslation::query()->create([
        'release_id' => $release->id,
        'locale' => 'it',
        'title' => 'Titolo release pubblica',
        'summary' => 'Riepilogo release pubblica',
        'excerpt' => 'Estratto release pubblica',
    ]);

    $section = ChangelogSection::query()->create([
        'release_id' => $release->id,
        'key' => 'highlights',
        'sort_order' => 1,
    ]);

    ChangelogSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'en',
        'label' => 'Highlights',
    ]);

    ChangelogSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'it',
        'label' => 'Novità',
    ]);

    $item = ChangelogItem::query()->create([
        'section_id' => $section->id,
        'sort_order' => 1,
        'item_type' => 'bullet',
    ]);

    ChangelogItemTranslation::query()->create([
        'item_id' => $item->id,
        'locale' => 'en',
        'title' => 'Improvement',
        'body' => 'Release item body',
    ]);

    ChangelogItemTranslation::query()->create([
        'item_id' => $item->id,
        'locale' => 'it',
        'title' => 'Miglioramento',
        'body' => 'Corpo voce release',
    ]);

    return $release;
}
