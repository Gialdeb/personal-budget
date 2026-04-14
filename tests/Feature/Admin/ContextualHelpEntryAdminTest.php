<?php

use App\Models\ContextualHelpEntry;
use App\Models\ContextualHelpEntryTranslation;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleTranslation;
use App\Models\KnowledgeSection;
use App\Models\KnowledgeSectionTranslation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createContextualHelpAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function createContextualHelpUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('user');

    return $user;
}

function createPublishedKnowledgeArticleForContextualHelp(): KnowledgeArticle
{
    $section = KnowledgeSection::factory()->create([
        'slug' => 'contextual-help-section',
        'is_published' => true,
    ]);

    KnowledgeSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'it',
        'title' => 'Sezione contestuale',
        'description' => 'Descrizione IT',
    ]);

    KnowledgeSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'en',
        'title' => 'Contextual section',
        'description' => 'Description EN',
    ]);

    $article = KnowledgeArticle::factory()->create([
        'section_id' => $section->id,
        'slug' => 'full-contextual-guide',
        'is_published' => true,
        'published_at' => now(),
    ]);

    KnowledgeArticleTranslation::query()->create([
        'article_id' => $article->id,
        'locale' => 'it',
        'title' => 'Guida completa IT',
        'excerpt' => 'Estratto IT',
        'body' => '<p>Body IT</p>',
    ]);

    KnowledgeArticleTranslation::query()->create([
        'article_id' => $article->id,
        'locale' => 'en',
        'title' => 'Full guide EN',
        'excerpt' => 'Excerpt EN',
        'body' => '<p>Body EN</p>',
    ]);

    return $article;
}

function contextualHelpPayload(int $knowledgeArticleId, array $overrides = []): array
{
    return array_replace_recursive([
        'page_key' => 'support',
        'knowledge_article_id' => $knowledgeArticleId,
        'sort_order' => 3,
        'is_published' => true,
        'translations' => [
            [
                'locale' => 'it',
                'title' => 'Guida contestuale IT',
                'body' => '<p><strong>Body IT</strong> con dettagli rapidi.</p>',
            ],
            [
                'locale' => 'en',
                'title' => 'Contextual guide EN',
                'body' => '<p><em>Body EN</em> with quick details.</p>',
            ],
        ],
    ], $overrides);
}

it('renders the admin contextual help index and create pages', function () {
    $admin = createContextualHelpAdmin();

    $this->actingAs($admin)
        ->get(route('admin.contextual-help.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/ContextualHelp/Index')
            ->has('pageKeyOptions', 14)
            ->where('auth.user.is_admin', true));

    $this->actingAs($admin)
        ->get(route('admin.contextual-help.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/ContextualHelp/Edit')
            ->where('entry', null)
            ->has('supportedLocales', 2)
            ->has('pageKeyOptions', 14));
});

it('creates a contextual help entry with bilingual translations', function () {
    $admin = createContextualHelpAdmin();
    $knowledgeArticle = createPublishedKnowledgeArticleForContextualHelp();

    $this->actingAs($admin)
        ->post(route('admin.contextual-help.store'), contextualHelpPayload($knowledgeArticle->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $entry = ContextualHelpEntry::query()
        ->with(['translations', 'knowledgeArticle'])
        ->firstOrFail();

    expect($entry->page_key)->toBe('support')
        ->and($entry->knowledge_article_id)->toBe($knowledgeArticle->id)
        ->and($entry->is_published)->toBeTrue()
        ->and($entry->translations)->toHaveCount(2)
        ->and($entry->translations->firstWhere('locale', 'it')?->body)->toContain('<strong>Body IT</strong>')
        ->and($entry->translations->firstWhere('locale', 'en')?->title)->toBe('Contextual guide EN');
});

it('updates a contextual help entry and can unpublish it', function () {
    $admin = createContextualHelpAdmin();
    $knowledgeArticle = createPublishedKnowledgeArticleForContextualHelp();

    $entry = ContextualHelpEntry::factory()->published()->create([
        'page_key' => 'dashboard',
        'knowledge_article_id' => $knowledgeArticle->id,
        'sort_order' => 1,
    ]);

    ContextualHelpEntryTranslation::query()->create([
        'contextual_help_entry_id' => $entry->id,
        'locale' => 'it',
        'title' => 'Titolo IT',
        'body' => '<p>Body IT</p>',
    ]);

    ContextualHelpEntryTranslation::query()->create([
        'contextual_help_entry_id' => $entry->id,
        'locale' => 'en',
        'title' => 'Title EN',
        'body' => '<p>Body EN</p>',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.contextual-help.update', $entry->uuid), contextualHelpPayload($knowledgeArticle->id, [
            'page_key' => 'dashboard',
            'sort_order' => 9,
            'is_published' => false,
        ]))
        ->assertRedirect(route('admin.contextual-help.edit', $entry->uuid))
        ->assertSessionHas('success');

    $entry->refresh();

    expect($entry->sort_order)->toBe(9)
        ->and($entry->is_published)->toBeFalse();
});

it('forbids non admin users from accessing contextual help admin pages', function () {
    $user = createContextualHelpUser();

    $this->actingAs($user)
        ->get(route('admin.contextual-help.index'))
        ->assertForbidden();
});
