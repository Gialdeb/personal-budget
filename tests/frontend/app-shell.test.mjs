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
const themePreferenceControlSource = readFileSync(
    new URL(
        '../../resources/js/components/ThemePreferenceControl.vue',
        import.meta.url,
    ),
    'utf8',
);
const navUserSource = readFileSync(
    new URL('../../resources/js/components/NavUser.vue', import.meta.url),
    'utf8',
);
const appSource = readFileSync(
    new URL('../../resources/js/app.ts', import.meta.url),
    'utf8',
);
const appMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/app.ts', import.meta.url),
    'utf8',
);
const appearanceComposableSource = readFileSync(
    new URL('../../resources/js/composables/useAppearance.ts', import.meta.url),
    'utf8',
);
const banksPageSource = readFileSync(
    new URL('../../resources/js/pages/settings/Banks.vue', import.meta.url),
    'utf8',
);
const accountsPageSource = readFileSync(
    new URL('../../resources/js/pages/settings/Accounts.vue', import.meta.url),
    'utf8',
);
const recurringPageSource = readFileSync(
    new URL(
        '../../resources/js/pages/transactions/recurring/Index.vue',
        import.meta.url,
    ),
    'utf8',
);
const categoriesPageSource = readFileSync(
    new URL(
        '../../resources/js/pages/settings/Categories.vue',
        import.meta.url,
    ),
    'utf8',
);
const sharedCategoriesPageSource = readFileSync(
    new URL(
        '../../resources/js/pages/settings/SharedCategories.vue',
        import.meta.url,
    ),
    'utf8',
);
const trackedItemsPageSource = readFileSync(
    new URL(
        '../../resources/js/pages/settings/TrackedItems.vue',
        import.meta.url,
    ),
    'utf8',
);
const transactionsPageSource = readFileSync(
    new URL('../../resources/js/pages/transactions/Show.vue', import.meta.url),
    'utf8',
);

test('global shell header exposes quick actions and status chips', () => {
    assert.match(headerSource, /app\.shell\.actions\.newTransaction/);
    assert.match(headerSource, /app\.shell\.actions\.newRecurringEntry/);
    assert.match(headerSource, /app\.shell\.actions\.newBank/);
    assert.match(
        headerSource,
        /recurringEntries\(\{\s*query:\s*\{\s*create:\s*'1'/,
    );
    assert.match(headerSource, /banks\(\{\s*query:\s*\{\s*create:\s*'1'/);
    assert.match(headerSource, /app\.shell\.statusBaseCurrency/);
    assert.match(headerSource, /app\.shell\.statusFormatLocale/);
    assert.doesNotMatch(headerSource, /quickActions\.slice\(0,\s*3\)/);
});

test('quick create actions open the destination forms from query state', () => {
    assert.match(banksPageSource, /consumeCreateBankQuery/);
    assert.match(banksPageSource, /url\.searchParams\.get\('create'\)/);
    assert.match(banksPageSource, /openCreateBank\(\)/);
    assert.match(accountsPageSource, /consumeCreateAccountQuery/);
    assert.match(accountsPageSource, /url\.searchParams\.get\('create'\)/);
    assert.match(accountsPageSource, /openCreateAccount\(\)/);
    assert.match(recurringPageSource, /consumeCreateRecurringEntryQuery/);
    assert.match(recurringPageSource, /url\.searchParams\.get\('create'\)/);
    assert.match(recurringPageSource, /openCreateForm\(\)/);
    assert.match(categoriesPageSource, /consumeCreateCategoryQuery/);
    assert.match(categoriesPageSource, /url\.searchParams\.get\('create'\)/);
    assert.match(categoriesPageSource, /openCreateCategory\(\)/);
    assert.match(
        sharedCategoriesPageSource,
        /consumeCreateSharedCategoryQuery/,
    );
    assert.match(
        sharedCategoriesPageSource,
        /url\.searchParams\.get\('create'\)/,
    );
    assert.match(sharedCategoriesPageSource, /openCreateCategory\(\)/);
    assert.match(trackedItemsPageSource, /consumeCreateTrackedItemQuery/);
    assert.match(trackedItemsPageSource, /url\.searchParams\.get\('create'\)/);
    assert.match(trackedItemsPageSource, /openCreateTrackedItem\(\)/);
    assert.match(transactionsPageSource, /consumeCreateTransactionQuery/);
    assert.match(transactionsPageSource, /url\.searchParams\.get\('create'\)/);
    assert.match(transactionsPageSource, /openCreate\(\)/);
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
    assert.match(userMenuSource, /settingsIndex\(\)/);
    assert.match(userMenuSource, /ThemePreferenceControl/);
});

test('sidebar user area exposes a mobile-safe inline menu instead of nesting a dropdown inside the mobile sidebar sheet', () => {
    assert.match(navUserSource, /<div v-if="isMobile" class="space-y-3">/);
    assert.match(
        navUserSource,
        /<UserInfo :user="user" :show-email="true" :compact="true" \/>/,
    );
    assert.match(navUserSource, /setOpenMobile\(false\)/);
    assert.match(navUserSource, /settingsIndex\(\)/);
    assert.match(
        navUserSource,
        /adminIndex\(\{\s*query:\s*\{\s*mobile:\s*'launcher'/,
    );
    assert.match(navUserSource, /logout\(\)/);
    assert.match(
        navUserSource,
        /<ThemePreferenceControl[\s\S]*variant="inline"[\s\S]*tone="sidebar"/,
    );
});

test('user menu uses a compact avatar block in mobile-safe contexts', () => {
    assert.match(
        userMenuSource,
        /<UserInfo :user="user" :show-email="true" :compact="true" \/>/,
    );
});

test('theme preference control reuses the shared appearance composable and preserves system resolution', () => {
    assert.match(themePreferenceControlSource, /useAppearance\(\)/);
    assert.match(themePreferenceControlSource, /DropdownMenuRadioGroup/);
    assert.match(themePreferenceControlSource, /theme-switcher-mobile/);
    assert.match(themePreferenceControlSource, /value: 'light'/);
    assert.match(themePreferenceControlSource, /value: 'dark'/);
    assert.match(themePreferenceControlSource, /value: 'system'/);
    assert.match(
        appearanceComposableSource,
        /localStorage\.setItem\('appearance', value\)/,
    );
    assert.match(
        appearanceComposableSource,
        /setCookie\('appearance', value\)/,
    );
    assert.match(
        appearanceComposableSource,
        /updateTheme\(savedAppearance \|\| 'system'\)/,
    );
    assert.match(
        appearanceComposableSource,
        /window\.matchMedia\('\(prefers-color-scheme: dark\)'\)/,
    );
});

test('theme labels are localized for the shared shell controls', () => {
    assert.match(appMessagesSource, /label: 'Tema'/);
    assert.match(appMessagesSource, /ariaLabel: 'Selettore tema applicazione'/);
    assert.match(appMessagesSource, /label: 'Theme'/);
    assert.match(appMessagesSource, /ariaLabel: 'Application theme selector'/);
});

test('inertia page resolver includes root and nested page components', () => {
    assert.match(
        appSource,
        /import\.meta\.glob<DefineComponent>\('\.\/pages\/\*\.vue'\)/,
    );
    assert.match(
        appSource,
        /import\.meta\.glob<DefineComponent>\('\.\/pages\/\*\*\/\*\.vue'\)/,
    );
});
