<?php

use App\Models\Brand;
use App\Models\BrandCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('only administrators may manage categories or move brands', function () {
    $category = BrandCategory::create(['name' => 'Tecnologia']);
    $brand = Brand::factory()->create();
    $this->postJson(route('admin.brands.categories.store'), ['name' => 'Nuova'])->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $this->postJson(route('admin.brands.categories.store'), ['name' => 'Nuova'])->assertForbidden();
    $this->patchJson(route('admin.brands.categories.update', $category->uuid), ['name' => 'Nuova'])->assertForbidden();
    $this->deleteJson(route('admin.brands.categories.destroy', $category->uuid))->assertForbidden();
    $this->postJson(route('admin.brands.move'), ['category_uuid' => $category->uuid, 'brand_uuids' => [$brand->uuid]])->assertForbidden();
    expect($category->fresh()->name)->toBe('Tecnologia')->and($brand->fresh()->category)->toBeNull();
});

test('empty categories can be created renamed and deleted and appear in filters and forms', function () {
    $this->actingAs($this->admin)->post(route('admin.brands.categories.store'), ['name' => '  Tecnologia  '])->assertSessionHasNoErrors();
    $category = BrandCategory::sole();
    $this->get(route('admin.brands.index'))->assertInertia(fn (Assert $page) => $page
        ->where('categories', ['Tecnologia'])->where('category_management.0.brands_count', 0));
    $this->postJson(route('admin.brands.categories.store'), ['name' => 'Tecnologia'])->assertUnprocessable();
    $this->patch(route('admin.brands.categories.update', $category->uuid), ['name' => 'Servizi'])->assertSessionHasNoErrors();
    $this->delete(route('admin.brands.categories.destroy', $category->uuid))->assertSessionHasNoErrors();
    expect(BrandCategory::count())->toBe(0);
});

test('rename preserves every brand attribute except category including draft and published records', function () {
    $category = BrandCategory::create(['name' => 'Tecnologia']);
    $brands = Brand::factory()->count(2)->sequence(['is_published' => true], ['is_published' => false])
        ->create(['category' => $category->name, 'aliases' => ['Alias'], 'logo_path' => 'brands/preserved.png', 'logo_mime_type' => 'image/png']);
    $before = $brands->map(fn (Brand $brand): array => $brand->fresh()->getAttributes());
    $this->actingAs($this->admin)->patch(route('admin.brands.categories.update', $category->uuid), ['name' => 'Elettronica'])->assertSessionHasNoErrors();
    foreach ($brands as $index => $brand) {
        expect($brand->fresh()->getAttributes())->toBe([...$before[$index], 'category' => 'Elettronica']);
    }
    $this->get(route('admin.brands.index', ['category' => 'Elettronica']))->assertInertia(fn (Assert $page) => $page
        ->has('brands.data', 2)->where('category_management.0.brands_count', 2));
});

test('nonempty categories require reassignment and bulk moves do not alter other brand data', function () {
    $source = BrandCategory::create(['name' => 'Origine']);
    $target = BrandCategory::create(['name' => 'Destinazione']);
    $brands = Brand::factory()->count(3)->create(['category' => $source->name]);
    $before = $brands->map(fn (Brand $brand): array => $brand->fresh()->getAttributes());
    $this->actingAs($this->admin)->deleteJson(route('admin.brands.categories.destroy', $source->uuid))->assertUnprocessable()->assertJsonValidationErrors('category');
    $this->post(route('admin.brands.move'), ['brand_uuids' => $brands->take(2)->pluck('uuid')->all(), 'category_uuid' => $target->uuid])->assertSessionHasNoErrors();
    foreach ($brands->take(2) as $index => $brand) {
        expect($brand->fresh()->getAttributes())->toBe([...$before[$index], 'category' => $target->name]);
    }
    expect($brands->last()->fresh()->category)->toBe($source->name);
    $this->deleteJson(route('admin.brands.categories.destroy', $source->uuid))->assertUnprocessable();
    $this->post(route('admin.brands.move'), ['source_category_uuid' => $source->uuid, 'category_uuid' => $target->uuid])->assertSessionHasNoErrors();
    $this->delete(route('admin.brands.categories.destroy', $source->uuid))->assertSessionHasNoErrors();
    expect(Brand::where('category', $target->name)->count())->toBe(3);
});

test('invalid destinations selections and unmanaged brand categories are rejected atomically', function () {
    $category = BrandCategory::create(['name' => 'Tecnologia']);
    $brand = Brand::factory()->create();
    $this->actingAs($this->admin);
    $this->postJson(route('admin.brands.move'), ['brand_uuids' => [$brand->uuid], 'category_uuid' => fake()->uuid()])->assertUnprocessable();
    $this->postJson(route('admin.brands.move'), ['brand_uuids' => [$brand->uuid, fake()->uuid()], 'category_uuid' => $category->uuid])->assertUnprocessable();
    $this->postJson(route('admin.brands.move'), ['brand_uuids' => [], 'category_uuid' => $category->uuid])->assertUnprocessable();
    $this->postJson(route('admin.brands.move'), ['source_category_uuid' => $category->uuid, 'category_uuid' => $category->uuid])->assertUnprocessable();
    $this->postJson(route('admin.brands.store'), ['name' => 'Example', 'domain' => 'example.com', 'category' => 'Non gestita', 'is_published' => true])->assertUnprocessable()->assertJsonValidationErrors('category');
    expect($brand->fresh()->category)->toBeNull()->and(Brand::count())->toBe(1);
});

