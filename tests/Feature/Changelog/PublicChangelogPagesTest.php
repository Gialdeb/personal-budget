<?php

use App\Models\ChangelogItem;
use App\Models\ChangelogItemTranslation;
use App\Models\ChangelogRelease;
use App\Models\ChangelogReleaseTranslation;
use App\Models\ChangelogSection;
use App\Models\ChangelogSectionTranslation;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the public changelog index page', function () {
    $this->get(route('changelog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('changelog/Index')
            ->where('canRegister', Route::has('register')));
});

it('renders the public changelog detail page shell with version label', function () {
    $release = seedPublicChangelogRelease('0.10.4-beta');

    $this->get(route('changelog.show', $release->version_label))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('changelog/Show')
            ->where('versionLabel', '0.10.4-beta'));
});

function seedPublicChangelogRelease(string $versionLabel): ChangelogRelease
{
    [$versionMajor, $versionMinor, $versionPatch] = array_map(
        static fn (string $value): int => (int) $value,
        explode('.', $versionLabel),
    );

    $release = ChangelogRelease::query()->create([
        'uuid' => (string) str()->uuid(),
        'version_label' => $versionLabel,
        'version_major' => $versionMajor,
        'version_minor' => $versionMinor,
        'version_patch' => $versionPatch,
        'version_suffix' => null,
        'channel' => 'beta',
        'is_published' => true,
        'is_pinned' => false,
        'published_at' => now(),
    ]);

    ChangelogReleaseTranslation::query()->create([
        'release_id' => $release->id,
        'locale' => 'it',
        'title' => 'Release pubblica beta',
        'summary' => 'Riepilogo pubblico',
        'excerpt' => 'Estratto pubblico',
    ]);

    ChangelogReleaseTranslation::query()->create([
        'release_id' => $release->id,
        'locale' => 'en',
        'title' => 'Public beta release',
        'summary' => 'Public summary',
        'excerpt' => 'Public excerpt',
    ]);

    $section = ChangelogSection::query()->create([
        'release_id' => $release->id,
        'key' => 'highlights',
        'sort_order' => 1,
    ]);

    ChangelogSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'it',
        'label' => 'Novità',
    ]);

    ChangelogSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'en',
        'label' => 'Highlights',
    ]);

    $item = ChangelogItem::query()->create([
        'section_id' => $section->id,
        'sort_order' => 1,
        'item_type' => 'bullet',
    ]);

    ChangelogItemTranslation::query()->create([
        'item_id' => $item->id,
        'locale' => 'it',
        'title' => 'Miglioramento',
        'body' => 'Dettaglio pubblico',
    ]);

    ChangelogItemTranslation::query()->create([
        'item_id' => $item->id,
        'locale' => 'en',
        'title' => 'Improvement',
        'body' => 'Public detail',
    ]);

    return $release;
}
