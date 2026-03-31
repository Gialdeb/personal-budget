import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    HEADER_INFO_EXPANDED_STORAGE_KEY,
    persistHeaderInfoExpanded,
    readHeaderInfoExpanded,
} from '../../resources/js/lib/header-preferences.js';

const headerSource = readFileSync(
    new URL(
        '../../resources/js/components/AppSidebarHeader.vue',
        import.meta.url,
    ),
    'utf8',
);

const footerSource = readFileSync(
    new URL(
        '../../resources/js/components/AppShellFooter.vue',
        import.meta.url,
    ),
    'utf8',
);
const layoutSource = readFileSync(
    new URL(
        '../../resources/js/layouts/app/AppSidebarLayout.vue',
        import.meta.url,
    ),
    'utf8',
);
const userMenuSource = readFileSync(
    new URL(
        '../../resources/js/components/UserMenuContent.vue',
        import.meta.url,
    ),
    'utf8',
);
const appSource = readFileSync(
    new URL('../../resources/js/app.ts', import.meta.url),
    'utf8',
);

test('global shell header exposes quick actions and status chips', () => {
    assert.match(headerSource, /app\.shell\.actions\.newTransaction/);
    assert.match(headerSource, /app\.shell\.statusBaseCurrency/);
    assert.match(headerSource, /app\.shell\.statusFormatLocale/);
    assert.doesNotMatch(headerSource, /quickActions\.slice\(0,\s*3\)/);
});

test('global shell header exposes notifications and user area controls', () => {
    assert.match(headerSource, /app\.shell\.notifications\.title/);
    assert.match(headerSource, /app\.shell\.notifications\.open/);
    assert.match(headerSource, /useNotificationInboxRealtime/);
    assert.match(headerSource, /unreadPreviewNotifications/);
    assert.match(headerSource, /TransitionGroup/);
    assert.doesNotMatch(headerSource, /setInterval\(/);
    assert.doesNotMatch(headerSource, /refreshNotifications\(/);
    assert.doesNotMatch(
        headerSource,
        /fetch\(notificationInbox\.value\.preview_url/,
    );
    assert.match(headerSource, /app\.shell\.notifications\.markAsRead/);
    assert.match(headerSource, /app\.shell\.notifications\.viewAll/);
    assert.match(headerSource, /app\.shell\.userMenu\.account/);
    assert.match(
        headerSource,
        /AvatarFallback\s+class="rounded-2xl bg-gradient-to-br/,
    );
    assert.match(headerSource, /UserMenuContent/);
});

test('global shell header exposes collapsible page info controls', () => {
    assert.match(headerSource, /readHeaderInfoExpanded/);
    assert.match(headerSource, /persistHeaderInfoExpanded/);
    assert.match(headerSource, /app\.shell\.expandInfo/);
    assert.match(headerSource, /app\.shell\.collapseInfo/);
    assert.match(headerSource, /:aria-expanded="isInfoExpanded"/);
});

test('header info preference persists in local storage', () => {
    const storage = new Map();

    global.window = {
        localStorage: {
            getItem(key) {
                return storage.has(key) ? storage.get(key) : null;
            },
            setItem(key, value) {
                storage.set(key, value);
            },
        },
    };

    assert.equal(readHeaderInfoExpanded(), true);

    persistHeaderInfoExpanded(false);
    assert.equal(storage.get(HEADER_INFO_EXPANDED_STORAGE_KEY), 'false');
    assert.equal(readHeaderInfoExpanded(), false);

    persistHeaderInfoExpanded(true);
    assert.equal(readHeaderInfoExpanded(), true);

    delete global.window;
});

test('footer component still exposes application version metadata when reused later', () => {
    assert.match(footerSource, /page\.props\.app/);
    assert.match(footerSource, /app\.shell\.footerVersion/);
    assert.match(footerSource, /changelog\.latest_release_label/);
    assert.match(footerSource, /app\.userMenu\.version\.changelog/);
    assert.match(footerSource, /app\.shell\.footerLinks\.settings/);
});

test('shared app layout no longer renders the global footer', () => {
    assert.doesNotMatch(layoutSource, /AppShellFooter/);
});

test('user menu exposes application version metadata and changelog link', () => {
    assert.match(userMenuSource, /data-testid="user_menu_version"/);
    assert.match(userMenuSource, /changelog\.latest_release_label/);
    assert.match(userMenuSource, /changelog\.latest_release_url/);
    assert.match(userMenuSource, /app\.userMenu\.version\.changelog/);
});

test('inertia page resolver includes root and nested page components', () => {
    assert.match(appSource, /import\.meta\.glob<DefineComponent>\('\.\/pages\/\*\.vue'\)/);
    assert.match(
        appSource,
        /import\.meta\.glob<DefineComponent>\('\.\/pages\/\*\*\/\*\.vue'\)/,
    );
});
