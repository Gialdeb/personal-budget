<?php

use App\Models\ChangelogRelease;
use App\Services\Changelog\ChangelogVersionSuggestionService;

it('suggests next semver variants from the latest beta release', function () {
    $release = ChangelogRelease::factory()->create([
        'version_label' => '0.10.4-beta',
        'version_major' => 0,
        'version_minor' => 10,
        'version_patch' => 4,
        'version_suffix' => 'beta',
        'channel' => 'beta',
    ]);

    $suggestions = app(ChangelogVersionSuggestionService::class)->suggestions($release);

    expect($suggestions['patch']['beta'])->toBe('0.10.5-beta')
        ->and($suggestions['minor']['beta'])->toBe('0.11.0-beta')
        ->and($suggestions['major']['stable'])->toBe('1.0.0');
});

it('orders releases using structured semver columns instead of lexical version strings', function () {
    ChangelogRelease::factory()->published()->create([
        'version_label' => '0.9.9-beta',
        'version_major' => 0,
        'version_minor' => 9,
        'version_patch' => 9,
        'version_suffix' => 'beta',
        'channel' => 'beta',
    ]);

    ChangelogRelease::factory()->published()->create([
        'version_label' => '0.10.0-beta',
        'version_major' => 0,
        'version_minor' => 10,
        'version_patch' => 0,
        'version_suffix' => 'beta',
        'channel' => 'beta',
    ]);

    expect(
        ChangelogRelease::query()->ordered()->pluck('version_label')->all()
    )->toBe(['0.10.0-beta', '0.9.9-beta']);
});
