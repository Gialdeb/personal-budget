import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const routesSource = readFileSync(
    new URL('../../routes/web.php', import.meta.url),
    'utf8',
);
const dashboardRoutesSource = readFileSync(
    new URL('../../routes/dashboard.php', import.meta.url),
    'utf8',
);
const pricingSource = readFileSync(
    new URL('../../resources/js/pages/Pricing.vue', import.meta.url),
    'utf8',
);
const headerSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSiteHeader.vue',
        import.meta.url,
    ),
    'utf8',
);
const pricingContentSource = readFileSync(
    new URL('../../resources/js/i18n/pricing-content.ts', import.meta.url),
    'utf8',
);

test('public pricing route is registered', () => {
    assert.match(routesSource, /Route::inertia\('\/pricing', 'Pricing'/);
});

test('public navbar links pricing to the english path', () => {
    assert.match(headerSource, /href: '\/pricing'/);
    assert.match(pricingSource, /current-page="pricing"/);
});

test('pricing page clearly communicates free product and optional donations', () => {
    assert.match(pricingSource, /pricingContent/);
    assert.match(pricingContentSource, /Sempre gratuito/);
    assert.match(pricingContentSource, /donazione libera/);
    assert.match(pricingContentSource, /Donations are optional/);
});

test('pricing page includes FAQ and support sections', () => {
    assert.match(pricingSource, /content\.support/);
    assert.match(pricingSource, /content\.faq/);
    assert.match(pricingSource, /Visione sostenibile/);
    assert.match(pricingSource, /HeartHandshake/);
    assert.match(pricingSource, /@\/routes\/support/);
    assert.match(pricingSource, /content\.support\.secondaryLabel/);
    assert.match(
        dashboardRoutesSource,
        /Route::inertia\('support', 'Support'\)/,
    );
});
