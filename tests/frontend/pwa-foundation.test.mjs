import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

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
        /rel="manifest" href="\{\{ route\('pwa\.manifest', \['v' => \$pwaVersion]\) }}" type="application\/manifest\+json"/,
    );
    assert.match(
        bladeSource,
        /rel="icon" href="\/favicon\.ico\?v=\{\{ \$pwaVersion }}"/,
    );
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
    assert.match(
        manifestSource,
        /return sprintf\('%s\?v=%s', \$path, substr\(\$fingerprint, 0, 12\)\);/,
    );
    assert.match(
        manifestSource,
        /icon-maskable-192\.png', '192x192', 'maskable'/,
    );
    assert.match(
        manifestSource,
        /icon-maskable-512\.png', '512x512', 'maskable'/,
    );
    assert.match(
        manifestSource,
        /'debug_logging' => \$this->debugLoggingEnabled\(\)/,
    );
});

test('pwa icon sources use the brand red background without the old pale frame', () => {
    assert.match(
        iconSource,
        /rect width="1024" height="1024" rx="248" fill="url\(#pwa-icon-bg\)"/,
    );
    assert.match(
        iconMaskableSource,
        /rect width="1024" height="1024" rx="248" fill="url\(#pwa-icon-bg\)"/,
    );
    assert.doesNotMatch(iconSource, /fill="#F6EFE9"/);
    assert.doesNotMatch(iconMaskableSource, /fill="#F6EFE9"/);
});

test('app boot mounts the shared PWA banner', () => {
    assert.match(
        appSource,
        /import PwaStatusBanner from '@\/components\/PwaStatusBanner\.vue';/,
    );
    assert.match(appSource, /await import\('@\/lib\/push-notifications'\)/);
    assert.match(appSource, /h\(PwaStatusBanner\)/);
    assert.match(appSource, /cleanupLegacyFirebaseMessagingServiceWorker\(\)/);
    assert.match(appSource, /initializeForegroundPushNotifications\(\)/);
});

