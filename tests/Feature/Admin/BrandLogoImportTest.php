<?php

use App\Models\Brand;
use App\Models\BrandCategory;
use App\Models\TrackedItem;
use App\Models\User;
use App\Models\WebsiteIdentity;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    Http::preventStrayRequests();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function brandImportLogo(string $domain): WebsiteIdentity
{
    $path = 'tracked-item-logos/'.Str::uuid().'.png';
    Storage::disk('public')->put($path, UploadedFile::fake()->image('logo.png', 24, 24)->get());

    return WebsiteIdentity::create(['domain' => $domain, 'canonical_url' => 'https://'.$domain.'/', 'status' => 'ready', 'logo_path' => $path, 'logo_mime_type' => 'image/png']);
}

test('admin candidates deduplicate website logos across users without exposing personal references', function () {
    $logo = brandImportLogo('apple.com');
    foreach (User::factory()->count(2)->create() as $user) {
        TrackedItem::create(['user_id' => $user->id, 'name' => 'Nome privato '.$user->id, 'slug' => 'private-'.$user->id, 'website_identity_id' => $logo->id, 'is_active' => true]);
    }
    brandImportLogo('www.APPLE.com');
    $this->actingAs($this->admin)->getJson(route('admin.brands.import-candidates'))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.domain', 'apple.com')
        ->assertJsonPath('data.0.uuid', $logo->uuid)->assertJsonMissingPath('data.0.user_id')
        ->assertJsonMissingPath('data.0.name')->assertJsonMissingPath('data.0.logo_path');
    expect(Brand::count())->toBe(0)->and(TrackedItem::count())->toBe(2);
});

test('candidates exclude existing domains unavailable files missing images and unsupported images', function () {
    brandImportLogo('apple.com');
    Brand::factory()->create(['domain' => 'apple.com', 'is_published' => false]);
    $missing = brandImportLogo('missing.example.com');
    Storage::disk('public')->delete($missing->logo_path);
    brandImportLogo('www.missing.example.com');
    $unavailable = brandImportLogo('unavailable.example.com');
    $unavailable->update(['status' => 'unavailable']);
    $unsupported = brandImportLogo('unsupported.example.com');
    $unsupported->update(['logo_mime_type' => 'image/svg+xml']);
    WebsiteIdentity::create(['domain' => 'empty.example.com', 'canonical_url' => 'https://empty.example.com/', 'status' => 'ready']);
    $this->actingAs($this->admin)->getJson(route('admin.brands.import-candidates'))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.domain', 'missing.example.com');
});

test('only admins may list or import existing logos', function () {
    $logo = brandImportLogo('apple.com');
    $payload = ['name' => 'Apple', 'domain' => 'apple.com', 'is_published' => true, 'source_logo_uuid' => $logo->uuid];
    $this->getJson(route('admin.brands.import-candidates'))->assertUnauthorized();
    $this->postJson(route('admin.brands.store'), $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson(route('admin.brands.import-candidates'))->assertForbidden();
    $this->postJson(route('admin.brands.store'), $payload)->assertForbidden();
    expect(Brand::count())->toBe(0);
});

test('an explicit import reuses the stored image accepts category and publication and changes no source data', function () {
    BrandCategory::create(['name' => 'Tecnologia']);
    $logo = brandImportLogo('apple.com');
    $before = $logo->fresh()->getAttributes();
    $reference = TrackedItem::create(['user_id' => $this->admin->id, 'name' => 'Nome personale', 'slug' => 'private-name', 'website_identity_id' => $logo->id, 'is_active' => true]);
    $referenceBefore = $reference->fresh()->getAttributes();
    $this->actingAs($this->admin)->post(route('admin.brands.store'), [
        'name' => 'Apple', 'domain' => 'apple.com', 'aliases' => ['Apple Inc'], 'category' => 'Tecnologia',
        'is_published' => true, 'source_logo_uuid' => $logo->uuid, 'logo_path' => 'forged.png',
    ])->assertSessionHasNoErrors()->assertRedirect();
    $brand = Brand::sole();
    expect($brand->logo_path)->toBe($logo->logo_path)->and($brand->category)->toBe('Tecnologia')
        ->and($brand->is_published)->toBeTrue()->and($logo->fresh()->getAttributes())->toBe($before)
        ->and(TrackedItem::count())->toBe(1)->and($reference->fresh()->getAttributes())->toBe($referenceBefore);
    $this->getJson(route('brands.index', ['search' => 'Apple']))->assertJsonPath('data.0.uuid', $brand->uuid);
    $this->getJson(route('admin.brands.import-candidates'))->assertJsonCount(0, 'data');
});

test('repeat imports and equivalent domain sources cannot create duplicate brands even with a different name', function () {
    $logo = brandImportLogo('apple.com');
    $alternate = brandImportLogo('www.APPLE.com');
    $payload = ['name' => 'Apple', 'domain' => 'apple.com', 'is_published' => false, 'source_logo_uuid' => $logo->uuid];
    $this->actingAs($this->admin)->post(route('admin.brands.store'), $payload)->assertSessionHasNoErrors();
    $this->postJson(route('admin.brands.store'), [...$payload, 'name' => 'Altro nome', 'source_logo_uuid' => $alternate->uuid])
        ->assertUnprocessable()->assertJsonValidationErrors('source_logo_uuid');
    expect(Brand::count())->toBe(1);
});

test('stale sources forged domains conflicting logo modes and imports on edits are rejected without writes', function () {
    $logo = brandImportLogo('apple.com');
    $payload = ['name' => 'Apple', 'domain' => 'apple.com', 'is_published' => false, 'source_logo_uuid' => $logo->uuid];
    $this->actingAs($this->admin);
    $this->postJson(route('admin.brands.store'), [...$payload, 'domain' => 'different.example.com'])->assertUnprocessable()->assertJsonValidationErrors('domain');
    $this->postJson(route('admin.brands.store'), [...$payload, 'fetch_logo' => true])->assertUnprocessable()->assertJsonValidationErrors('logo');
    $this->postJson(route('admin.brands.store'), [...$payload, 'source_logo_uuid' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('source_logo_uuid');
    Storage::disk('public')->delete($logo->logo_path);
    $this->postJson(route('admin.brands.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('source_logo_uuid');
    expect(Brand::count())->toBe(0);
    $brand = Brand::factory()->create(['name' => 'Existing', 'domain' => 'existing.example.com']);
    $this->patchJson(route('admin.brands.update', $brand->uuid), [...$payload, 'domain' => 'existing.example.com'])->assertUnprocessable()->assertJsonValidationErrors('source_logo_uuid');
    expect($brand->fresh()->name)->toBe('Existing');
});

test('candidate search and pagination contain only unique usable images', function () {
    for ($index = 0; $index < 26; $index++) {
        brandImportLogo('brand'.$index.'.example.com');
    }
    $this->actingAs($this->admin);
    $first = $this->getJson(route('admin.brands.import-candidates'))->assertOk()->assertJsonCount(24, 'data')->assertJsonPath('has_more', true)->json('data');
    $last = $this->getJson(route('admin.brands.import-candidates', ['page' => 2]))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('has_more', false)->json('data');
    expect(array_intersect(array_column($first, 'uuid'), array_column($last, 'uuid')))->toBe([]);
    $this->getJson(route('admin.brands.import-candidates', ['search' => 'brand25.']))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson(route('admin.brands.import-candidates', ['page' => 0]))->assertUnprocessable();
});
