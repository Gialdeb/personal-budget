import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';

const bladeSource = readFileSync(
    new URL('../../resources/views/app.blade.php', import.meta.url),
    'utf8',
);
const appSource = readFileSync(
    new URL('../../resources/js/app.ts', import.meta.url),
    'utf8',
);
const pwaComposableSource = readFileSync(
    new URL('../../resources/js/composables/usePwa.ts', import.meta.url),
    'utf8',
);
const pwaBannerSource = readFileSync(
    new URL(
        '../../resources/js/components/PwaStatusBanner.vue',
        import.meta.url,
    ),
    'utf8',
);
const offlineSource = readFileSync(
    new URL('../../public/offline.html', import.meta.url),
    'utf8',
);
const manifestSource = readFileSync(
    new URL('../../app/Support/Pwa/PwaManifestData.php', import.meta.url),
    'utf8',
);
const iconSource = readFileSync(
    new URL('../../public/pwa/icon-source.svg', import.meta.url),
    'utf8',
);
const iconMaskableSource = readFileSync(
    new URL('../../public/pwa/icon-maskable-source.svg', import.meta.url),
    'utf8',
);

const pngAssets = [
    '../../public/pwa/icons/icon-64.png',
    '../../public/pwa/icons/icon-192.png',
    '../../public/pwa/icons/icon-512.png',
    '../../public/pwa/icons/icon-maskable-192.png',
    '../../public/pwa/icons/icon-maskable-512.png',
    '../../public/pwa/screenshots/dashboard-wide.png',
    '../../public/pwa/screenshots/dashboard-mobile.png',
    '../../public/apple-touch-icon.png',
];

function readBinaryAsset(relativePath) {
    return readFileSync(new URL(relativePath, import.meta.url));
}

function isPng(buffer) {
    return buffer
        .subarray(0, 8)
        .equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]));
}

