<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Models\WebsiteIdentity;
use App\Services\TrackedItems\PendingReferenceLogoService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Storage::fake('public', ['root' => storage_path('framework/testing/disks/mobile-reference-public')]);
    Storage::fake('local', ['root' => storage_path('framework/testing/disks/mobile-reference-local')]);
    $this->actor = User::factory()->create();
    $this->actingAs($this->actor);
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2026, 'is_closed' => false]);
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000, 'opening_balance_date' => '2026-01-01']);
    $this->category = Category::create(['user_id' => $this->actor->id, 'name' => 'Tecnologia', 'slug' => 'reference-technology', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->context = ['account_uuid' => $this->account->uuid, 'category_uuid' => $this->category->uuid, 'type_key' => 'expense', 'direction' => 'expense'];
    Storage::disk('public')->put('brand-logos/apple.png', UploadedFile::fake()->image('apple.png', 32, 32)->getContent());
    $this->brand = Brand::factory()->published()->create(['name' => 'Apple', 'aliases' => ['Mela'], 'domain' => 'apple.com', 'category' => 'Tecnologia', 'logo_path' => 'brand-logos/apple.png', 'logo_mime_type' => 'image/png']);
});

test('published catalog ranks existing category metadata without excluding other categories and preserves aliases and homonyms', function () {
    $other = Brand::factory()->published()->create(['name' => 'Apple', 'domain' => 'apple.other', 'category' => 'Alimentari']);
    Brand::factory()->create(['name' => 'Apple hidden', 'domain' => 'hidden.example']);
    $this->getJson(route('brands.index', ['search' => 'Apple', 'category' => 'Tecnologia']))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.uuid', $this->brand->uuid)->assertJsonPath('data.1.uuid', $other->uuid);
    $this->getJson(route('brands.index', ['search' => 'MELA']))->assertOk()->assertJsonPath('data.0.uuid', $this->brand->uuid);
    expect(TrackedItem::count())->toBe(0)->and(WebsiteIdentity::count())->toBe(0);
});

test('Apple brand and uploaded logos remain provisional and rollback files on failed main saves', function (string $flow, string $source) {
    $logo = ['logo_brand_uuid' => $this->brand->uuid];
    $temporaryPath = null;
    if ($source === 'upload') {
        $upload = $this->post(route('tracked-items.logo-upload'), ['logo' => UploadedFile::fake()->image('apple-custom.png', 64, 64)], ['Accept' => 'application/json'])->assertOk()->json();
        $logo = ['logo_upload_token' => $upload['token']];
        $temporaryPath = app(PendingReferenceLogoService::class)->temporaryPath($this->actor, $upload['token']);
    }
    $draft = [...$this->context, ...$logo, 'draft' => true, 'name' => 'Apple', 'website_url' => 'https://apple.com'];
    $token = $this->postJson(route($flow.'.tracked-items.store'), $draft)->assertOk()->json('item.value');
    expect(TrackedItem::count())->toBe(0)->and(WebsiteIdentity::count())->toBe(0)->and(Storage::disk('public')->files('reference-logos'))->toBe([]);
    $url = $flow === 'transactions' ? route('transactions.store', ['year' => 2026, 'month' => 10]) : route('recurring-entries.store');
    $input = [...$this->context, 'tracked_item_uuid' => $token, 'transaction_day' => 17, 'amount' => 20, 'title' => 'Apple test', 'entry_type' => 'recurring', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'start_date' => '2026-10-17', 'expected_amount' => 20];
    $this->postJson($url, [...$input, 'amount' => -1, 'expected_amount' => -1])->assertUnprocessable();
    expect(TrackedItem::count())->toBe(0)->and(Transaction::count())->toBe(0)->and(RecurringEntry::count())->toBe(0)->and(Storage::disk('public')->files('reference-logos'))->toBe([]);
    if ($temporaryPath !== null) {
        Storage::disk('local')->assertExists($temporaryPath);
    }
    $this->post($url, $input)->assertSessionHasNoErrors();
    $reference = TrackedItem::sole();
    expect($reference->settings['logo_source'])->toBe($source)->and($reference->website_identity_id)->toBeNull()->and($reference->logoUrl())->not->toBeNull();
    Storage::disk('public')->assertExists($reference->settings['reference_logo_path']);
    if ($temporaryPath !== null) {
        Storage::disk('local')->assertMissing($temporaryPath);
    }
    $this->post($url, [...$input, 'transaction_day' => 18])->assertSessionHasNoErrors();
    expect(TrackedItem::count())->toBe(1)->and(Brand::count())->toBe(1)->and(Storage::disk('public')->files('reference-logos'))->toHaveCount(1);
})->with(['transactions', 'recurring-entries'])->with(['brand', 'upload']);

