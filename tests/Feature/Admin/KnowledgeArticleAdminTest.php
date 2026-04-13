<?php

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

function knowledgeArticlePayload(int $sectionId, array $overrides = []): array
{
    return array_replace_recursive([
        'section_id' => $sectionId,
        'slug' => 'configure-dashboard',
        'sort_order' => 20,
        'is_published' => false,
        'published_at' => null,
        'translations' => [
            [
                'locale' => 'it',
                'title' => 'Configura la dashboard',
                'excerpt' => 'Estratto articolo IT',
                'body' => '<h2>Panoramica</h2><p><strong>Body IT.</strong> <em>Importante.</em></p><ul><li>Primo step</li></ul><p><a href="https://example.com/it">Approfondisci</a></p>',
            ],
            [
                'locale' => 'en',
                'title' => 'Configure the dashboard',
                'excerpt' => 'Article excerpt EN',
                'body' => '<h3>Overview</h3><p><strong>Body EN.</strong> <em>Important.</em></p><ol><li>First step</li></ol><p><a href="https://example.com/en">Learn more</a></p>',
            ],
        ],
    ], $overrides);
}

function createAdminKnowledgeUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function createKnowledgeSectionWithTranslations(array $attributes = []): KnowledgeSection
{
    $section = KnowledgeSection::factory()->create($attributes);

    KnowledgeSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'it',
        'title' => 'Sezione IT',
        'description' => 'Descrizione IT',
    ]);

    KnowledgeSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'en',
        'title' => 'Section EN',
        'description' => 'Description EN',
    ]);

    return $section;
}

it('renders the admin knowledge articles index and create pages', function () {
    $admin = createAdminKnowledgeUser();
    $section = createKnowledgeSectionWithTranslations();

    $this->actingAs($admin)
        ->get(route('admin.knowledge-articles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/KnowledgeBase/Articles/Index')
            ->where('auth.user.is_admin', true));

    $this->actingAs($admin)
        ->get(route('admin.knowledge-articles.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/KnowledgeBase/Articles/Edit')
            ->where('article', null)
            ->where('sections.0.id', $section->id)
            ->has('supportedLocales', 2));
});

it('creates a knowledge article with bilingual translations', function () {
    $admin = createAdminKnowledgeUser();
    $section = createKnowledgeSectionWithTranslations();

    $this->actingAs($admin)
        ->post(route('admin.knowledge-articles.store'), knowledgeArticlePayload($section->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $article = KnowledgeArticle::query()
        ->with(['translations', 'section'])
        ->firstOrFail();

    expect($article->slug)->toBe('configure-dashboard')
        ->and($article->section_id)->toBe($section->id)
        ->and($article->is_published)->toBeFalse()
        ->and($article->translations)->toHaveCount(2)
        ->and($article->translations->firstWhere('locale', 'it')?->body)->toContain('<strong>Body IT.</strong>')
        ->and($article->translations->firstWhere('locale', 'en')?->body)->toContain('<ol><li>First step</li></ol>');
});

it('updates a knowledge article and can publish it', function () {
    $admin = createAdminKnowledgeUser();
    $section = createKnowledgeSectionWithTranslations();

    $article = KnowledgeArticle::factory()->create([
        'section_id' => $section->id,
        'slug' => 'draft-article',
        'sort_order' => 1,
        'is_published' => false,
        'published_at' => null,
    ]);

    KnowledgeArticleTranslation::query()->create([
        'article_id' => $article->id,
        'locale' => 'it',
        'title' => 'Bozza IT',
        'excerpt' => 'Estratto IT',
        'body' => '<p>Body IT.</p>',
    ]);

    KnowledgeArticleTranslation::query()->create([
        'article_id' => $article->id,
        'locale' => 'en',
        'title' => 'Draft EN',
        'excerpt' => 'Excerpt EN',
        'body' => '<p>Body EN.</p>',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.knowledge-articles.update', $article->uuid), knowledgeArticlePayload($section->id, [
            'slug' => 'published-article',
            'sort_order' => 2,
            'is_published' => true,
            'published_at' => now()->toISOString(),
        ]))
        ->assertRedirect(route('admin.knowledge-articles.edit', $article->uuid))
        ->assertSessionHas('success');

    $article->refresh();

    expect($article->slug)->toBe('published-article')
        ->and($article->sort_order)->toBe(2)
        ->and($article->is_published)->toBeTrue()
        ->and($article->published_at)->not->toBeNull();
});

it('keeps unpublished articles out of the public help center', function () {
    $admin = createAdminKnowledgeUser();
    $section = createKnowledgeSectionWithTranslations([
        'slug' => 'public-section',
        'is_published' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.knowledge-articles.store'), knowledgeArticlePayload($section->id, [
            'slug' => 'hidden-article',
            'is_published' => false,
        ]))
        ->assertRedirect();

    $article = KnowledgeArticle::query()->firstOrFail();

    $this->get(route('help-center.articles.show', ['knowledgeArticle' => $article->slug]))
        ->assertNotFound();
});

it('validates the article slug as globally unique', function () {
    $admin = createAdminKnowledgeUser();
    $section = createKnowledgeSectionWithTranslations();

    KnowledgeArticle::factory()->create([
        'section_id' => $section->id,
        'slug' => 'existing-article',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.knowledge-articles.create'))
        ->post(route('admin.knowledge-articles.store'), knowledgeArticlePayload($section->id, [
            'slug' => 'existing-article',
        ]))
        ->assertRedirect(route('admin.knowledge-articles.create'))
        ->assertSessionHasErrors(['slug']);
});

it('deletes a knowledge article', function () {
    $admin = createAdminKnowledgeUser();
    $section = createKnowledgeSectionWithTranslations();

    $article = KnowledgeArticle::factory()->create([
        'section_id' => $section->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.knowledge-articles.destroy', $article->uuid))
        ->assertRedirect(route('admin.knowledge-articles.index'))
        ->assertSessionHas('success');

    expect(KnowledgeArticle::query()->whereKey($article->id)->exists())->toBeFalse();
});
