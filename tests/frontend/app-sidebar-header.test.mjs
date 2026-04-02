import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const appSidebarHeaderSource = readFileSync(
    new URL(
        '../../resources/js/components/AppSidebarHeader.vue',
        import.meta.url,
    ),
    'utf8',
);

test('mobile app header exposes an admin launcher shortcut for admin users', () => {
    assert.match(
        appSidebarHeaderSource,
        /const mobileAdminLauncherHref = computed/,
    );
    assert.match(
        appSidebarHeaderSource,
        /adminIndex\(\{\s*query:\s*\{\s*mobile:\s*'launcher'/,
    );
    assert.match(
        appSidebarHeaderSource,
        /:admin-href="mobileAdminLauncherHref\.url"/,
    );
});
