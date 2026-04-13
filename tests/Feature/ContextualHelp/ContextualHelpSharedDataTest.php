<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\AccountType;
use App\Models\ContextualHelpEntry;
use App\Models\ContextualHelpEntryTranslation;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleTranslation;
use App\Models\KnowledgeSection;
use App\Models\KnowledgeSectionTranslation;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Categories\CategoryFoundationService;
use App\Services\ContextualHelp\ContextualHelpEntryUpsertService;
use App\Services\Knowledge\KnowledgeArticleUpsertService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function contextualHelpAppUser(string $locale = 'it'): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'locale' => $locale,
    ]);
    $user->assignRole('user');

    return $user;
}

function contextualHelpAccountType(): AccountType
{
    return AccountType::query()->firstOrCreate([
        'code' => 'contextual-help-shared-test',
    ], [
        'name' => 'Contextual help shared test',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);
}

function contextualHelpSharedAccount(User $owner, string $name): Account
{
    return Account::query()->create([
        'user_id' => $owner->id,
        'account_type_id' => contextualHelpAccountType()->id,
        'name' => $name,
        'currency' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_manual' => true,
        'is_active' => true,
    ]);
}

function contextualHelpShareAccount(Account $account, User $user): AccountMembership
{
    return AccountMembership::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'role' => AccountMembershipRoleEnum::EDITOR,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'granted_by_user_id' => $account->user_id,
        'joined_at' => now(),
    ]);
}

function createLinkedKnowledgeArticle(): KnowledgeArticle
{
    $section = KnowledgeSection::factory()->create([
        'slug' => fake()->unique()->slug(3),
        'is_published' => true,
    ]);

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

    $article = KnowledgeArticle::factory()->create([
        'section_id' => $section->id,
        'slug' => fake()->unique()->slug(4),
        'is_published' => true,
        'published_at' => now(),
    ]);

    KnowledgeArticleTranslation::query()->create([
        'article_id' => $article->id,
        'locale' => 'it',
        'title' => 'Guida supporto IT',
        'excerpt' => 'Estratto IT',
        'body' => '<p>Body IT</p>',
    ]);

    KnowledgeArticleTranslation::query()->create([
        'article_id' => $article->id,
        'locale' => 'en',
        'title' => 'Support guide EN',
        'excerpt' => 'Excerpt EN',
        'body' => '<p>Body EN</p>',
    ]);

    return $article;
}

function createContextualHelpEntry(string $pageKey, bool $isPublished = true): ContextualHelpEntry
{
    $article = createLinkedKnowledgeArticle();

    $entry = ContextualHelpEntry::factory()->create([
        'page_key' => $pageKey,
        'knowledge_article_id' => $article->id,
        'sort_order' => 1,
        'is_published' => $isPublished,
    ]);

    ContextualHelpEntryTranslation::query()->create([
        'contextual_help_entry_id' => $entry->id,
        'locale' => 'it',
        'title' => 'Guida contestuale IT',
        'body' => '<p>Body IT</p>',
    ]);

    ContextualHelpEntryTranslation::query()->create([
        'contextual_help_entry_id' => $entry->id,
        'locale' => 'en',
        'title' => 'Contextual guide EN',
        'body' => '<p>Body EN</p>',
    ]);

    return $entry;
}

it('shares contextual help on supported authenticated pages when a published entry exists', function () {
    $entry = createContextualHelpEntry('support');
    $user = contextualHelpAppUser('en');

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Support')
            ->where('contextualHelp.page_key', 'support')
            ->where('contextualHelp.locale', 'en')
            ->where('contextualHelp.title', 'Contextual guide EN')
            ->where('contextualHelp.knowledge_article.slug', $entry->knowledgeArticle->slug));
});

it('does not share contextual help when the entry for the current page is unpublished', function () {
    createContextualHelpEntry('support', false);
    $user = contextualHelpAppUser();

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Support')
            ->where('contextualHelp', null));
});

it('shares contextual help on dashboard when a published entry exists', function () {
    createContextualHelpEntry('dashboard');
    $user = contextualHelpAppUser();

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => (int) now(config('app.timezone'))->year],
    );

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('contextualHelp.page_key', 'dashboard'));
});

