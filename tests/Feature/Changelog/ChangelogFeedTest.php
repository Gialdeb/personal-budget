<?php

use App\Models\ChangelogItem;
use App\Models\ChangelogItemTranslation;
use App\Models\ChangelogRelease;
use App\Models\ChangelogReleaseTranslation;
use App\Models\ChangelogSection;
use App\Models\ChangelogSectionTranslation;

it('returns only published releases in semver order for the public changelog feed', function () {
    $older = ChangelogRelease::factory()->published()->create([
        'version_label' => '0.9.9-beta',
        'version_major' => 0,
        'version_minor' => 9,
        'version_patch' => 9,
        'version_suffix' => 'beta',
        'channel' => 'beta',
    ]);
    $newer = ChangelogRelease::factory()->published()->create([
        'version_label' => '0.10.0-beta',
        'version_major' => 0,
        'version_minor' => 10,
        'version_patch' => 0,
        'version_suffix' => 'beta',
        'channel' => 'beta',
    ]);
    ChangelogRelease::factory()->create([
        'version_label' => '0.10.1-beta',
        'version_major' => 0,
        'version_minor' => 10,
        'version_patch' => 1,
        'version_suffix' => 'beta',
        'channel' => 'beta',
        'is_published' => false,
    ]);

    ChangelogReleaseTranslation::factory()->create([
        'release_id' => $older->id,
        'locale' => 'it',
        'title' => 'Vecchia',
    ]);
    ChangelogReleaseTranslation::factory()->create([
        'release_id' => $newer->id,
        'locale' => 'it',
        'title' => 'Nuova',
    ]);

    $response = $this->getJson(route('changelog.releases.index', ['locale' => 'it']))
        ->assertOk()
        ->json('data');

    expect($response)->toHaveCount(2)
        ->and($response[0]['version_label'])->toBe('0.10.0-beta')
        ->and($response[1]['version_label'])->toBe('0.9.9-beta');
});

it('returns localized release detail with ordered sections and items', function () {
    $release = ChangelogRelease::factory()->published()->create([
        'version_label' => '0.10.4-beta',
        'version_major' => 0,
        'version_minor' => 10,
        'version_patch' => 4,
        'version_suffix' => 'beta',
        'channel' => 'beta',
    ]);

    ChangelogReleaseTranslation::factory()->create([
        'release_id' => $release->id,
        'locale' => 'it',
        'title' => 'Release italiana',
        'summary' => '<p>Riepilogo</p>',
    ]);
    ChangelogReleaseTranslation::factory()->create([
        'release_id' => $release->id,
        'locale' => 'en',
        'title' => 'English release',
        'summary' => '<p>Summary</p>',
    ]);

    $firstSection = ChangelogSection::factory()->create([
        'release_id' => $release->id,
        'key' => 'fixed',
        'sort_order' => 2,
    ]);
    $secondSection = ChangelogSection::factory()->create([
        'release_id' => $release->id,
        'key' => 'new',
        'sort_order' => 1,
    ]);

    ChangelogSectionTranslation::factory()->create([
        'section_id' => $firstSection->id,
        'locale' => 'it',
        'label' => 'Corretto',
    ]);
    ChangelogSectionTranslation::factory()->create([
        'section_id' => $secondSection->id,
        'locale' => 'it',
        'label' => 'Nuovo',
    ]);

    $laterItem = ChangelogItem::factory()->create([
        'section_id' => $secondSection->id,
        'sort_order' => 3,
    ]);
    $earlierItem = ChangelogItem::factory()->create([
        'section_id' => $secondSection->id,
        'sort_order' => 1,
    ]);

    ChangelogItemTranslation::factory()->create([
        'item_id' => $laterItem->id,
        'locale' => 'it',
        'title' => 'Più tardi',
        'body' => '<p>Later</p>',
    ]);
    ChangelogItemTranslation::factory()->create([
        'item_id' => $earlierItem->id,
        'locale' => 'it',
        'title' => 'Prima',
        'body' => '<p>Earlier</p>',
    ]);

    $this->getJson(route('changelog.releases.show', [
        'versionLabel' => $release->version_label,
        'locale' => 'it',
    ]))
        ->assertOk()
        ->assertJsonPath('data.title', 'Release italiana')
        ->assertJsonPath('data.sections.0.key', 'new')
        ->assertJsonPath('data.sections.0.items.0.title', 'Prima')
        ->assertJsonPath('data.sections.1.key', 'fixed');
});