test('root template exposes manifest and mobile web app meta tags', () => {
    assert.match(
        bladeSource,
        /rel="manifest" href="\{\{ route\('pwa\.manifest', \['v' => \$pwaVersion\]\) \}\}" type="application\/manifest\+json"/,
    );
    assert.match(bladeSource, /rel="icon" href="\/favicon\.ico\?v=\{\{ \$pwaVersion }}"/);
    assert.match(bladeSource, /name="theme-color" content="#ea5a47"/);
    assert.match(
        bladeSource,
        /name="apple-mobile-web-app-capable" content="yes"/,
    );
    assert.match(bladeSource, /name="soamco-pwa-enabled"/);
    assert.match(bladeSource, /name="soamco-pwa-debug"/);
    assert.match(bladeSource, /name="soamco-push-debug"/);
    assert.match(bladeSource, /apple-touch-icon" sizes="180x180"/);
});

test('manifest data keeps any and maskable icons separated', () => {
    assert.doesNotMatch(manifestSource, /'any maskable'/);
    assert.match(manifestSource, /return sprintf\('%s\?v=%s', \$path, substr\(\$fingerprint, 0, 12\)\);/);
    assert.match(
        manifestSource,
        /icon-maskable-192\.png', '192x192', 'maskable'/,
    );
    assert.match(
        manifestSource,
        /icon-maskable-512\.png', '512x512', 'maskable'/,
    );
    assert.match(manifestSource, /'debug_logging' => \$this->debugLoggingEnabled\(\)/);
});

test('pwa icon sources use the brand red background without the old pale frame', () => {
    assert.match(iconSource, /rect width="1024" height="1024" rx="248" fill="url\(#pwa-icon-bg\)"/);
    assert.match(iconMaskableSource, /rect width="1024" height="1024" rx="248" fill="url\(#pwa-icon-bg\)"/);
    assert.doesNotMatch(iconSource, /fill="#F6EFE9"/);
    assert.doesNotMatch(iconMaskableSource, /fill="#F6EFE9"/);
});

test('app boot mounts the shared PWA banner', () => {
    assert.match(
        appSource,
        /import PwaStatusBanner from '@\/components\/PwaStatusBanner\.vue';/,
    );
    assert.match(
        appSource,
        /import \{\s*initializeForegroundPushNotifications,[\s\S]*} from '@\/lib\/push-notifications';/,
    );
    assert.match(appSource, /h\(PwaStatusBanner\)/);
    assert.match(appSource, /void initializeForegroundPushNotifications\(\);/);
});

test('service worker registration keeps a stable path and controlled update flow', () => {
    assert.match(pwaComposableSource, /register\(\s*'\/service-worker\.js'/);
    assert.match(pwaComposableSource, /updateViaCache:\s*'none'/);
    assert.match(
        pwaComposableSource,
        /Bootstrapping global beforeinstallprompt listeners\./,
    );
    assert.match(pwaComposableSource, /PWA_DEBUG_SELECTOR/);
    assert.match(pwaComposableSource, /PWA_DEBUG_STORAGE_KEY/);
    assert.match(pwaComposableSource, /attachInstallPromptListeners\(\);/);
    assert.match(pwaComposableSource, /window\.addEventListener\(\s*'beforeinstallprompt'/);
    assert.match(
        pwaComposableSource,
        /Captured beforeinstallprompt globally and cached it for reuse\./,
    );
    assert.match(pwaComposableSource, /event\.preventDefault\(\)/);
    assert.match(pwaComposableSource, /window\.addEventListener\(\s*'appinstalled'/);
    assert.match(
        pwaComposableSource,
        /Browser install prompt is available\./,
    );
    assert.match(
        pwaComposableSource,
        /No browser install prompt is currently available\./,
    );
    assert.match(
        pwaComposableSource,
        /launchInstall\(\) invoked\. installPromptEvent present=/,
    );
    assert.match(
        pwaComposableSource,
        /Calling prompt\(\) directly from the install CTA user gesture\./,
    );
    assert.match(pwaComposableSource, /await deferredPrompt\.prompt\(\)/);
    assert.match(
        pwaComposableSource,
        /const \{ outcome } = await deferredPrompt\.userChoice/,
    );
    assert.match(pwaComposableSource, /installState\.value = 'dismissed'/);
    assert.doesNotMatch(
        pwaComposableSource,
        /const deferredPrompt = installPromptEvent\.value;\s*installPromptEvent\.value = null;\s*isLaunchingInstallPrompt\.value = true;/,
    );
    assert.match(pwaComposableSource, /navigator\.standalone === true/);
    assert.match(pwaComposableSource, /window\.matchMedia\('\(display-mode: standalone\)'\)\.matches/);
    assert.match(pwaComposableSource, /currentRegistration\.waiting/);
    assert.match(
        pwaComposableSource,
        /postMessage\(\{ type: 'SKIP_WAITING' }\)/,
    );
    assert.match(pwaComposableSource, /controllerchange/);
    assert.match(pwaComposableSource, /registration\.value\.update\(\)/);
    assert.match(pwaComposableSource, /window\.setInterval/);
});

test('banner copy covers offline state and update refresh UX', () => {
    assert.match(pwaBannerSource, /app\.pwa\.offline\.title/);
    assert.match(pwaBannerSource, /app\.pwa\.update\.title/);
    assert.match(pwaBannerSource, /applyUpdate/);
});

test('offline fallback is present and human readable', () => {
    assert.match(offlineSource, /You are offline\./);
    assert.match(offlineSource, /Try again/);
    assert.match(offlineSource, /Go to home/);
});

test('required PWA binary assets exist and are valid PNG files', () => {
    for (const asset of pngAssets) {
        const assetUrl = new URL(asset, import.meta.url);

        assert.equal(existsSync(assetUrl), true, `Missing asset: ${asset}`);
        assert.equal(
            isPng(readBinaryAsset(asset)),
            true,
            `Invalid PNG asset: ${asset}`,
        );
    }
});
