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

test('it creates a complete changelog release from a json payload file', function () {
    $payloadFile = tempnam(sys_get_temp_dir(), 'changelog-payload-');

    file_put_contents($payloadFile, json_encode([
        'version_label' => '3.4.5-beta',
        'channel' => 'beta',
        'is_published' => true,
        'is_pinned' => true,
        'sort_order' => 40,
        'translations' => [
            [
                'locale' => 'it',
                'title' => 'Release completa',
                'summary' => '<p>Riepilogo completo italiano</p>',
                'excerpt' => 'Estratto completo italiano',
            ],
            [
                'locale' => 'en',
                'title' => 'Complete release',
                'summary' => '<p>Complete English summary</p>',
                'excerpt' => 'Complete English excerpt',
            ],
        ],
        'sections' => [
            [
                'key' => 'new',
                'sort_order' => 1,
                'translations' => [
                    ['locale' => 'it', 'label' => 'Novita'],
                    ['locale' => 'en', 'label' => 'New'],
                ],
                'items' => [
                    [
                        'sort_order' => 1,
                        'screenshot_key' => 'dashboard',
                        'link_url' => 'https://example.com/dashboard',
                        'link_label' => 'Dashboard',
                        'item_type' => 'feature',
                        'platform' => 'web',
                        'translations' => [
                            ['locale' => 'it', 'title' => 'Dashboard nuova', 'body' => '<p>Corpo dashboard</p>'],
                            ['locale' => 'en', 'title' => 'New dashboard', 'body' => '<p>Dashboard body</p>'],
                        ],
                    ],
                    [
                        'sort_order' => 2,
                        'item_type' => 'improvement',
                        'platform' => 'mobile',
                        'translations' => [
                            ['locale' => 'it', 'title' => 'Mobile rapido', 'body' => '<p>Corpo mobile</p>'],
                            ['locale' => 'en', 'title' => 'Faster mobile', 'body' => '<p>Mobile body</p>'],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'fixed',
                'sort_order' => 2,
                'translations' => [
                    ['locale' => 'it', 'label' => 'Correzioni'],
                    ['locale' => 'en', 'label' => 'Fixes'],
                ],
                'items' => [
                    [
                        'sort_order' => 1,
                        'item_type' => 'bugfix',
                        'platform' => 'backend',
                        'translations' => [
                            ['locale' => 'it', 'title' => 'Import corretti', 'body' => '<p>Corpo import</p>'],
                            ['locale' => 'en', 'title' => 'Import fixes', 'body' => '<p>Import body</p>'],
                        ],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        $this->artisan('changelog:upsert-release', [
            'version' => '3.4.5-beta',
            '--payload-file' => $payloadFile,
        ])
            ->expectsOutputToContain('Changelog release created successfully.')
            ->assertSuccessful();
    } finally {
        if (is_string($payloadFile) && file_exists($payloadFile)) {
            unlink($payloadFile);
        }
    }

    $release = ChangelogRelease::query()
        ->with(['translations', 'sections.translations', 'sections.items.translations'])
        ->where('version_label', '3.4.5-beta')
        ->firstOrFail();

    expect($release->channel)->toBe('beta')
        ->and($release->is_published)->toBeTrue()
        ->and($release->is_pinned)->toBeTrue()
        ->and($release->sort_order)->toBe(40)
        ->and($release->translations)->toHaveCount(2)
        ->and($release->sections)->toHaveCount(2)
        ->and($release->sections->firstWhere('key', 'new')?->items)->toHaveCount(2)
        ->and($release->sections->firstWhere('key', 'fixed')?->items)->toHaveCount(1)
        ->and($release->sections->firstWhere('key', 'new')?->items->first()?->screenshot_key)->toBe('dashboard')
        ->and($release->sections->firstWhere('key', 'new')?->items->first()?->translations)->toHaveCount(2);
});
