<?php

use App\Models\ChangelogRelease;
use App\Models\ChangelogReleaseTranslation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function changelogPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'version_label' => '0.10.4-beta',
        'channel' => 'beta',
        'is_published' => false,
        'is_pinned' => false,
        'published_at' => null,
        'sort_order' => 10,
        'translations' => [
            [
                'locale' => 'it',
                'title' => 'Release beta italiana',
                'summary' => '<p><strong>Riepilogo italiano</strong> con <em>evidenza</em>.</p>',
                'excerpt' => 'Estratto it',
            ],
            [
                'locale' => 'en',
                'title' => 'English beta release',
                'summary' => '<p><strong>English summary</strong> with <em>emphasis</em>.</p>',
                'excerpt' => 'Excerpt en',
            ],
        ],
        'sections' => [
            [
                'key' => 'new',
                'sort_order' => 1,
                'translations' => [
                    ['locale' => 'it', 'label' => 'Nuovo'],
                    ['locale' => 'en', 'label' => 'New'],
                ],
                'items' => [
                    [
                        'sort_order' => 1,
                        'screenshot_key' => 'dashboard-overview',
                        'link_url' => 'https://example.com/changelog',
                        'link_label' => 'Dettagli',
                        'item_type' => 'feature',
                        'platform' => 'web',
                        'translations' => [
                            [
                                'locale' => 'it',
                                'title' => 'Nuova dashboard',
                                'body' => '<h2>Corpo italiano</h2><p><strong>Dettaglio</strong> con <a href="https://example.com/it">link</a>.</p>',
                            ],
                            [
                                'locale' => 'en',
                                'title' => 'New dashboard',
                                'body' => '<h3>English body</h3><p><strong>Detail</strong> with <a href="https://example.com/en">link</a>.</p>',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'fixed',
                'sort_order' => 2,
                'translations' => [
                    ['locale' => 'it', 'label' => 'Corretto'],
                    ['locale' => 'en', 'label' => 'Fixed'],
                ],
                'items' => [
                    [
                        'sort_order' => 5,
                        'screenshot_key' => null,
                        'link_url' => null,
                        'link_label' => null,
                        'item_type' => 'bugfix',
                        'platform' => 'backend',
                        'translations' => [
                            [
                                'locale' => 'it',
                                'title' => 'Ordine corretto',
                                'body' => '<p>Secondo elemento</p>',
                            ],
                            [
                                'locale' => 'en',
                                'title' => 'Correct ordering',
                                'body' => '<p>Second item</p>',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ], $overrides);
}

it('renders the admin changelog index and create pages', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.changelog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Changelog/Index')
            ->where('auth.user.is_admin', true)
            ->has('versionSuggestions')
            ->has('supportedLocales', 2));

    $this->actingAs($user)
        ->get(route('admin.changelog.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Changelog/Edit')
            ->where('release', null));
});

it('creates a changelog release with multilingual translations, sections, and items', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->post(route('admin.changelog.store'), changelogPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $release = ChangelogRelease::query()
        ->with(['translations', 'sections.translations', 'sections.items.translations'])
        ->firstOrFail();

    $newSection = $release->sections->firstWhere('key', 'new');

    expect($release->version_label)->toBe('0.10.4-beta')
        ->and($release->channel)->toBe('beta')
        ->and($release->is_published)->toBeFalse()
        ->and($release->translations)->toHaveCount(2)
        ->and($release->translations->firstWhere('locale', 'it')?->summary)->toContain('<strong>Riepilogo italiano</strong>')
        ->and($release->sections)->toHaveCount(2)
        ->and($newSection?->translations)->toHaveCount(2)
        ->and($newSection?->items)->toHaveCount(1)
        ->and($newSection?->items->first()?->translations)->toHaveCount(2)
        ->and($newSection?->items->first()?->translations->firstWhere('locale', 'en')?->body)->toContain('<a href="https://example.com/en">link</a>');
});

it('updates a changelog release and can publish it', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $release = ChangelogRelease::factory()->create([
        'version_label' => '0.10.4-beta',
        'version_major' => 0,
        'version_minor' => 10,
        'version_patch' => 4,
        'version_suffix' => 'beta',
        'channel' => 'beta',
        'is_published' => false,
        'published_at' => null,
    ]);

    ChangelogReleaseTranslation::factory()->create([
        'release_id' => $release->id,
        'locale' => 'it',
    ]);

    $this->actingAs($user)
        ->put(route('admin.changelog.update', $release->uuid), changelogPayload([
            'version_label' => '0.10.5',
            'channel' => 'stable',
            'is_published' => true,
            'published_at' => now()->toISOString(),
        ]))
        ->assertRedirect(route('admin.changelog.edit', $release->uuid))
        ->assertSessionHas('success');

    $release->refresh();

    expect($release->version_label)->toBe('0.10.5')
        ->and($release->channel)->toBe('stable')
        ->and($release->version_suffix)->toBeNull()
        ->and($release->is_published)->toBeTrue()
        ->and($release->published_at)->not->toBeNull();
});

it('orders sections and items by numeric sort order in the edit payload', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $release = ChangelogRelease::factory()->create([
        'version_label' => '0.10.4-beta',
        'version_major' => 0,
        'version_minor' => 10,
        'version_patch' => 4,
        'version_suffix' => 'beta',
        'channel' => 'beta',
    ]);

    $this->actingAs($user)
        ->put(route('admin.changelog.update', $release->uuid), changelogPayload([
            'sections' => [
                changelogPayload()['sections'][1],
                changelogPayload()['sections'][0],
            ],
        ]))
        ->assertRedirect(route('admin.changelog.edit', $release->uuid));

    $this->actingAs($user)
        ->get(route('admin.changelog.edit', $release->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Changelog/Edit')
            ->where('release.sections.0.key', 'new')
            ->where('release.sections.0.items.0.sort_order', 1)
            ->where('release.sections.1.key', 'fixed')
            ->where('release.sections.1.items.0.sort_order', 5));
});
