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
const indexSource = readFileSync(
    new URL('../../resources/js/pages/changelog/Index.vue', import.meta.url),
    'utf8',
);
const showSource = readFileSync(
    new URL('../../resources/js/pages/changelog/Show.vue', import.meta.url),
    'utf8',
);
const feedSource = readFileSync(
    new URL('../../resources/js/lib/public-changelog.ts', import.meta.url),
    'utf8',
);
const richTextRendererSource = readFileSync(
    new URL(
        '../../resources/js/components/public/changelog/PublicRichTextRenderer.vue',
        import.meta.url,
    ),
    'utf8',
);
const sanitizeSource = readFileSync(
    new URL('../../resources/js/lib/public-rich-text.ts', import.meta.url),
    'utf8',
);
const cardSource = readFileSync(
    new URL(
        '../../resources/js/components/public/changelog/PublicChangelogReleaseCard.vue',
        import.meta.url,
    ),
    'utf8',
);
const contentSource = readFileSync(
    new URL('../../resources/js/i18n/changelog-content.ts', import.meta.url),
    'utf8',
);

test('public changelog routes are registered for index detail and feed', () => {
    assert.match(
        routesSource,
        /Route::inertia\('\/changelog', 'changelog\/Index'/,
    );
    assert.match(routesSource, /inertia\('changelog\/Show'/);
    assert.match(routesSource, /Route::prefix\('changelog\/releases'\)/);
});

test('public navbar and footer link to changelog', () => {
    assert.match(headerSource, /auth\.welcome\.nav\.changelog/);
    assert.match(headerSource, /changelogIndex\(\)/);
    assert.match(footerSource, /changelogIndex\(\)/);
});

test('changelog index consumes backend feed and handles loading and empty states', () => {
    assert.match(indexSource, /fetchPublicChangelogIndex/);
    assert.match(indexSource, /isLoading/);
    assert.match(indexSource, /emptyTitle/);
    assert.match(indexSource, /PublicChangelogReleaseCard/);
    assert.match(indexSource, /current-page="changelog"/);
    assert.doesNotMatch(indexSource, /0\.10\.4-beta/);
});

test('changelog detail consumes backend feed and renders release sections', () => {
    assert.match(showSource, /fetchPublicChangelogRelease/);
    assert.match(showSource, /PublicChangelogSection/);
    assert.match(showSource, /PublicRichTextRenderer/);
    assert.match(showSource, /notFoundTitle/);
    assert.match(showSource, /content\.detail\.backLabel/);
    assert.doesNotMatch(showSource, /0\.10\.4-beta/);
});

test('public changelog fetch helpers use backend feed endpoints with locale', () => {
    assert.match(feedSource, /changelogFeedIndex\.url/);
    assert.match(feedSource, /changelogFeedShow\.url/);
    assert.match(feedSource, /locale/);
});

test('rich text rendering sanitizes backend html before display', () => {
    assert.match(richTextRendererSource, /sanitizePublicRichText/);
    assert.match(sanitizeSource, /script\|style\|iframe\|object\|embed/);
    assert.match(sanitizeSource, /javascript:/);
    assert.match(sanitizeSource, /target="_blank" rel="noopener noreferrer"/);
});

test('changelog content is localized in italian and english', () => {
    assert.match(contentSource, /Novità di Soamco Budget/);
    assert.match(contentSource, /What is new in Soamco Budget/);
    assert.match(cardSource, /changelogContent\.it/);
});
