<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

function richContentAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('allows an admin to upload a rich content image', function () {
    $admin = richContentAdmin();

    $response = $this->actingAs($admin)
        ->post(route('admin.rich-content-assets.store'), [
            'image' => UploadedFile::fake()->image('knowledge-inline.png', 1200, 800),
        ]);

    $response->assertOk()
        ->assertJsonStructure(['path', 'url']);

    $path = $response->json('path');

    expect($path)->toStartWith('editorial/rich-content/')
        ->and($response->json('url'))->toContain('/editorial-assets?path=editorial%2Frich-content%2F');

    Storage::disk('public')->assertExists($path);
});

it('allows an admin to delete a rich content image', function () {
    $admin = richContentAdmin();
    $path = 'editorial/rich-content/2026/04/test-image.png';

    Storage::disk('public')->put($path, 'fake-image');

    $this->actingAs($admin)
        ->delete(route('admin.rich-content-assets.destroy'), [
            'path' => $path,
        ])
        ->assertOk()
        ->assertJson([
            'deleted' => true,
        ]);

    Storage::disk('public')->assertMissing($path);
});

it('rejects image deletion outside the rich content directory', function () {
    $admin = richContentAdmin();

    $this->actingAs($admin)
        ->delete(route('admin.rich-content-assets.destroy'), [
            'path' => 'avatars/unsafe.png',
        ])
        ->assertSessionHasErrors(['path']);
});
