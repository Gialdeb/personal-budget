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
    assert.match(adminLayoutSource, /summaryKey\(item\.title\)/);
    assert.match(adminLayoutSource, /index\(\{\s*query:/);
});

test('admin layout exposes a clear contextual help navigation entry', () => {
    assert.match(adminLayoutSource, /admin\.sections\.contextualHelp/);
    assert.match(adminLayoutSource, /contextualHelpIndex/);
});
