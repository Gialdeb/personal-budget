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

const transactionsMonthNavigatorSource = readFileSync(
    new URL(
        '../../resources/js/components/TransactionsMonthNavigator.vue',
        import.meta.url,
    ),
    'utf8',
);
const navUserSource = readFileSync(
    new URL('../../resources/js/components/NavUser.vue', import.meta.url),
    'utf8',
);

test('sidebar theming keeps month selection and mobile user actions inside sidebar tokens', () => {
    assert.match(
        transactionsMonthNavigatorSource,
        /bg-sidebar-accent text-sidebar-accent-foreground shadow-sm ring-1 ring-sidebar-border/,
    );
    assert.match(
        transactionsMonthNavigatorSource,
        /border-sidebar-border\/70 bg-sidebar-accent\/85 text-sidebar-accent-foreground/,
    );
    assert.match(
        navUserSource,
        /text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground/,
    );
});