test('temporary upload validates images supports cancellation enforces ownership and prunes expired files', function () {
    $this->post(route('tracked-items.logo-upload'), ['logo' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml')], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post(route('tracked-items.logo-upload'), ['logo' => UploadedFile::fake()->image('large.png')->size(2049)], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post(route('tracked-items.logo-upload'), ['logo' => UploadedFile::fake()->image('wide.png', 4097, 2)], ['Accept' => 'application/json'])->assertUnprocessable();
    $token = $this->post(route('tracked-items.logo-upload'), ['logo' => UploadedFile::fake()->image('apple.png')], ['Accept' => 'application/json'])->assertOk()->json('token');
    $path = app(PendingReferenceLogoService::class)->temporaryPath($this->actor, $token);
    $other = User::factory()->create();
    $this->actingAs($other)->deleteJson(route('tracked-items.logo-discard'), ['token' => $token])->assertUnprocessable();
    Storage::disk('local')->assertExists($path);
    $this->actingAs($this->actor)->deleteJson(route('tracked-items.logo-discard'), ['token' => $token])->assertOk();
    Storage::disk('local')->assertMissing($path);
    $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'name' => 'Apple', 'logo_upload_token' => $token])->assertUnprocessable();
    $token = $this->post(route('tracked-items.logo-upload'), ['logo' => UploadedFile::fake()->image('abandoned.png')], ['Accept' => 'application/json'])->assertOk()->json('token');
    $path = app(PendingReferenceLogoService::class)->temporaryPath($this->actor, $token);
    $this->travel(25)->hours();
    app(PendingReferenceLogoService::class)->prune();
    Storage::disk('local')->assertMissing($path);
    expect(TrackedItem::count())->toBe(0)->and(WebsiteIdentity::count())->toBe(0);
    $this->app['auth']->forgetGuards();
    $this->postJson(route('tracked-items.logo-upload'))->assertUnauthorized();
});

test('unpublished brands invalid combinations and ambiguous names cannot prepare references', function () {
    $this->brand->update(['is_published' => false]);
    $input = [...$this->context, 'name' => 'Apple', 'draft' => true, 'logo_brand_uuid' => $this->brand->uuid];
    $this->postJson(route('transactions.tracked-items.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('logo_brand_uuid');
    $this->brand->update(['is_published' => true]);
    $this->postJson(route('transactions.tracked-items.store'), [...$input, 'logo_identity_uuid' => null])->assertUnprocessable();
    $this->postJson(route('transactions.tracked-items.store'), [...$input, 'logo_source' => 'website', 'website_url' => 'https://apple.com'])->assertUnprocessable();
    foreach (['apple-one', 'apple-two'] as $slug) {
        TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Apple', 'slug' => $slug, 'is_active' => true]);
    }
    $this->postJson(route('transactions.tracked-items.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('name');
    expect(TrackedItem::count())->toBe(2);
});

test('brand suggestions never overwrite an existing personal explicit logo choice', function () {
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Apple', 'slug' => 'apple-existing', 'is_active' => true, 'settings' => ['logo_source' => 'none']]);
    $token = $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'name' => 'Apple', 'logo_brand_uuid' => $this->brand->uuid])->assertOk()->assertJsonPath('item.logo_url', null)->json('item.value');
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->context, 'tracked_item_uuid' => $token, 'transaction_day' => 17, 'amount' => 20])->assertSessionHasNoErrors();
    expect(TrackedItem::count())->toBe(1)->and($reference->fresh()->settings['logo_source'])->toBe('none')->and(Storage::disk('public')->files('reference-logos'))->toBe([]);
});