test('category rename rolls back if the brand update fails', function () {
    $category = BrandCategory::create(['name' => 'Tecnologia']);
    $brand = Brand::factory()->create(['category' => $category->name]);
    $fail = true;
    DB::listen(function (QueryExecuted $event) use (&$fail): void {
        if ($fail && str_starts_with($event->sql, 'update "brands"')) {
            $fail = false;
            throw new RuntimeException('Simulated failure');
        }
    });
    $this->withoutExceptionHandling()->actingAs($this->admin);
    expect(fn () => $this->patch(route('admin.brands.categories.update', $category->uuid), ['name' => 'Nuova']))->toThrow(RuntimeException::class, 'Simulated failure');
    expect($category->fresh()->name)->toBe('Tecnologia')->and($brand->fresh()->category)->toBe('Tecnologia');
});

test('migration imports existing category labels without touching brands and rollback retains brand data', function () {
    $brands = Brand::factory()->count(3)->sequence(['category' => 'Tecnologia'], ['category' => 'Tecnologia'], ['category' => 'Servizi'])->create();
    $before = $brands->map(fn (Brand $brand): array => $brand->fresh()->getAttributes());
    $migration = require database_path('migrations/2026_10_23_080645_create_brand_categories_table.php');
    $migration->down();
    $migration->up();
    expect(BrandCategory::orderBy('name')->pluck('name')->all())->toBe(['Servizi', 'Tecnologia']);
    foreach ($brands as $index => $brand) {
        expect($brand->fresh()->getAttributes())->toBe($before[$index]);
    }
    $migration->down();
    foreach ($brands as $index => $brand) {
        expect($brand->fresh()->getAttributes())->toBe($before[$index]);
    }
    $migration->up();
});

test('transferring and deleting a category is atomic and preserves all brand attributes', function () {
    $source = BrandCategory::create(['name' => 'Origine']);
    $target = BrandCategory::create(['name' => 'Destinazione']);
    $brands = Brand::factory()->count(3)->create(['category' => $source->name, 'aliases' => ['Alias'], 'logo_path' => 'brands/logo.png', 'is_published' => true]);
    $before = $brands->map(fn (Brand $brand): array => $brand->fresh()->getAttributes());
    $this->actingAs($this->admin)->post(route('admin.brands.move'), ['source_category_uuid' => $source->uuid, 'category_uuid' => $target->uuid, 'brand_uuids' => [], 'delete_source' => true])->assertSessionHasNoErrors();
    expect(BrandCategory::find($source->id))->toBeNull()->and($target->brands()->count())->toBe(3);
    foreach ($brands as $index => $brand) {
        expect($brand->fresh()->getAttributes())->toBe([...$before[$index], 'category' => $target->name]);
    }
});

test('transfer deletion requires a distinct valid source and target and rolls back if deletion fails', function () {
    $source = BrandCategory::create(['name' => 'Origine']);
    $target = BrandCategory::create(['name' => 'Destinazione']);
    $brand = Brand::factory()->create(['category' => $source->name]);
    $this->actingAs($this->admin);
    $this->postJson(route('admin.brands.move'), ['category_uuid' => $target->uuid, 'brand_uuids' => [$brand->uuid], 'delete_source' => true])->assertUnprocessable()->assertJsonValidationErrors('source_category_uuid');
    $this->postJson(route('admin.brands.move'), ['source_category_uuid' => $source->uuid, 'category_uuid' => $source->uuid, 'delete_source' => true])->assertUnprocessable();
    $this->postJson(route('admin.brands.move'), ['source_category_uuid' => $source->uuid, 'category_uuid' => fake()->uuid(), 'delete_source' => true])->assertUnprocessable();
    $fail = true;
    DB::listen(function (QueryExecuted $event) use (&$fail): void {
        if ($fail && str_starts_with($event->sql, 'delete') && str_contains($event->sql, '"brand_categories"')) {
            $fail = false;
            throw new RuntimeException('Simulated deletion failure');
        }
    });
    $this->withoutExceptionHandling();
    expect(fn () => $this->post(route('admin.brands.move'), ['source_category_uuid' => $source->uuid, 'category_uuid' => $target->uuid, 'delete_source' => true]))->toThrow(RuntimeException::class, 'Simulated deletion failure');
    expect($source->fresh())->not->toBeNull()->and($brand->fresh()->category)->toBe($source->name)->and($target->brands()->count())->toBe(0);
});