test('startup shell is independent of JavaScript assets and external fonts are non-blocking', () => {
    assert.match(bladeSource, /id="soamco-startup" role="status"/);
    assert.ok(
        bladeSource.indexOf('id="soamco-startup"') <
            bladeSource.indexOf('@inertia\n'),
    );
    assert.match(bladeSource, /media="print" onload="this.media='all'"/);
    assert.match(bladeSource, /display=swap/);
    assert.doesNotMatch(appSource, /from '@\/lib\/push-notifications'/);
    assert.ok(
        appSource.indexOf('.mount(el)') <
            appSource.indexOf('void initializePushAfterRender'),
    );
    assert.match(appSource, /nextTick\(/);
    assert.match(appSource, /soamco:mounted/);
});

test('startup fallback appears only on failure, clears on mount, and does not return on resume', () => {
    const script = bladeSource.match(
        /<script>\s*(\(\(\) => \{\s*const shell =[\s\S]*?)<\/script>/,
    )[1];

    for (const trigger of ['ready', 'error', 'timeout', 'offline']) {
        const loading = { hidden: false };
        const error = { hidden: true };
        const listeners = new Map();
        let removed = false;
        let timeout;
        runInNewContext(script, {
            document: {
                documentElement: { lang: 'it' },
                getElementById: () => ({
                    querySelector: (selector) =>
                        selector.includes('loading') ? loading : error,
                    remove: () => {
                        removed = true;
                    },
                }),
            },
            navigator: { onLine: trigger !== 'offline' },
            performance: { mark: () => {} },
            window: {
                setTimeout: (callback) => {
                    timeout = callback;

                    return 1;
                },
                clearTimeout() {},
                addEventListener: (name, fn) => listeners.set(name, fn),
                removeEventListener: (name) => listeners.delete(name),
            },
            setTimeout: () =>
                assert.fail('Slow loading is not a bootstrap failure'),
        });
        assert.equal(removed, false);
        assert.equal(error.hidden, trigger !== 'offline');

        if (trigger !== 'ready') {
            if (trigger === 'timeout') {
                timeout();
            } else {
                listeners.get('soamco:startup-error')();
            }

            assert.equal(error.hidden, false);
            assert.equal(loading.hidden, true);
        }

        listeners.get('soamco:ready')();
        assert.equal(removed, true);
        timeout();
        assert.equal(removed, true);
        assert.equal(listeners.has('soamco:startup-error'), false);
        assert.equal(listeners.has('visibilitychange'), false);
        assert.equal(listeners.has('pageshow'), false);
    }
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
    assert.match(
        pwaComposableSource,
        /window\.addEventListener\(\s*'beforeinstallprompt'/,
    );
    assert.match(
        pwaComposableSource,
        /Captured beforeinstallprompt globally and cached it for reuse\./,
    );
    assert.match(pwaComposableSource, /event\.preventDefault\(\)/);
    assert.match(
        pwaComposableSource,
        /window\.addEventListener\(\s*'appinstalled'/,
    );
    assert.match(pwaComposableSource, /Browser install prompt is available\./);
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
    assert.match(
        pwaComposableSource,
        /window\.matchMedia\('\(display-mode: standalone\)'\)\.matches/,
    );
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
    assert.match(pwaBannerSource, /isOffline \? 'offline' : connectionIssue/);
    assert.match(pwaBannerSource, /app\.pwa\.update\.title/);
    assert.match(pwaBannerSource, /applyUpdate/);
});

test('offline fallback is present and human readable', () => {
    assert.match(offlineSource, /You are offline/);
    assert.match(offlineSource, /Retry/);
    assert.match(offlineSource, /Riprova/);
    assert.match(offlineSource, /class="brand-logo"/);
    assert.match(offlineSource, /aria-label="Soamco Budget"/);
    assert.match(offlineSource, /window.parent.location.reload/);
    assert.doesNotMatch(offlineSource, /auth\.user|data-page/);
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

test('first worker activation does not announce an update while replacement and requested reload still work', () => {
    const block = pwaComposableSource.match(
        /if \(controllerChangeHandler === null\) \{([\s\S]*?)navigator\.serviceWorker\.addEventListener/,
    )[1];
    let reloads = 0;
    let requestedReload = false;
    const state = {
        navigator: { serviceWorker: { controller: null } },
        window: { location: { reload: () => reloads++ } },
        controllerChangeHandler: null,
        waitingWorker: { value: null },
        isApplyingUpdate: { value: false },
        isUpdateReady: { value: false },
        consumeReloadOnActivateFlag: () => requestedReload,
    };
    runInNewContext(block, state);
    state.navigator.serviceWorker.controller = {
        scriptURL: '/service-worker.js',
    };
    state.controllerChangeHandler();
    assert.equal(state.isUpdateReady.value, false);
    state.navigator.serviceWorker.controller = {
        scriptURL: '/service-worker.js?v=2',
    };
    state.controllerChangeHandler();
    assert.equal(state.isUpdateReady.value, true);
    requestedReload = true;
    state.controllerChangeHandler();
    assert.equal(reloads, 1);
});

test('session validation starts before asynchronous page resolution without duplicate initialization', () => {
    const bootstrap = appSource.slice(
        appSource.indexOf('const initialPageElement ='),
        appSource.indexOf('createInertiaApp({'),
    );
    const calls = [];
    runInNewContext(bootstrap, {
        document: {
            getElementById: () => ({
                dataset: {
                    page: JSON.stringify({
                        props: { auth: { user: { uuid: 'A' } } },
                    }),
                },
            }),
            documentElement: { hasAttribute: () => true },
        },
        initializeSessionIsolation: (...args) => calls.push(args),
    });
    assert.deepEqual(calls, [['A', true]]);
    assert.match(appSource, /if \(!sessionIsolationInitialized\)/);
});

test('loading screens use neutral accessible copy and a delayed motion aware indicator', () => {
    assert.doesNotMatch(
        bladeSource,
        /Verifica della sessione|Checking session|Prepariamo il tuo spazio/,
    );
    assert.match(bladeSource, /Caricamento…/);
    assert.match(bladeSource, /startup-appear 0s 180ms forwards/);
    assert.match(bladeSource, /prefers-reduced-motion: reduce/);
    assert.match(bladeSource, /class="startup-label"/);
});
