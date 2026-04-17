import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const appCssSource = readFileSync(
    new URL('../../resources/css/app.css', import.meta.url),
    'utf8',
);
const appSource = readFileSync(
    new URL('../../resources/js/app.ts', import.meta.url),
    'utf8',
);
const guardsSource = readFileSync(
    new URL(
        '../../resources/js/composables/useAppTouchGuards.ts',
        import.meta.url,
    ),
    'utf8',
);
const mobileBottomNavSource = readFileSync(
    new URL(
        '../../resources/js/components/MobileBottomNav.vue',
        import.meta.url,
    ),
    'utf8',
);
const navMainSource = readFileSync(
    new URL('../../resources/js/components/NavMain.vue', import.meta.url),
    'utf8',
);
const navUserSource = readFileSync(
    new URL('../../resources/js/components/NavUser.vue', import.meta.url),
    'utf8',
);
const userMenuSource = readFileSync(
    new URL('../../resources/js/components/UserMenuContent.vue', import.meta.url),
    'utf8',
);
const sidebarSource = readFileSync(
    new URL('../../resources/js/components/AppSidebar.vue', import.meta.url),
    'utf8',
);
const buttonVariantsSource = readFileSync(
    new URL(
        '../../resources/js/components/ui/button/index.ts',
        import.meta.url,
    ),
    'utf8',
);
const sidebarVariantsSource = readFileSync(
    new URL(
        '../../resources/js/components/ui/sidebar/index.ts',
        import.meta.url,
    ),
    'utf8',
);
const dropdownItemSource = readFileSync(
    new URL(
        '../../resources/js/components/ui/dropdown-menu/DropdownMenuItem.vue',
        import.meta.url,
    ),
    'utf8',
);
const selectTriggerSource = readFileSync(
    new URL(
        '../../resources/js/components/ui/select/SelectTrigger.vue',
        import.meta.url,
    ),
    'utf8',
);

test('touch guard CSS disables Safari callout on marked interactive targets and descendants only', () => {
    assert.match(appCssSource, /\.app-touch-interactive \{/);
    assert.match(appCssSource, /touch-action: manipulation/);
    assert.match(appCssSource, /-webkit-touch-callout: none/);
    assert.match(appCssSource, /-webkit-user-drag: none/);
    assert.match(appCssSource, /[[]data-app-touch-target]/);
    assert.match(appCssSource, /\.app-touch-interactive \*/);
    assert.match(appCssSource, /-webkit-text-size-adjust: 100%/);
    assert.match(appCssSource, /\.app-touch-selectable \{/);
    assert.match(appCssSource, /-webkit-touch-callout: default/);
    assert.doesNotMatch(appCssSource, /body,\s*html\s*\{[^}]*-webkit-touch-callout: none/s);
});

test('runtime guard prevents context menus and drag previews for marked app controls', () => {
    assert.match(appSource, /initializeAppTouchGuards\(\)/);
    assert.match(guardsSource, /TOUCH_TARGET_SELECTOR/);
    assert.match(guardsSource, /\.app-touch-interactive, [[]data-app-touch-target]/);
    assert.match(guardsSource, /event\.target\.closest\(TOUCH_TARGET_SELECTOR\)/);
    assert.match(guardsSource, /document\.addEventListener\('contextmenu'/);
    assert.match(guardsSource, /document\.addEventListener\('dragstart'/);
    assert.match(guardsSource, /event\.preventDefault\(\)/);
    assert.match(guardsSource, /__soamcoBudgetAppTouchGuardsInitialized/);
});

test('mobile shell navigation marks the real tappable links and buttons', () => {
    assert.match(sidebarSource, /data-app-touch-target/);
    assert.match(sidebarSource, /router\.visit\(dashboard\(\)\.url\)/);
    assert.match(navMainSource, /<button[\s\S]*data-app-touch-target/);
    assert.match(navMainSource, /router\.visit\(routeUrl\(item\.href\)\)/);
    assert.match(navUserSource, /data-test="sidebar-menu-button"[\s\S]*data-app-touch-target/);
    assert.match(navUserSource, /visitMobileMenuItem\(settingsIndex\(\)\.url\)/);
    assert.match(userMenuSource, /class="app-touch-interactive block w-full cursor-pointer"/);
    assert.match(mobileBottomNavSource, /visitShellTarget\(dashboard\(\)\.url\)/);
    assert.match(mobileBottomNavSource, /visitShellTarget\(budgetPlanning\(\)\.url\)/);
    assert.match(mobileBottomNavSource, /visitShellTarget\(settingsHref\.url\)/);
    assert.match(mobileBottomNavSource, /visitShellTarget\(adminLauncherHref\.url\)/);
    assert.match(mobileBottomNavSource, /visitShellTarget\(transactionsHref\.url\)/);
    assert.match(mobileBottomNavSource, /visitShellTarget\(recurringCreateHref\.url\)/);
    assert.doesNotMatch(mobileBottomNavSource, /<Link/);
    assert.doesNotMatch(navMainSource, /<Link/);
});

test('shared primitives used by shell controls carry the touch guard class', () => {
    assert.match(buttonVariantsSource, /app-touch-interactive/);
    assert.match(sidebarVariantsSource, /app-touch-interactive/);
    assert.match(dropdownItemSource, /app-touch-interactive/);
    assert.match(selectTriggerSource, /app-touch-interactive/);
});