it('shares contextual help on categories and exports pages when published entries exist', function () {
    createContextualHelpEntry('categories');
    createContextualHelpEntry('exports');
    $user = contextualHelpAppUser();

    app(CategoryFoundationService::class)->ensureForUser($user);

    $this->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Categories')
            ->where('contextualHelp.page_key', 'categories'));

    $this->actingAs($user)
        ->get(route('exports.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Export')
            ->where('contextualHelp.page_key', 'exports'));
});

it('shares contextual help on banks, tracked items, accounts, and years pages when published entries exist', function () {
    createContextualHelpEntry('banks');
    createContextualHelpEntry('tracked-items');
    createContextualHelpEntry('accounts');
    createContextualHelpEntry('years');
    $user = contextualHelpAppUser();

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => (int) now(config('app.timezone'))->year],
    );

    $this->actingAs($user)
        ->get(route('banks.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Banks')
            ->where('contextualHelp.page_key', 'banks'));

    $this->actingAs($user)
        ->get(route('tracked-items.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TrackedItems')
            ->where('contextualHelp.page_key', 'tracked-items'));

    $this->actingAs($user)
        ->get(route('accounts.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Accounts')
            ->where('contextualHelp.page_key', 'accounts'));

    $this->actingAs($user)
        ->get(route('years.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Years')
            ->where('contextualHelp.page_key', 'years'));
});

it('shares contextual help on transactions show and shared categories pages when published entries exist', function () {
    createContextualHelpEntry('transactions');
    createContextualHelpEntry('shared-categories');
    $user = contextualHelpAppUser();

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['active_year' => (int) now(config('app.timezone'))->year],
    );

    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $sharedAccount = contextualHelpSharedAccount($owner, 'Conto condiviso');
    contextualHelpShareAccount($sharedAccount, $user);

    $this->actingAs($user)
        ->followingRedirects()
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Show')
            ->where('contextualHelp.page_key', 'transactions'));

    $this->actingAs($user)
        ->get(route('shared-categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/SharedCategories')
            ->where('contextualHelp.page_key', 'shared-categories'));
});

it('caches resolved contextual help payload by page key and locale', function () {
    createContextualHelpEntry('support');
    $user = contextualHelpAppUser('it');

    Cache::forget('contextual_help:support:it');

    expect(Cache::get('contextual_help:support:it'))->toBeNull();

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertOk();

    expect(Cache::get('contextual_help:support:it'))
        ->toBeArray()
        ->and(Cache::get('contextual_help:support:it')['page_key'] ?? null)
        ->toBe('support');
});

it('invalidates contextual help cache when the entry is updated', function () {
    $entry = createContextualHelpEntry('support');
    $user = contextualHelpAppUser('it');

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertOk();

    expect(Cache::get('contextual_help:support:it'))->toBeArray();

    app(ContextualHelpEntryUpsertService::class)->upsert($entry, [
        'page_key' => 'support',
        'knowledge_article_id' => $entry->knowledge_article_id,
        'sort_order' => 5,
        'is_published' => false,
        'translations' => [
            [
                'locale' => 'it',
                'title' => 'Guida aggiornata IT',
                'body' => '<p>Body aggiornato IT</p>',
            ],
            [
                'locale' => 'en',
                'title' => 'Updated guide EN',
                'body' => '<p>Updated body EN</p>',
            ],
        ],
    ]);

    expect(Cache::get('contextual_help:support:it'))->toBeNull();
});

it('invalidates contextual help cache when a linked knowledge article is updated', function () {
    $entry = createContextualHelpEntry('support');
    $user = contextualHelpAppUser('it');
    /** @var KnowledgeArticle $article */
    $article = $entry->knowledgeArticle()->with('translations')->firstOrFail();

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertOk();

    expect(Cache::get('contextual_help:support:it'))->toBeArray();

    app(KnowledgeArticleUpsertService::class)->upsert($article, [
        'section_id' => $article->section_id,
        'slug' => $article->slug,
        'sort_order' => $article->sort_order,
        'is_published' => $article->is_published,
        'published_at' => $article->published_at,
        'translations' => [
            [
                'locale' => 'it',
                'title' => 'Guida knowledge aggiornata IT',
                'excerpt' => 'Estratto IT',
                'body' => '<p>Body article IT aggiornato</p>',
            ],
            [
                'locale' => 'en',
                'title' => 'Updated knowledge EN',
                'excerpt' => 'Excerpt EN',
                'body' => '<p>Updated article EN body</p>',
            ],
        ],
    ]);

    expect(Cache::get('contextual_help:support:it'))->toBeNull();
});
