<?php

use App\Models\Brand;
use App\Models\BrandCategory;
use App\Models\TrackedItem;
use App\Models\User;
use App\Models\WebsiteIdentity;
use App\Services\TrackedItems\WebsiteLogoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    BrandCategory::create(['name' => 'Tecnologia']);
    $this->payload = ['name' => 'Apple', 'aliases' => ['Apple Inc', 'Mela'], 'domain' => 'https://www.apple.com/store', 'category' => 'Tecnologia', 'is_published' => false];
});

test('admin can create edit publish unpublish and remove brands independently from personal references', function () {
    $this->actingAs($this->admin)->post(route('admin.brands.store'), $this->payload)->assertSessionHasNoErrors()->assertRedirect();
    $brand = Brand::sole();
    expect($brand->domain)->toBe('apple.com')->and($brand->is_published)->toBeFalse()->and(TrackedItem::count())->toBe(0)->and(WebsiteIdentity::count())->toBe(0);
    $this->actingAs($this->admin)->patch(route('admin.brands.update', $brand->uuid), [...$this->payload, 'name' => 'Apple Music', 'is_published' => true])->assertSessionHasNoErrors();
    expect($brand->fresh()->name)->toBe('Apple Music')->and($brand->fresh()->is_published)->toBeTrue();
    $this->get(route('admin.brands.index', ['search' => 'Mela', 'letter' => 'A', 'sort' => 'name_desc']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/Brands/Index')->has('brands.data', 1)->where('brands.data.0.name', 'Apple Music')->where('filters.sort', 'name_desc'));
    $this->patch(route('admin.brands.update', $brand->uuid), [...$this->payload, 'is_published' => false])->assertSessionHasNoErrors();
    expect($brand->fresh()->is_published)->toBeFalse();
    $this->delete(route('admin.brands.destroy', $brand->uuid))->assertRedirect();
    expect(Brand::count())->toBe(0)->and(TrackedItem::count())->toBe(0);
});

test('ordinary users cannot access any catalog administration endpoint', function () {
    $brand = Brand::factory()->create();
    $this->actingAs(User::factory()->create());
    $this->get(route('admin.brands.index'))->assertForbidden();
    $this->postJson(route('admin.brands.store'), $this->payload)->assertForbidden();
    $this->patchJson(route('admin.brands.update', $brand->uuid), $this->payload)->assertForbidden();
    $this->deleteJson(route('admin.brands.destroy', $brand->uuid))->assertForbidden();
    expect(Brand::count())->toBe(1)->and($brand->fresh()->is_published)->toBeFalse();
});

test('guest can read only published catalog data and cannot mutate it', function () {
    $brand = Brand::factory()->published()->create(['name' => 'Apple Music', 'aliases' => ['Mùsica Apple'], 'domain' => 'apple.com']);
    Brand::factory()->create(['name' => 'Apple draft', 'domain' => 'draft.example']);
    TrackedItem::create(['user_id' => $this->admin->id, 'name' => 'Apple private person', 'slug' => 'apple-private', 'is_active' => true]);
    WebsiteIdentity::create(['domain' => 'apple-cache.example', 'canonical_url' => 'https://apple-cache.example/', 'status' => 'ready']);
    foreach (['APPLE', 'musica apple', 'apple.com'] as $search) {
        $this->getJson(route('brands.index', ['search' => $search]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $brand->uuid)->assertJsonMissingPath('data.0.id')->assertJsonMissingPath('data.0.logo_path')->assertJsonMissingPath('data.0.user_id');
    }
    $this->getJson(route('brands.index', ['letter' => 'B']))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('brands.index', ['search' => '%']))->assertOk()->assertJsonCount(0, 'data');
    $this->postJson(route('brands.index'), $this->payload)->assertMethodNotAllowed();
    $this->postJson(route('admin.brands.store'), $this->payload)->assertUnauthorized();
    expect(Brand::count())->toBe(2)->and(TrackedItem::count())->toBe(1)->and(WebsiteIdentity::count())->toBe(1);
});

test('catalog duplicate and malformed input validation prevents writes', function () {
    Brand::factory()->create(['name' => 'Apple', 'domain' => 'apple.com']);
    $this->actingAs($this->admin);
    $this->postJson(route('admin.brands.store'), [...$this->payload, 'name' => '  APPLE  '])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson(route('admin.brands.store'), [...$this->payload, 'name' => 'Other', 'domain' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('domain');
    $this->postJson(route('admin.brands.store'), [...$this->payload, 'name' => 'Other', 'aliases' => ['Apple', 'APPLE']])->assertUnprocessable()->assertJsonValidationErrors('aliases.0');
    $this->postJson(route('admin.brands.store'), [...$this->payload, 'name' => 'Other', 'fetch_logo' => true, 'remove_logo' => true])->assertUnprocessable()->assertJsonValidationErrors('logo');
    expect(Brand::count())->toBe(1);
});

test('manual logo upload replacement and removal reuse image storage without deleting shared files', function () {
    $this->actingAs($this->admin)->post(route('admin.brands.store'), [...$this->payload, 'logo' => UploadedFile::fake()->image('apple.png', 32, 32)])->assertSessionHasNoErrors();
    $brand = Brand::sole();
    $original = $brand->logo_path;
    Storage::disk('public')->assertExists($original);
    $this->get(route('brands.logo', $brand->uuid))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->patch(route('admin.brands.update', $brand->uuid), [...$this->payload, 'is_published' => true])->assertSessionHasNoErrors();
    expect($brand->fresh()->logo_path)->toBe($original);
    $this->patch(route('admin.brands.update', $brand->uuid), [...$this->payload, 'logo' => UploadedFile::fake()->image('other.jpg', 64, 64)])->assertSessionHasNoErrors();
    expect($brand->fresh()->logo_path)->not->toBe($original);
    $this->patch(route('admin.brands.update', $brand->uuid), [...$this->payload, 'remove_logo' => true])->assertSessionHasNoErrors();
    expect($brand->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertExists($original);
});

test('published logos are readable and unpublished logos are hidden from guests and ordinary users', function () {
    Storage::disk('public')->put('tracked-item-logos/shared.png', 'image');
    $brand = Brand::factory()->published()->create(['logo_path' => 'tracked-item-logos/shared.png', 'logo_mime_type' => 'image/png']);
    $this->get(route('brands.logo', $brand->uuid))->assertOk();
    $brand->update(['is_published' => false]);
    $this->get(route('brands.logo', $brand->uuid))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('brands.logo', $brand->uuid))->assertNotFound();
    $this->actingAs($this->admin)->delete(route('admin.brands.destroy', $brand->uuid))->assertRedirect();
    Storage::disk('public')->assertExists('tracked-item-logos/shared.png');
});

test('retrieval from website uses existing logo service and never imports personal or cached identities', function () {
    $service = Mockery::mock(WebsiteLogoService::class)->makePartial();
    $service->shouldReceive('preview')->once()->with('apple.com')->andReturn(['domain' => 'apple.com', 'website_url' => 'https://apple.com/', 'status' => 'ready', 'logo_url' => 'data:image/png;base64,'.base64_encode('safe-service-logo')]);
    $this->app->instance(WebsiteLogoService::class, $service);
    $this->actingAs($this->admin)->post(route('admin.brands.store'), [...$this->payload, 'fetch_logo' => true])->assertSessionHasNoErrors();
    Storage::disk('public')->assertExists(Brand::sole()->logo_path);
    expect(WebsiteIdentity::count())->toBe(0)->and(TrackedItem::count())->toBe(0);
});

test('missing remote logos and invalid uploads fail without creating a brand', function () {
    $service = Mockery::mock(WebsiteLogoService::class)->makePartial();
    $service->shouldReceive('preview')->once()->andReturn(['domain' => 'apple.com', 'website_url' => 'https://apple.com/', 'status' => 'unavailable', 'logo_url' => null]);
    $this->app->instance(WebsiteLogoService::class, $service);
    $this->actingAs($this->admin)->postJson(route('admin.brands.store'), [...$this->payload, 'fetch_logo' => true])->assertUnprocessable()->assertJsonValidationErrors('logo');
    $this->postJson(route('admin.brands.store'), [...$this->payload, 'logo' => UploadedFile::fake()->create('fake.svg', 20, 'image/svg+xml')])->assertUnprocessable()->assertJsonValidationErrors('logo');
    expect(Brand::count())->toBe(0);
});

test('search pagination and admin sort are bounded and stable', function () {
    for ($index = 0; $index < 25; $index++) {
        Brand::factory()->published()->create(['name' => sprintf('Apple %02d', $index)]);
    }
    $first = $this->getJson(route('brands.index', ['search' => 'Apple']))->assertOk()->assertJsonCount(20, 'data')->json('data');
    $last = $this->getJson(route('brands.index', ['search' => 'Apple', 'page' => 2]))->assertOk()->assertJsonCount(5, 'data')->json('data');
    expect(array_intersect(array_column($first, 'uuid'), array_column($last, 'uuid')))->toBe([]);
    $this->actingAs($this->admin)->get(route('admin.brands.index', ['sort' => 'name_desc']))->assertInertia(fn (Assert $page) => $page->where('brands.data.0.name', 'Apple 24'));
    $this->getJson(route('brands.index', ['letter' => 'invalid']))->assertUnprocessable();
});

test('catalog aliases prevent duplicate identities and never change personal catalog records', function () {
    Brand::factory()->create(['name' => 'Apple', 'domain' => 'apple.com', 'aliases' => ['Apple Inc']]);
    $this->actingAs($this->admin)->postJson(route('admin.brands.store'), [...$this->payload, 'name' => 'Apple Inc', 'aliases' => []])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson(route('admin.brands.store'), [...$this->payload, 'name' => 'Apple Music', 'aliases' => ['  music  ', 'MUSIC']])->assertUnprocessable()->assertJsonValidationErrors('aliases.0');
    expect(Brand::count())->toBe(1)->and(TrackedItem::count())->toBe(0);
});

test('cached website logo files can be reused without importing website identity records', function () {
    Storage::disk('public')->put('tracked-item-logos/apple.png', 'existing');
    $identity = WebsiteIdentity::create(['domain' => 'apple.com', 'canonical_url' => 'https://apple.com/', 'status' => 'ready', 'logo_path' => 'tracked-item-logos/apple.png', 'logo_mime_type' => 'image/png']);
    $before = $identity->fresh()->getAttributes();
    $this->actingAs($this->admin)->post(route('admin.brands.store'), [...$this->payload, 'fetch_logo' => true])->assertSessionHasNoErrors();
    expect(Brand::sole()->logo_path)->toBe('tracked-item-logos/apple.png')->and($identity->fresh()->getAttributes())->toBe($before)->and(WebsiteIdentity::count())->toBe(1);
});

test('brand admin UI fixture uses exclusively synthetic catalog entries', function () {
    Brand::factory()->published()->create(['name' => 'Apple', 'domain' => 'apple.com', 'aliases' => ['Apple Inc'], 'category' => 'Tecnologia']);
    $response = $this->actingAs($this->admin)->get(route('admin.brands.index'))->assertOk();
    if (getenv('BRAND_BROWSER_FIXTURE')) {
        file_put_contents(getenv('BRAND_BROWSER_FIXTURE'), json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR));
    }
    expect(Brand::count())->toBe(1);
});

test('an empty aliases field clears previous aliases with multipart form submissions', function () {
    $brand = Brand::factory()->create(['name' => 'Apple', 'domain' => 'apple.com', 'aliases' => ['Previous alias']]);
    $payload = $this->payload;
    unset($payload['aliases']);
    $this->actingAs($this->admin)->post(route('admin.brands.update', $brand->uuid), [...$payload, '_method' => 'patch', 'logo' => UploadedFile::fake()->image('clear.png', 16, 16)])->assertSessionHasNoErrors();
    expect($brand->fresh()->aliases)->toBe([]);
});

test('admin filters brands by category or no category while retaining all category choices', function () {
    Brand::factory()->create(['name' => 'Apple', 'category' => 'Tecnologia']);
    Brand::factory()->create(['name' => 'Adobe', 'category' => 'Tecnologia']);
    BrandCategory::create(['name' => 'Servizi']);
    Brand::factory()->create(['name' => 'Acme', 'category' => 'Servizi']);
    Brand::factory()->create(['name' => 'Alpha', 'category' => null]);
    Brand::factory()->create(['name' => 'Beta', 'category' => '']);
    $this->actingAs($this->admin);
    $this->get(route('admin.brands.index', ['category' => 'Tecnologia', 'search' => 'Apple', 'letter' => 'A']))
        ->assertInertia(fn (Assert $page) => $page->has('brands.data', 1)->where('brands.data.0.name', 'Apple')
            ->where('categories', ['Servizi', 'Tecnologia'])->where('filters.category', 'Tecnologia')->where('filters.without_category', false));
    $this->get(route('admin.brands.index', ['without_category' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('brands.data', 2)->where('filters.without_category', true));
    $this->get(route('admin.brands.index'))->assertInertia(fn (Assert $page) => $page->has('brands.data', 5));
    $this->getJson(route('admin.brands.index', ['category' => str_repeat('x', 101)]))->assertUnprocessable();
    $this->getJson(route('admin.brands.index', ['without_category' => 'invalid']))->assertUnprocessable();
});

test('a managed category is available to subsequent brand forms', function () {
    $this->actingAs($this->admin)->post(route('admin.brands.categories.store'), ['name' => '  Nuova categoria  '])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.brands.store'), [...$this->payload, 'category' => 'Nuova categoria', 'is_published' => true])->assertSessionHasNoErrors();
    $this->get(route('admin.brands.index'))->assertInertia(fn (Assert $page) => $page
        ->where('categories', ['Nuova categoria', 'Tecnologia'])->where('brands.data.0.category', 'Nuova categoria')->where('brands.data.0.is_published', true));
});
