<?php

use App\Support\Pwa\PwaManifestData;

test('asset version endpoint returns the current no cache asset version', function () {
    $response = $this->get(route('asset-version'));
    $cacheControl = (string) $response->headers->get('Cache-Control');

    $response->assertOk()
        ->assertJson([
            'version' => app(PwaManifestData::class)->version(),
        ]);

    expect($cacheControl)->toContain('no-cache')
        ->toContain('no-store')
        ->toContain('must-revalidate');
});
