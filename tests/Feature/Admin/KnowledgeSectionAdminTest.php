<?php

use App\Models\KnowledgeArticle;
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

function knowledgeSectionPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'slug' => 'new-section',
        'sort_order' => 10,
        'is_published' => false,
        'translations' => [
            [
                'locale' => 'it',
                'title' => 'Titolo sezione IT',
                'description' => 'Descrizione IT',
            ],
            [
                'locale' => 'en',
                'title' => 'Section title EN',
                'description' => 'Description EN',
            ],
        ],
    ], $overrides);
}

function createAdminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('renders the admin knowledge sections index and create pages', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->get(route('admin.knowledge-sections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/KnowledgeBase/Sections/Index')
            ->where('auth.user.is_admin', true));

    $this->actingAs($admin)
        ->get(route('admin.knowledge-sections.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/KnowledgeBase/Sections/Edit')
            ->where('section', null)
            ->has('supportedLocales', 2));
});

it('creates a knowledge section with bilingual translations', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->post(route('admin.knowledge-sections.store'), knowledgeSectionPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $section = KnowledgeSection::query()
        ->with('translations')
        ->firstOrFail();

    expect($section->slug)->toBe('new-section')
        ->and($section->is_published)->toBeFalse()
        ->and($section->translations)->toHaveCount(2)
        ->and($section->translations->pluck('title')->all())
        ->toContain('Titolo sezione IT', 'Section title EN');
});

it('updates a knowledge section and can publish it', function () {
    $admin = createAdminUser();

    $section = KnowledgeSection::factory()->create([
        'slug' => 'draft-section',
        'sort_order' => 2,
        'is_published' => false,
    ]);

    KnowledgeSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'it',
        'title' => 'Bozza IT',
        'description' => 'Descrizione bozza IT',
    ]);

    KnowledgeSectionTranslation::query()->create([
        'section_id' => $section->id,
        'locale' => 'en',
        'title' => 'Draft EN',
        'description' => 'Draft description EN',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.knowledge-sections.update', $section->uuid), knowledgeSectionPayload([
            'slug' => 'published-section',
            'sort_order' => 1,
            'is_published' => true,
        ]))
        ->assertRedirect(route('admin.knowledge-sections.edit', $section->uuid))
        ->assertSessionHas('success');

    $section->refresh();

    expect($section->slug)->toBe('published-section')
        ->and($section->sort_order)->toBe(1)
        ->and($section->is_published)->toBeTrue();
});

it('validates the section slug as globally unique', function () {
    $admin = createAdminUser();

    KnowledgeSection::factory()->create([
        'slug' => 'existing-section',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.knowledge-sections.create'))
        ->post(route('admin.knowledge-sections.store'), knowledgeSectionPayload([
            'slug' => 'existing-section',
        ]))
        ->assertRedirect(route('admin.knowledge-sections.create'))
        ->assertSessionHasErrors(['slug']);
});

it('deletes a section and cascades to related articles', function () {
    $admin = createAdminUser();

    $section = KnowledgeSection::factory()->create();
    $article = KnowledgeArticle::factory()->create([
        'section_id' => $section->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.knowledge-sections.destroy', $section->uuid))
        ->assertRedirect(route('admin.knowledge-sections.index'))
        ->assertSessionHas('success');

    expect(KnowledgeSection::query()->whereKey($section->id)->exists())->toBeFalse()
        ->and(KnowledgeArticle::query()->whereKey($article->id)->exists())->toBeFalse();
});
