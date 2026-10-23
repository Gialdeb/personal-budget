<?php

use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Models\WebsiteIdentity;
use App\Services\TrackedItems\WebsiteLogoService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Storage::fake('public');
    $this->actor = User::factory()->create();
    $this->actingAs($this->actor);
    UserYear::create(['user_id' => $this->actor->id, 'year' => 2026, 'is_closed' => false]);
    $this->account = createTestAccount($this->actor, ['opening_balance' => 1000, 'current_balance' => 1000, 'opening_balance_date' => '2026-01-01']);
    $this->category = Category::create(['user_id' => $this->actor->id, 'name' => 'Test', 'slug' => 'reference-test', 'direction_type' => 'expense', 'group_type' => 'expense', 'is_active' => true, 'is_selectable' => true]);
    $this->context = ['account_uuid' => $this->account->uuid, 'category_uuid' => $this->category->uuid, 'type_key' => 'expense'];
    Storage::disk('public')->put('logos/test.png', 'test-logo');
    $this->logo = WebsiteIdentity::create(['domain' => 'catalog.example', 'canonical_url' => 'https://catalog.example/', 'status' => 'ready', 'logo_path' => 'logos/test.png', 'logo_mime_type' => 'image/png']);
});

test('logo catalog is authenticated searchable paginated and exposes no reference data', function () {
    TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Private reference', 'slug' => 'private-reference', 'is_active' => true, 'website_identity_id' => $this->logo->id]);
    $before = DB::table('website_identities')->get()->all();
    $this->getJson(route('tracked-items.logo-catalog', ['search' => 'catalog']))->assertOk()->assertExactJson(['items' => [['uuid' => $this->logo->uuid, 'domain' => 'catalog.example', 'logo_url' => $this->logo->logoUrl()]], 'has_more' => false]);
    $this->getJson(route('tracked-items.logo-catalog', ['search' => 'missing']))->assertOk()->assertJsonCount(0, 'items');
    expect(DB::table('website_identities')->get()->all())->toEqual($before);
    $this->app['auth']->forgetGuards();
    $this->getJson(route('tracked-items.logo-catalog'))->assertUnauthorized();
});

test('catalog logo and independent website remain provisional and save atomically without duplicates', function () {
    $input = [...$this->context, 'draft' => true, 'name' => 'New provider', 'website_url' => 'https://different.example', 'logo_identity_uuid' => $this->logo->uuid];
    $token = $this->postJson(route('transactions.tracked-items.store'), $input)->assertOk()->assertJsonPath('item.logo_url', $this->logo->logoUrl())->json('item.value');
    expect(TrackedItem::count())->toBe(0)->and(WebsiteIdentity::count())->toBe(1);
    $submission = [...$this->context, 'tracked_item_uuid' => $token, 'transaction_day' => 17, 'amount' => 20, 'description' => 'Reference test'];
    $url = route('transactions.store', ['year' => 2026, 'month' => 10]);
    $this->postJson($url, [...$submission, 'amount' => -1])->assertUnprocessable();
    expect(TrackedItem::count())->toBe(0)->and(Transaction::count())->toBe(0);
    $this->post($url, $submission)->assertSessionHasNoErrors();
    $reference = TrackedItem::sole();
    expect($reference->website_url)->toBe('https://different.example/')->and($reference->website_identity_id)->toBe($this->logo->id)->and($reference->settings['logo_source'])->toBe('catalog');
    $this->post($url, [...$submission, 'transaction_day' => 18])->assertSessionHasNoErrors();
    $this->post($url, [...$submission, 'transaction_day' => 19, 'tracked_item_uuid' => $reference->uuid])->assertSessionHasNoErrors();
    expect(TrackedItem::count())->toBe(1)->and($reference->fresh()->website_identity_id)->toBe($this->logo->id)->and(WebsiteIdentity::count())->toBe(1);
    app(WebsiteLogoService::class)->afterReferenceSaved($reference->fresh());
    expect($reference->fresh()->website_identity_id)->toBe($this->logo->id);
});

test('unavailable logo choices are rejected without writes', function () {
    $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'name' => 'Test', 'logo_source' => 'website'])->assertUnprocessable()->assertJsonValidationErrors('website_url');
    $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'name' => 'Test', 'logo_identity_uuid' => fake()->uuid()])->assertUnprocessable()->assertJsonValidationErrors('logo_identity_uuid');
    $token = $this->postJson(route('transactions.tracked-items.store'), [...$this->context, 'draft' => true, 'name' => 'Test', 'logo_identity_uuid' => $this->logo->uuid])->assertOk()->json('item.value');
    $this->logo->update(['status' => 'unavailable']);
    $this->postJson(route('transactions.store', ['year' => 2026, 'month' => 10]), [...$this->context, 'tracked_item_uuid' => $token, 'transaction_day' => 17, 'amount' => 20])->assertUnprocessable();
    expect(TrackedItem::count())->toBe(0);
});

