<?php

use App\Models\ChangelogRelease;
use App\Models\ChangelogReleaseTranslation;

function changelogCommandArguments(array $overrides = []): array
{
    return array_replace([
        'version' => '1.2.3',
        '--title-it' => 'Esperienza mobile piu fluida e miglioramenti generali',
        '--title-en' => 'Improved mobile experience and overall refinements',
        '--body-it' => 'Abbiamo introdotto una nuova serie di miglioramenti pensati per rendere Soamco Budget piu chiaro, piu fluido e piu affidabile nell\'uso quotidiano. L\'esperienza mobile e stata rifinita in diversi punti chiave, con interazioni piu semplici, flussi meglio guidati e una navigazione piu coerente. Abbiamo inoltre migliorato la stabilita generale dell\'app e la qualita di alcune sezioni importanti, per offrire un prodotto sempre piu ordinato, solido e piacevole da usare.',
        '--body-en' => 'We\'ve introduced a new set of improvements designed to make Soamco Budget clearer, smoother, and more reliable in everyday use. The mobile experience has been refined across several key areas, with simpler interactions, better guided flows, and more consistent navigation. We\'ve also improved the app\'s overall stability and polished a number of important sections to deliver a product that feels more organized, solid, and pleasant to use.',
        '--excerpt-it' => 'Esperienza mobile piu fluida, interazioni piu chiare e miglioramenti generali di stabilita e usabilita.',
        '--excerpt-en' => 'A smoother mobile experience, clearer interactions, and overall stability and usability refinements.',
        '--published' => '1',
        '--pinned' => '0',
        '--channel' => 'stable',
    ], $overrides);
}

test('it creates a new changelog release via command', function () {
    $this->artisan('changelog:upsert-release', changelogCommandArguments())
        ->expectsOutputToContain('Changelog release created successfully.')
        ->assertSuccessful();

    $release = ChangelogRelease::query()
        ->with(['translations', 'sections.translations', 'sections.items.translations'])
        ->where('version_label', '1.2.3')
        ->firstOrFail();

    expect($release->channel)->toBe('stable')
        ->and($release->is_published)->toBeTrue()
        ->and($release->translations)->toHaveCount(2)
        ->and($release->translations->pluck('locale')->sort()->values()->all())->toBe(['en', 'it'])
        ->and($release->translations->firstWhere('locale', 'it')?->title)
        ->toBe('Esperienza mobile piu fluida e miglioramenti generali')
        ->and($release->translations->firstWhere('locale', 'en')?->title)
        ->toBe('Improved mobile experience and overall refinements')
        ->and($release->sections)->toHaveCount(1)
        ->and($release->sections->first()?->key)->toBe('highlights')
        ->and($release->sections->first()?->items)->toHaveCount(1);
});

test('it updates an existing changelog release for the same version without duplicates', function () {
    $release = ChangelogRelease::factory()->create([
        'version_label' => '1.2.3',
        'version_major' => 1,
        'version_minor' => 2,
        'version_patch' => 3,
        'version_suffix' => null,
        'channel' => 'stable',
        'is_published' => false,
        'published_at' => null,
    ]);

    ChangelogReleaseTranslation::factory()->create([
        'release_id' => $release->id,
        'locale' => 'it',
        'title' => 'Vecchio titolo',
    ]);

    ChangelogReleaseTranslation::factory()->create([
        'release_id' => $release->id,
        'locale' => 'en',
        'title' => 'Old title',
    ]);

    $this->artisan('changelog:upsert-release', changelogCommandArguments([
        '--title-it' => 'Titolo aggiornato',
        '--title-en' => 'Updated title',
        '--published' => '0',
    ]))
        ->expectsOutputToContain('Changelog release updated successfully.')
        ->assertSuccessful();

    expect(ChangelogRelease::query()->where('version_label', '1.2.3')->count())->toBe(1);

    $release->refresh();
    $release->load(['translations', 'sections.items.translations']);

    expect($release->is_published)->toBeFalse()
        ->and($release->translations->firstWhere('locale', 'it')?->title)->toBe('Titolo aggiornato')
        ->and($release->translations->firstWhere('locale', 'en')?->title)->toBe('Updated title')
        ->and($release->sections)->toHaveCount(1)
        ->and($release->sections->first()?->items->first()?->translations->firstWhere('locale', 'en')?->body)
        ->toContain('We&#039;ve introduced a new set of improvements');
});

test('it persists italian and english changelog copy and exposes published releases publicly', function () {
    $this->artisan('changelog:upsert-release', changelogCommandArguments([
        'version' => '2.0.0',
        '--published' => '1',
    ]))->assertSuccessful();

    $release = ChangelogRelease::query()
        ->where('version_label', '2.0.0')
        ->firstOrFail();

    $this->get(route('changelog.show', ['versionLabel' => $release->version_label]))
        ->assertOk();

    $itTranslation = $release->translations()->where('locale', 'it')->first();
    $enTranslation = $release->translations()->where('locale', 'en')->first();

    expect($itTranslation?->summary)->toContain('Soamco Budget')
        ->and($enTranslation?->summary)->toContain('Soamco Budget')
        ->and($release->published_at)->not->toBeNull();
});
