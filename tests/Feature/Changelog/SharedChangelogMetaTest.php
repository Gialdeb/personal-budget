<?php

use App\Models\ChangelogRelease;
use Inertia\Testing\AssertableInertia as Assert;

it('shares the latest published changelog release in app meta', function () {
    ChangelogRelease::factory()->create([
        'version_label' => '0.9.9-beta',
        'version_major' => 0,
        'version_minor' => 9,
        'version_patch' => 9,
        'channel' => 'beta',
        'is_published' => false,
    ]);

    ChangelogRelease::factory()->published()->create([
        'version_label' => '0.10.4-beta',
        'version_major' => 0,
        'version_minor' => 10,
        'version_patch' => 4,
        'channel' => 'beta',
    ]);

    ChangelogRelease::factory()->published()->create([
        'version_label' => '0.10.5-beta',
        'version_major' => 0,
        'version_minor' => 10,
        'version_patch' => 5,
        'channel' => 'beta',
    ]);

    $this->get(route('changelog.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $page
                ->where('app.changelog.latest_release_label', '0.10.5-beta')
                ->where('app.changelog.latest_release_channel', 'beta')
                ->where('app.changelog.has_published_release', true)
                ->where('app.changelog.latest_release_url', route('changelog.show', ['versionLabel' => '0.10.5-beta']))
                ->where('app.changelog_url', route('changelog.show', ['versionLabel' => '0.10.5-beta']));
        });
});

it('falls back to changelog index when no release is published', function () {
    ChangelogRelease::factory()->create([
        'version_label' => '0.10.7-beta',
        'version_major' => 0,
        'version_minor' => 10,
        'version_patch' => 7,
        'channel' => 'beta',
        'is_published' => false,
    ]);

    $this->get(route('changelog.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $page
                ->where('app.changelog.latest_release_label', null)
                ->where('app.changelog.latest_release_channel', null)
                ->where('app.changelog.has_published_release', false)
                ->where('app.changelog.latest_release_url', route('changelog.index'))
                ->where('app.changelog_url', route('changelog.index'));
        });
});
