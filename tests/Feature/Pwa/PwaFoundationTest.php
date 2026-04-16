<?php

use App\Support\Pwa\PwaManifestData;

it('serves a professional web app manifest', function () {
    $response = $this->get('/manifest.webmanifest');

    $response->assertOk();
    $response->assertHeader(
        'Content-Type',
        'application/manifest+json; charset=UTF-8',
    );

    expect($response->json())->toMatchArray([
        'name' => 'Soamco Budget',
        'short_name' => 'Soamco',
        'start_url' => '/dashboard?source=pwa',
        'scope' => '/',
        'display' => 'standalone',
        'theme_color' => '#ea5a47',
        'background_color' => '#f6efe9',
    ]);

    expect($response->json('icons'))->not->toBeEmpty();
    expect($response->json('screenshots'))->not->toBeEmpty();
    expect($response->json('shortcuts'))->toHaveCount(3);

    $icons = collect($response->json('icons'));

    expect($icons->contains(fn (array $icon): bool => ($icon['purpose'] ?? null) === 'any maskable'))->toBeFalse()
        ->and($icons->where('purpose', 'maskable'))->toHaveCount(2)
        ->and($icons->every(fn (array $icon): bool => str_contains($icon['src'], '?v=')))->toBeTrue()
        ->and($icons->where('purpose', 'maskable')->pluck('sizes')->all())->toBe([
            '192x192',
            '512x512',
        ]);
});

it('ships icon and screenshot assets that exist with the declared sizes', function () {
    $manifest = app(PwaManifestData::class)->manifest();

    collect([...$manifest['icons'], ...$manifest['screenshots']])
        ->each(function (array $asset): void {
            $path = public_path(
                ltrim((string) str($asset['src'])->before('?'), '/'),
            );

            expect(is_file($path))->toBeTrue(
                "Missing PWA asset [{$asset['src']}].",
            );

            [$width, $height] = getimagesize($path);

            expect(sprintf('%dx%d', $width, $height))->toBe($asset['sizes']);
        });
});

it('renders a stable service worker with versioned caches and controlled activation', function () {
    $serviceWorker = app(PwaManifestData::class)->serviceWorker();

    $response = $this->get('/service-worker.js');

    $response->assertOk();
    $response->assertHeader(
        'Content-Type',
        'application/javascript; charset=UTF-8',
    );
    $response->assertHeader(
        'Service-Worker-Allowed',
        '/',
    );

    $content = $response->getContent();

    expect($content)
        ->toContain("const VERSION = '{$serviceWorker['version']}'")
        ->toContain($serviceWorker['cache_names']['static'])
        ->toContain($serviceWorker['cache_names']['images'])
        ->toContain('request.mode === \'navigate\'')
        ->toContain('SKIP_WAITING')
        ->toContain('clients.claim()')
        ->toContain('firebase.messaging()')
        ->toContain('pushsubscriptionchange')
        ->toContain('showNotification(title, options)')
        ->toContain('caches.keys()')
        ->toContain('caches.delete(cacheName)')
        ->toContain('/offline.html');

    expect($serviceWorker['static_asset_path_prefixes'])->toContain(
        '/build/assets/',
    );
    expect($serviceWorker['stable_image_path_prefixes'])->toBe(['/images/']);
});

it('precaches the offline fallback and current build shell assets only', function () {
    $precacheUrls = app(PwaManifestData::class)->precacheUrls();

    expect($precacheUrls)->toContain('/offline.html');
    expect($precacheUrls)->toContain('/manifest.webmanifest');
    expect(
        collect($precacheUrls)->contains(
            fn (string $url): bool => str_starts_with($url, '/build/assets/'),
        ),
    )->toBeTrue();
    expect(
        collect($precacheUrls)->contains(
            fn (string $url): bool => $url === '/dashboard?source=pwa',
        ),
    )->toBeFalse();
});
