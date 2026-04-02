import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const seoHeadSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSeoHead.vue',
        import.meta.url,
    ),
    'utf8',
);

const publicPageSources = [
    '../../resources/js/pages/Welcome.vue',
    '../../resources/js/pages/Features.vue',
    '../../resources/js/pages/Pricing.vue',
    '../../resources/js/pages/AboutMe.vue',
    '../../resources/js/pages/Customers.vue',
    '../../resources/js/pages/DownloadApp.vue',
    '../../resources/js/pages/changelog/Index.vue',
    '../../resources/js/pages/changelog/Show.vue',
    '../../resources/js/layouts/public/PublicLegalLayout.vue',
].map((path) => readFileSync(new URL(path, import.meta.url), 'utf8'));

const bladeSource = readFileSync(
    new URL('../../resources/views/app.blade.php', import.meta.url),
    'utf8',
);

test('public seo head renders canonical, robots and json-ld tags', () => {
    assert.match(seoHeadSource, /head-key="description"/);
    assert.match(seoHeadSource, /meta head-key="robots"/);
    assert.match(seoHeadSource, /rel="canonical"/);
    assert.match(seoHeadSource, /application\/ld\+json/);
    assert.match(seoHeadSource, /twitter:description/);
});

test('public pages use the shared public seo head component', () => {
    for (const source of publicPageSources) {
        assert.match(source, /PublicSeoHead/);
    }
});

test('app blade renders public seo fallback tags for the initial public response', () => {
    assert.match(bladeSource, /publicSeo/);
    assert.match(bladeSource, /meta name="description"/);
    assert.match(bladeSource, /meta name="robots"/);
    assert.match(bladeSource, /rel="canonical"/);
    assert.match(bladeSource, /application\/ld\+json/);
});
