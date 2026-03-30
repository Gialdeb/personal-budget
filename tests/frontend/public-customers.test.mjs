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
const customersSource = readFileSync(
    new URL('../../resources/js/pages/Customers.vue', import.meta.url),
    'utf8',
);
const customersContentSource = readFileSync(
    new URL('../../resources/js/i18n/customers-content.ts', import.meta.url),
    'utf8',
);

test('public customers route is registered', () => {
    assert.match(routesSource, /Route::inertia\('\/customers', 'Customers'/);
});

test('customers page remains public even without a direct navbar item', () => {
    assert.doesNotMatch(headerSource, /href: '\/customers'/);
    assert.doesNotMatch(headerSource, /auth\.welcome\.nav\.customers/);
    assert.match(customersSource, /current-page="customers"/);
});

test('customers page includes the main honest sections', () => {
    assert.match(customersSource, /content\.audience/);
    assert.match(customersSource, /content\.scenarios/);
    assert.match(customersSource, /content\.useful/);
    assert.match(customersSource, /content\.beta/);
    assert.match(customersContentSource, /Al posto di recensioni finte/);
    assert.match(customersContentSource, /Instead of fake testimonials/);
});

test('customers page includes coherent calls to action', () => {
    assert.match(customersSource, /content\.cta/);
    assert.match(customersSource, /register\(\)/);
    assert.match(customersSource, /pricing\(\)/);
    assert.match(customersSource, /aboutMe\(\)/);
});

test('customers page does not rely on fake reviews or star ratings', () => {
    assert.doesNotMatch(customersContentSource, /review by/i);
    assert.doesNotMatch(customersContentSource, /quote from/i);
    assert.doesNotMatch(customersContentSource, /5 stars/i);
    assert.doesNotMatch(customersContentSource, /★★★★★/);
});
