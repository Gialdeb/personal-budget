<?php

use Illuminate\Support\Facades\Storage;

it('serves public editorial assets through a dedicated route', function () {
    Storage::disk('public')->put(
        'editorial/rich-content/2026/04/help-center-image.png',
        'fake-image-content',
    );

    $this->get('/editorial-assets?path=editorial/rich-content/2026/04/help-center-image.png')
        ->assertOk();
});

it('does not serve files outside the editorial rich content directory', function () {
    Storage::disk('public')->put('avatars/unsafe.png', 'fake');

    $this->get('/editorial-assets?path=avatars/unsafe.png')
        ->assertNotFound();
});
