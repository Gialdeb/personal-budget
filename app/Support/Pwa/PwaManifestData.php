<?php

namespace App\Support\Pwa;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use JsonException;

class PwaManifestData
{
    protected const SHELL_ENTRYPOINT = 'resources/js/app.ts';

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return [
            'id' => '/',
            'name' => 'Soamco Budget',
            'short_name' => 'Soamco',
            'description' => 'Household budgeting, transactions, recurring planning, and account visibility in one installable workspace.',
            'lang' => 'it',
            'dir' => 'ltr',
            'start_url' => '/dashboard?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => '#ea5a47',
            'background_color' => '#f6efe9',
            'categories' => ['finance', 'productivity', 'utilities'],
            'prefer_related_applications' => false,
            'icons' => $this->icons(),
            'screenshots' => $this->screenshots(),
            'shortcuts' => $this->shortcuts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceWorker(): array
    {
        return [
            'version' => $this->version(),
            'firebase_messaging' => $this->firebaseMessagingConfig(),
            'debug_logging' => $this->debugLoggingEnabled(),
            'offline_url' => '/offline.html',
            'precache_urls' => $this->precacheUrls(),
            'cache_names' => [
                'static' => sprintf('soamco-budget-static-%s', $this->version()),
                'images' => sprintf('soamco-budget-images-%s', $this->version()),
            ],
            'static_asset_path_prefixes' => ['/build/assets/'],
            'stable_image_path_prefixes' => [
                '/images/',
            ],
            'cache_prefix' => 'soamco-budget-',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    protected function firebaseMessagingConfig(): ?array
    {
        $config = [
            'apiKey' => trim((string) config('push-notifications.firebase_web.api_key', '')),
            'authDomain' => trim((string) config('push-notifications.firebase_web.auth_domain', '')),
            'projectId' => trim((string) config('push-notifications.firebase_web.project_id', '')),
            'storageBucket' => trim((string) config('push-notifications.firebase_web.storage_bucket', '')),
            'messagingSenderId' => trim((string) config('push-notifications.firebase_web.messaging_sender_id', '')),
            'appId' => trim((string) config('push-notifications.firebase_web.app_id', '')),
        ];

        return collect($config)->every(
            fn (string $value): bool => $value !== '',
        )
            ? $config
            : null;
    }

    protected function debugLoggingEnabled(): bool
    {
        return app()->isLocal() || (bool) config('app.debug');
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function icons(): array
    {
        return [
            $this->icon('/pwa/icons/icon-64.png', '64x64'),
            $this->icon('/pwa/icons/icon-72.png', '72x72'),
            $this->icon('/pwa/icons/icon-96.png', '96x96'),
            $this->icon('/pwa/icons/icon-128.png', '128x128'),
            $this->icon('/pwa/icons/icon-144.png', '144x144'),
            $this->icon('/pwa/icons/icon-152.png', '152x152'),
            $this->icon('/pwa/icons/icon-167.png', '167x167'),
            $this->icon('/pwa/icons/icon-180.png', '180x180'),
            $this->icon('/pwa/icons/icon-192.png', '192x192'),
            $this->icon('/pwa/icons/icon-256.png', '256x256'),
            $this->icon('/pwa/icons/icon-384.png', '384x384'),
            $this->icon('/pwa/icons/icon-512.png', '512x512'),
            $this->icon('/pwa/icons/icon-maskable-192.png', '192x192', 'maskable'),
            $this->icon('/pwa/icons/icon-maskable-512.png', '512x512', 'maskable'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function screenshots(): array
    {
        return [
            [
                'src' => '/pwa/screenshots/dashboard-wide.png',
                'sizes' => '1280x720',
                'type' => 'image/png',
                'form_factor' => 'wide',
                'label' => 'Dashboard overview with balances, monthly trends, and planning cards.',
            ],
            [
                'src' => '/pwa/screenshots/dashboard-mobile.png',
                'sizes' => '1170x2532',
                'type' => 'image/png',
                'label' => 'Mobile dashboard and transactions view optimized for quick checks on iPhone and Android.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function shortcuts(): array
    {
        return [
            [
                'name' => 'Dashboard',
                'short_name' => 'Dashboard',
                'description' => 'Open your budgeting dashboard immediately.',
                'url' => '/dashboard?source=pwa-shortcut-dashboard',
                'icons' => [
                    $this->icon('/pwa/icons/icon-192.png', '192x192'),
                ],
            ],
            [
                'name' => 'Transactions',
                'short_name' => 'Transactions',
                'description' => 'Review the current month and add new entries.',
                'url' => '/transactions?source=pwa-shortcut-transactions',
                'icons' => [
                    $this->icon('/pwa/icons/icon-192.png', '192x192'),
                ],
            ],
            [
                'name' => 'Notifications',
                'short_name' => 'Alerts',
                'description' => 'Jump straight to unread account notifications.',
                'url' => '/notifications?source=pwa-shortcut-notifications',
                'icons' => [
                    $this->icon('/pwa/icons/icon-192.png', '192x192'),
                ],
            ],
        ];
    }

    public function version(): string
    {
        $payload = [
            'app_version' => (string) config('app.version'),
            'vite_manifest_hash' => $this->viteManifestHash(),
            'manifest' => [
                'name' => 'Soamco Budget',
                'start_url' => '/dashboard?source=pwa',
                'theme_color' => '#ea5a47',
                'background_color' => '#f6efe9',
                'icons' => $this->icons(),
                'screenshots' => $this->screenshots(),
                'shortcuts' => $this->shortcuts(),
            ],
            'file_hashes' => $this->fingerprintedFiles(),
        ];

        try {
            $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $encodedPayload = serialize($payload);
        }

        return substr(hash('sha256', $encodedPayload), 0, 20);
    }

    /**
     * @return array<int, string>
     */
    public function precacheUrls(): array
    {
        return array_values(array_unique([
            '/manifest.webmanifest',
            '/offline.html',
            ...array_map(
                fn (array $icon): string => $icon['src'],
                $this->icons(),
            ),
            ...$this->shellAssetUrls(),
        ]));
    }

    /**
     * @return array<int, string>
     */
    protected function shellAssetUrls(): array
    {
        if ($this->usesHotAssets()) {
            return [];
        }

        $manifestPath = public_path('build/manifest.json');
        if (! is_file($manifestPath)) {
            return [];
        }

        try {
            $manifest = json_decode(
                File::get($manifestPath),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return [];
        }

        if (! is_array($manifest)) {
            return [];
        }

        $assets = [];
        $visited = [];

        $collect = function (string $entry) use (&$collect, &$assets, &$manifest, &$visited): void {
            if (isset($visited[$entry]) || ! isset($manifest[$entry])) {
                return;
            }

            $visited[$entry] = true;
            $chunk = $manifest[$entry];

            if (isset($chunk['file']) && is_string($chunk['file'])) {
                $assets[] = '/build/'.$chunk['file'];
            }

            foreach ($chunk['css'] ?? [] as $cssAsset) {
                if (is_string($cssAsset)) {
                    $assets[] = '/build/'.$cssAsset;
                }
            }

            foreach ($chunk['imports'] ?? [] as $import) {
                if (is_string($import)) {
                    $collect($import);
                }
            }

            foreach ($chunk['dynamicImports'] ?? [] as $import) {
                if (is_string($import)) {
                    $collect($import);
                }
            }
        };

        $collect(static::SHELL_ENTRYPOINT);

        return array_values(array_unique($assets));
    }

    /**
     * @return array<string, string>
     */
    protected function fingerprintedFiles(): array
    {
        $files = [
            public_path('offline.html'),
            public_path('favicon.ico'),
            public_path('favicon.svg'),
            public_path('apple-touch-icon.png'),
        ];

        $files = [
            ...$files,
            ...array_map(
                fn (array $icon): string => public_path(
                    ltrim((string) str($icon['src'])->before('?'), '/'),
                ),
                $this->icons(),
            ),
            ...array_map(
                fn (array $screenshot): string => public_path(
                    ltrim($screenshot['src'], '/'),
                ),
                $this->screenshots(),
            ),
        ];

        $fingerprints = [];

        foreach (array_unique($files) as $file) {
            if (! is_file($file)) {
                continue;
            }

            $relativePath = str($file)->after(public_path().DIRECTORY_SEPARATOR)
                ->replace('\\', '/')
                ->toString();

            $fingerprints[$relativePath] = md5_file($file) ?: '';
        }

        return $fingerprints;
    }

    /**
     * @return array<string, string>
     */
    protected function icon(
        string $src,
        string $sizes,
        string $purpose = 'any',
    ): array {
        return [
            'src' => $this->versionedAssetPath($src),
            'sizes' => $sizes,
            'type' => 'image/png',
            'purpose' => $purpose,
        ];
    }

    protected function versionedAssetPath(string $path): string
    {
        $publicPath = public_path(ltrim($path, '/'));

        if (! is_file($publicPath)) {
            return $path;
        }

        $fingerprint = md5_file($publicPath);

        if ($fingerprint === false) {
            return $path;
        }

        return sprintf('%s?v=%s', $path, substr($fingerprint, 0, 12));
    }

    protected function usesHotAssets(): bool
    {
        return app()->isLocal() && Vite::isRunningHot();
    }

    protected function viteManifestHash(): string
    {
        if ($this->usesHotAssets()) {
            return 'hot';
        }

        $manifestPath = public_path('build/manifest.json');

        if (! is_file($manifestPath)) {
            return 'missing';
        }

        return md5_file($manifestPath) ?: 'missing';
    }
}
