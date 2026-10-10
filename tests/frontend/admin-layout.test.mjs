import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const adminLayoutSource = readFileSync(
    new URL('../../resources/js/layouts/admin/Layout.vue', import.meta.url),
    'utf8',
);

test('admin layout exposes a mobile launcher and a dedicated page header with back navigation', () => {
    assert.match(adminLayoutSource, /data-test="admin-mobile-launcher"/);
    assert.match(adminLayoutSource, /data-test="admin-mobile-page-header"/);
    assert.match(adminLayoutSource, /class="mt-4 grid grid-cols-2 gap-3"/);
    assert.match(adminLayoutSource, /mobile:\s*'launcher'/);
    assert.match(adminLayoutSource, /ArrowLeft/);
    assert.match(
        adminLayoutSource,
        /<aside class="hidden space-y-4 md:block">/,
    );
    assert.match(adminLayoutSource, /navigationSummary\(item\.title\)/);
    assert.match(adminLayoutSource, /t\(summaryKey\(title\)\)/);
    assert.match(adminLayoutSource, /index\(\{\s*query:/);
});

test('admin layout exposes a clear contextual help navigation entry', () => {
    assert.match(adminLayoutSource, /admin\.sections\.contextualHelp/);
    assert.match(adminLayoutSource, /contextualHelpIndex/);
});

test('system logs have one admin-only navigation entry and use full-page navigation on desktop and mobile', () => {
    assert.match(adminLayoutSource, /page\.props\.auth\.user\.is_admin/);
    assert.match(adminLayoutSource, /title: ui\('Log di sistema'\)/);
    assert.match(adminLayoutSource, /href: logViewer\.url\(\)/);
    assert.equal(
        (adminLayoutSource.match(/item\.fullPage \? 'a' : Link/g) ?? []).length,
        2,
    );
    assert.equal(
        (adminLayoutSource.match(/href: logViewer\.url\(\)/g) ?? []).length,
        1,
    );
    assert.doesNotMatch(adminLayoutSource, /:href="logViewer\.url\(\)"/);
});
