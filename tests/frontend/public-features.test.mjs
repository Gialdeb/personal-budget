import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const routesSource = readFileSync(
    new URL('../../routes/web.php', import.meta.url),
    'utf8',
);
const welcomeSource = readFileSync(
    new URL('../../resources/js/pages/Welcome.vue', import.meta.url),
    'utf8',
);
const featuresSource = readFileSync(
    new URL('../../resources/js/pages/Features.vue', import.meta.url),
    'utf8',
);
const headerSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSiteHeader.vue',
        import.meta.url,
    ),
    'utf8',
);
const showcaseSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicFeatureShowcase.vue',
        import.meta.url,
    ),
    'utf8',
);
const assetResolverSource = readFileSync(
    new URL('../../resources/js/lib/public-feature-assets.ts', import.meta.url),
    'utf8',
);
const contentSource = readFileSync(
    new URL('../../resources/js/i18n/features-content.ts', import.meta.url),
    'utf8',
);

test('public features route is registered', () => {
    assert.match(routesSource, /Route::inertia\('\/features', 'Features'/);
});

test('public navbar links to the english features path', () => {
    assert.match(headerSource, /href: '\/features'/);
    assert.match(welcomeSource, /PublicSiteHeader/);
    assert.match(featuresSource, /current-page="features"/);
});

test('features page renders the main product sections', () => {
    assert.match(featuresSource, /resolvePublicFeatureImage/);
    assert.match(featuresSource, /content\.importer\.title/);
    assert.match(featuresSource, /content\.importer\.bullets/);
    assert.match(contentSource, /title: 'Dashboard'/);
    assert.match(contentSource, /title: 'Transactions'/);
    assert.match(contentSource, /title: 'Budget planning'/);
    assert.match(contentSource, /title: 'Shared accounts'/);
    assert.match(contentSource, /title: 'Recurring entries'/);
    assert.match(contentSource, /title: 'Credit cards'/);
    assert.match(contentSource, /Puoi lavorare in modo molto simile a Excel/);
    assert.match(showcaseSource, /highlights: string\[]/);
});

test('localized feature asset resolver switches by locale with shared filenames', () => {
    assert.ok(
        assetResolverSource.includes(
            '/images/features/${normalizeLocale(locale)}/${name}.svg',
        ),
    );
});

test('features content is available in italian and english', () => {
    assert.match(contentSource, /Le funzionalità principali di Soamco Budget/);
    assert.match(contentSource, /The main Soamco Budget features/);
});
