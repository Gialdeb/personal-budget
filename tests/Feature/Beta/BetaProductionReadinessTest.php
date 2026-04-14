<?php

use App\Models\ChangelogRelease;
use App\Models\ContextualHelpEntry;
use App\Models\User;
use Database\Seeders\BetaChangelogSeeder;
use Database\Seeders\ContextualHelpSeeder;
use Database\Seeders\KnowledgeBaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function betaAppUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locale' => 'it',
    ]);
    $user->assignRole('user');

    return $user;
}

it('hides import-related UI state and returns 404 for import endpoints when the feature flag is disabled', function () {
    config()->set('features.imports.enabled', false);

    $user = betaAppUser();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('features.imports_enabled', false));

    $this->actingAs($user)
        ->get(route('imports.index'))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('imports.template'))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('imports.store'), [])
        ->assertNotFound();
});

it('seeds bilingual beta contextual help and changelog content safely across repeated runs', function () {
    $this->seed(KnowledgeBaseSeeder::class);
    $this->seed(ContextualHelpSeeder::class);
    $this->seed(BetaChangelogSeeder::class);

    $this->seed(KnowledgeBaseSeeder::class);
    $this->seed(ContextualHelpSeeder::class);
    $this->seed(BetaChangelogSeeder::class);

    $expectedPageKeys = [
        'dashboard',
        'transactions',
        'recurring-entries',
        'budget-planning',
        'categories',
        'profile',
        'tracked-items',
        'banks',
        'accounts',
        'years',
        'shared-categories',
        'exports',
        'support',
        'exchange-rates',
    ];

    expect(ContextualHelpEntry::query()->whereIn('page_key', $expectedPageKeys)->count())
        ->toBe(count($expectedPageKeys));

    $budgetPlanningHelp = ContextualHelpEntry::query()
        ->where('page_key', 'budget-planning')
        ->with('translations')
        ->firstOrFail();

    expect($budgetPlanningHelp->translations)->toHaveCount(2)
        ->and($budgetPlanningHelp->translations->pluck('body')->join(' '))
        ->toContain('preventivazione')
        ->toContain('Budget planning');

    $profileHelp = ContextualHelpEntry::query()
        ->where('page_key', 'profile')
        ->with('translations')
        ->firstOrFail();

    expect($profileHelp->translations)->toHaveCount(2)
        ->and($profileHelp->translations->pluck('locale')->sort()->values()->all())->toBe(['en', 'it'])
        ->and($profileHelp->translations->pluck('body')->join(' '))
        ->not->toContain('<img');

    $recurringHelp = ContextualHelpEntry::query()
        ->where('page_key', 'recurring-entries')
        ->with('translations')
        ->firstOrFail();

    expect($recurringHelp->translations)->toHaveCount(2)
        ->and($recurringHelp->translations->pluck('body')->join(' '))
        ->not->toContain('<img');

    $release = ChangelogRelease::query()
        ->where('version_label', '1.0.0-beta')
        ->with(['translations', 'sections.translations', 'sections.items.translations'])
        ->firstOrFail();

    expect($release->translations)->toHaveCount(2)
        ->and($release->translations->pluck('locale')->sort()->values()->all())->toBe(['en', 'it'])
        ->and($release->sections)->toHaveCount(2)
        ->and($release->sections->flatMap->items)->toHaveCount(4)
        ->and($release->sections->flatMap->items->pluck('screenshot_key')->filter()->all())->toBe([])
        ->and($release->translations->firstWhere('locale', 'it')?->summary ?? '')
        ->toContain('beta')
        ->and($release->sections->flatMap->items->flatMap->translations->pluck('body')->join(' '))
        ->toContain('shared accounts')
        ->not->toContain('temporarily unavailable')
        ->not->toContain('disabilitato');
});

it('keeps seeded contextual help and beta changelog readable from their pages', function () {
    config()->set('features.imports.enabled', false);
    $this->seed(KnowledgeBaseSeeder::class);
    $this->seed(ContextualHelpSeeder::class);
    $this->seed(BetaChangelogSeeder::class);

    $user = betaAppUser();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('contextualHelp.page_key', 'profile')
            ->where('contextualHelp.locale', 'it'));

    $this->actingAs($user)
        ->get(route('budget-planning'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/Planning')
            ->where('contextualHelp.page_key', 'budget-planning')
            ->where('contextualHelp.locale', 'it'));

    $this->get(route('changelog.show', ['versionLabel' => '1.0.0-beta']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('changelog/Show')
            ->where('versionLabel', '1.0.0-beta'));
});