test('mobile Apple fixtures expose only the authorized personal catalog in both forms', function () {
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Apple personale', 'slug' => 'apple-personal', 'website_url' => 'https://apple.com/', 'is_active' => true]);
    $reference->compatibleCategories()->attach($this->category->id);
    $other = User::factory()->create();
    TrackedItem::create(['user_id' => $other->id, 'name' => 'Apple private other', 'slug' => 'apple-private', 'is_active' => true]);
    $transactions = $this->getJson(route('transactions.show', ['year' => 2026, 'month' => 10]))->assertOk()->json();
    $recurring = $this->getJson(route('recurring-entries.index'))->assertOk()->json();
    expect(json_encode([$transactions, $recurring]))->toContain('Apple personale')->not->toContain('Apple private other');
    if (getenv('MOBILE_REFERENCE_FIXTURE')) {
        file_put_contents(getenv('MOBILE_REFERENCE_FIXTURE'), json_encode(['transactions' => $transactions, 'recurring' => $recurring, 'context' => $this->context, 'brand' => ['uuid' => $this->brand->uuid, 'name' => 'Apple', 'domain' => 'apple.com', 'category' => 'Tecnologia', 'aliases' => ['Mela'], 'logo_url' => 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get('brand-logos/apple.png'))]], JSON_THROW_ON_ERROR));
    }
});

test('implicit matching preserves the personal logo even when the new draft requests no logo', function () {
    $identity = WebsiteIdentity::create(['domain' => 'personal.example', 'canonical_url' => 'https://personal.example/', 'status' => 'ready', 'logo_path' => 'brand-logos/apple.png']);
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Apple', 'slug' => 'apple-personal-logo', 'is_active' => true, 'website_identity_id' => $identity->id, 'website_url' => 'https://personal.example/', 'settings' => ['logo_source' => 'catalog']]);
    $token = $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'name' => 'Apple', 'website_url' => 'https://apple.com', 'logo_identity_uuid' => null])->assertOk()->assertJsonPath('item.logo_url', $identity->logoUrl())->json('item.value');
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->context, 'tracked_item_uuid' => $token, 'transaction_day' => 17, 'amount' => 20])->assertSessionHasNoErrors();
    expect($reference->fresh()->website_identity_id)->toBe($identity->id)->and($reference->fresh()->website_url)->toBe('https://personal.example/')->and(TrackedItem::count())->toBe(1);
});

test('a brand unpublished between preparation and main submission cannot persist a logo', function () {
    $token = $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'name' => 'Apple', 'logo_brand_uuid' => $this->brand->uuid])->assertOk()->json('item.value');
    $this->brand->update(['is_published' => false]);
    $this->postJson(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->context, 'tracked_item_uuid' => $token, 'transaction_day' => 17, 'amount' => 20])->assertUnprocessable();
    expect(TrackedItem::count())->toBe(0)->and(Storage::disk('public')->files('reference-logos'))->toBe([]);
});

test('published catalog logos retain support for imported GIF identities', function () {
    Storage::disk('public')->put('brand-logos/imported.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
    $this->brand->update(['logo_path' => 'brand-logos/imported.gif', 'logo_mime_type' => 'image/gif']);
    $token = $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'name' => 'Apple', 'logo_brand_uuid' => $this->brand->uuid])->assertOk()->json('item.value');
    $this->post(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->context, 'tracked_item_uuid' => $token, 'transaction_day' => 17, 'amount' => 20])->assertSessionHasNoErrors();
    expect(TrackedItem::sole()->settings['reference_logo_path'])->toEndWith('.gif');
});
