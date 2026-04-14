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
const profileSource = readFileSync(
    new URL('../../resources/js/pages/settings/Profile.vue', import.meta.url),
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
        /Route::get\('support', fn \(Request \$request\) => redirect\(\)->route\('support\.index'/,
    );
});

test('pricing donation CTA routes guests to auth and users to profile support', () => {
    assert.match(
        pricingSource,
        /import \{ features, login, register } from '@\/routes'/,
    );
    assert.match(
        pricingSource,
        /import \{ edit as profileEdit } from '@\/routes\/profile'/,
    );
    assert.match(pricingSource, /profileEdit\(\)\.url}#support/);
    assert.match(
        pricingSource,
        /canRegister\.value \? register\(\) : login\(\)/,
    );
    assert.match(pricingSource, /:href="donationTarget"/);
    assert.match(
        pricingSource,
        /trackDonationClick\(\s*'pricing_support_primary',\s*donationTargetUrl,/,
    );
    assert.match(
        profileSource,
        /<section\s+id="support"[\s\S]*settings\.profile\.support\.title/,
    );
});