test('transaction form fixture exposes only synthetic reference data', function () {
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Test reference', 'slug' => 'test-reference', 'is_active' => true]);
    $reference->compatibleCategories()->attach($this->category->id);
    $data = $this->getJson(route('transactions.show', ['year' => 2026, 'month' => 10]))->assertOk()->json();
    if (getenv('REFERENCE_PICKER_FIXTURE')) {
        file_put_contents(getenv('REFERENCE_PICKER_FIXTURE'), json_encode(['data' => $data, 'context' => $this->context], JSON_THROW_ON_ERROR));
    }
    expect($data)->toBeArray();
});

test('catalog pages expose only usable logos and support case insensitive domain search', function () {
    for ($index = 0; $index < 25; $index++) {
        WebsiteIdentity::create(['domain' => sprintf('site%02d.example', $index), 'canonical_url' => 'https://example.com/', 'status' => 'ready', 'logo_path' => 'logos/test.png']);
    }
    WebsiteIdentity::create(['domain' => 'unavailable.example', 'canonical_url' => 'https://unavailable.example/', 'status' => 'ready', 'logo_path' => 'logos/missing.png']);
    $first = $this->getJson(route('tracked-items.logo-catalog', ['search' => 'SITE']))->assertOk()->assertJsonCount(24, 'items')->assertJsonPath('has_more', true)->json('items');
    $last = $this->getJson(route('tracked-items.logo-catalog', ['search' => 'site', 'page' => 2]))->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('has_more', false)->json('items');
    expect(array_intersect(array_column($first, 'uuid'), array_column($last, 'uuid')))->toBe([]);
    $this->getJson(route('tracked-items.logo-catalog', ['search' => 'unavailable']))->assertOk()->assertJsonCount(0, 'items');
});

test('explicit no logo and website choices can replace a catalog choice only on main submission', function () {
    $reference = TrackedItem::create(['user_id' => $this->actor->id, 'name' => 'Existing', 'slug' => 'existing', 'is_active' => true, 'website_url' => 'https://old.example/', 'website_identity_id' => $this->logo->id, 'settings' => ['logo_source' => 'catalog']]);
    $reference->compatibleCategories()->attach($this->category->id);
    $before = $reference->fresh()->getAttributes();
    $url = route('transactions.store', ['year' => 2026, 'month' => 10]);
    $draft = [...$this->context, 'draft' => true, 'tracked_item_uuid' => $reference->uuid, 'website_url' => 'https://new.example/', 'logo_identity_uuid' => null];
    $token = $this->postJson(route('transactions.tracked-items.store'), $draft)->assertOk()->assertJsonPath('item.logo_url', null)->json('item.value');
    expect($reference->fresh()->getAttributes())->toBe($before);
    $this->postJson($url, [...$this->context, 'transaction_day' => 17, 'amount' => -1, 'tracked_item_uuid' => $token])->assertUnprocessable();
    expect($reference->fresh()->getAttributes())->toBe($before);
    $this->post($url, [...$this->context, 'transaction_day' => 17, 'amount' => 10, 'tracked_item_uuid' => $token])->assertSessionHasNoErrors();
    expect($reference->fresh()->website_identity_id)->toBeNull()->and($reference->fresh()->settings['logo_source'])->toBe('none');
    unset($draft['logo_identity_uuid']);
    $token = $this->postJson(route('transactions.tracked-items.store'), [...$draft, 'logo_source' => 'website'])->assertOk()->json('item.value');
    expect($reference->fresh()->settings['logo_source'])->toBe('none');
    $this->post($url, [...$this->context, 'transaction_day' => 18, 'amount' => 10, 'tracked_item_uuid' => $token])->assertSessionHasNoErrors();
    expect($reference->fresh()->settings)->not->toHaveKey('logo_source')->and(TrackedItem::count())->toBe(1);
});

