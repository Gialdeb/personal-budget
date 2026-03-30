import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const routesSource = readFileSync(
    new URL('../../routes/web.php', import.meta.url),
    'utf8',
);
const headerSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSiteHeader.vue',
        import.meta.url,
    ),
    'utf8',
);
const footerSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSiteFooter.vue',
        import.meta.url,
    ),
    'utf8',
);
const pageSource = readFileSync(
    new URL('../../resources/js/pages/DownloadApp.vue', import.meta.url),
    'utf8',
);
const contentSource = readFileSync(
    new URL('../../resources/js/i18n/download-app-content.ts', import.meta.url),
    'utf8',
);
const assetResolverSource = readFileSync(
    new URL('../../resources/js/lib/public-feature-assets.ts', import.meta.url),
    'utf8',
);

test('public download app route is registered', () => {
    assert.match(
        routesSource,
        /Route::inertia\('\/download-app', 'DownloadApp'/,
    );
});

test('download app page remains public even without a direct navbar item', () => {
    assert.doesNotMatch(headerSource, /href: '\/download-app'/);
    assert.doesNotMatch(headerSource, /auth\.welcome\.nav\.downloadApp/);
    assert.match(footerSource, /href: '\/download-app'/);
    assert.match(pageSource, /current-page="download-app"/);
});

test('download app page includes Android and iPhone\/iPad sections', () => {
    assert.match(pageSource, /content\.android/);
    assert.match(pageSource, /content\.ios/);
    assert.match(contentSource, /Come installarla su Android/);
    assert.match(contentSource, /How to install it on iPhone or iPad/);
});

test('download app page includes coherent calls to action', () => {
    assert.match(pageSource, /content\.cta/);
    assert.match(pageSource, /usePwa/);
    assert.match(pageSource, /launchInstall/);
    assert.match(pageSource, /@click="handleInstallClick"/);
    assert.match(pageSource, /CTA clicked on \/download-app/);
    assert.match(pageSource, /event\.isTrusted/);
    assert.match(pageSource, /installState === 'installed'/);
    assert.match(pageSource, /window\.location\.hash = installHelpHref\.value/);
    assert.match(pageSource, /content\.value\.cta\.iosHint/);
    assert.match(pageSource, /content\.value\.cta\.dismissedHint/);
    assert.match(pageSource, /content\.value\.cta\.unavailableHint/);
    assert.match(pageSource, /installDiagnostic/);
    assert.match(pageSource, /v-if="isDev"/);
    assert.match(pageSource, /register\(\)/);
    assert.match(pageSource, /features\(\)/);
    assert.match(pageSource, /pricing\(\)/);
});

test('download app localized image resolver switches by locale', () => {
    assert.ok(
        assetResolverSource.includes(
            '/images/download-app/${normalizeLocale(locale)}/${name}.svg',
        ),
    );
    assert.match(pageSource, /resolvePublicDownloadImage/);
});