test('recurring picker keeps logo and reference provisional through promotional preview and saves without duplicates', function () {
    $draft = [...$this->context, 'direction' => 'expense', 'draft' => true, 'name' => 'Recurring provider', 'website_url' => 'https://different.example', 'logo_identity_uuid' => $this->logo->uuid];
    $token = $this->postJson(route('recurring-entries.tracked-items.store'), $draft)->assertOk()->assertJsonPath('item.logo_url', $this->logo->logoUrl())->json('item.value');
    expect(TrackedItem::count())->toBe(0);
    $data = [...$this->context, 'tracked_item_uuid' => $token, 'title' => 'Recurring picker test', 'direction' => 'expense', 'entry_type' => 'recurring', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'start_date' => '2026-10-17', 'expected_amount' => 20, 'trial_duration' => 7, 'trial_unit' => 'days', 'promotion' => ['amount' => '5.00', 'normal_amount' => '20.00', 'duration' => 'first_payment', 'outcome' => 'normal']];
    $this->postJson(route('recurring-entries.promotion-preview'), $data)->assertOk()->assertJsonPath('promotion.starts_on', '2026-10-24');
    expect(TrackedItem::count())->toBe(0)->and(Transaction::count())->toBe(0);
    $this->postJson(route('recurring-entries.store'), [...$data, 'expected_amount' => -1])->assertUnprocessable();
    expect(TrackedItem::count())->toBe(0);
    $this->post(route('recurring-entries.store'), $data)->assertSessionHasNoErrors();
    $reference = TrackedItem::sole();
    $entry = RecurringEntry::sole();
    expect($entry->tracked_item_id)->toBe($reference->id)->and($reference->website_identity_id)->toBe($this->logo->id)->and($reference->website_url)->toBe('https://different.example/');
    $this->postJson(route('recurring-entries.promotion-preview'), [...$data, 'recurring_entry_uuid' => $entry->uuid])->assertOk();
    $this->patch(route('recurring-entries.update', $entry->uuid), $data)->assertSessionHasNoErrors();
    expect(TrackedItem::count())->toBe(1)->and(Transaction::count())->toBe(0);
    $other = User::factory()->create();
    $this->actingAs($other)->postJson(route('recurring-entries.tracked-items.store'), $draft)->assertUnprocessable();
});

test('website logo remains visible in the prepared reference and after the main save', function (string $flow, bool $cached) {
    $this->app->instance(WebsiteLogoService::class, new class extends WebsiteLogoService
    {
        protected function discoverLogo(string $websiteUrl): ?array
        {
            return ['bytes' => 'website-preview-image', 'mime_type' => 'image/png', 'source_url' => 'https://provider.example/logo.png'];
        }
    });
    $website = $cached ? 'https://catalog.example/' : 'https://provider.example/';
    $expectedPreview = $cached ? $this->logo->logoUrl() : 'data:image/png;base64,'.base64_encode('website-preview-image');
    $files = Storage::disk('public')->allFiles();
    $draft = [...$this->context, 'direction' => 'expense', 'draft' => true, 'name' => 'Website provider', 'website_url' => $website, 'logo_source' => 'website'];
    $token = $this->postJson(route($flow.'.tracked-items.store'), $draft)->assertOk()->assertJsonPath('item.logo_url', $expectedPreview)->json('item.value');
    expect(TrackedItem::count())->toBe(0)->and(WebsiteIdentity::count())->toBe(1)->and(Storage::disk('public')->allFiles())->toBe($files);
    $url = $flow === 'transactions' ? route('transactions.store', ['year' => 2026, 'month' => 10]) : route('recurring-entries.store');
    $input = [...$this->context, 'direction' => 'expense', 'tracked_item_uuid' => $token, 'transaction_day' => 17, 'amount' => 20, 'title' => 'Website logo test', 'entry_type' => 'recurring', 'recurrence_type' => 'monthly', 'recurrence_interval' => 1, 'start_date' => '2026-10-17', 'expected_amount' => 20];
    $this->postJson($url, [...$input, 'amount' => -1, 'expected_amount' => -1])->assertUnprocessable();
    expect(TrackedItem::count())->toBe(0)->and(WebsiteIdentity::count())->toBe(1)->and(Storage::disk('public')->allFiles())->toBe($files);
    $this->post($url, $input)->assertSessionHasNoErrors();
    $reference = TrackedItem::sole();
    expect($reference->logoUrl())->not->toBeNull()->and($reference->websiteIdentity->hasStoredLogo())->toBeTrue();
    if ($flow === 'transactions') {
        $sheet = $this->getJson(route('transactions.show', ['year' => 2026, 'month' => 10]))->assertOk()->json();
        expect(collect($sheet['transactions'])->firstWhere('tracked_item_uuid', $reference->uuid)['tracked_item_logo_url'])->toBe($reference->logoUrl());
    } else {
        $entry = RecurringEntry::sole();
        expect($entry->trackedItem->logoUrl())->toBe($reference->logoUrl());
    }
})->with(['transactions', 'recurring-entries'])->with([true, false]);
