import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const mobileBottomNavSource = readFileSync(
    new URL(
        '../../resources/js/components/MobileBottomNav.vue',
        import.meta.url,
    ),
    'utf8',
);

test('mobile bottom nav uses translated labels instead of hardcoded navigation names', () => {
    assert.match(mobileBottomNavSource, /t\('nav\.dashboard'\)/);
    assert.match(mobileBottomNavSource, /t\('nav\.transactions'\)/);
    assert.match(mobileBottomNavSource, /t\('nav\.planning'\)/);
    assert.match(mobileBottomNavSource, /t\('nav\.reports'\)/);
    assert.match(mobileBottomNavSource, /t\('nav\.recurring'\)/);
    assert.match(mobileBottomNavSource, /t\('app\.userMenu\.settings'\)/);
    assert.match(mobileBottomNavSource, /dashboard:\s*isItalian \? 'Panor\.'/);
    assert.match(
        mobileBottomNavSource,
        /transactions:\s*isItalian \? 'Transaz\.'/,
    );
    assert.match(mobileBottomNavSource, /planning:\s*isItalian \? 'Prevent\.'/);
    assert.match(
        mobileBottomNavSource,
        /const settingsHref = computed\(\(\) => settingsIndex\(\)\)/,
    );
    assert.match(
        mobileBottomNavSource,
        /const isPrimaryActionsOpen = ref\(false\)/,
    );
    assert.match(
        mobileBottomNavSource,
        /currentSection\.value === 'dashboard'/,
    );
    assert.match(mobileBottomNavSource, /currentSection\.value === 'accounts'/);
    assert.match(mobileBottomNavSource, /currentSection\.value === 'banks'/);
    assert.match(mobileBottomNavSource, /transactionsCreateHref/);
    assert.match(mobileBottomNavSource, /recurringCreateHref/);
    assert.match(mobileBottomNavSource, /accountsCreateHref/);
    assert.match(mobileBottomNavSource, /banksCreateHref/);
    assert.match(mobileBottomNavSource, /categoriesCreateHref/);
    assert.match(mobileBottomNavSource, /sharedCategoriesCreateHref/);
    assert.match(mobileBottomNavSource, /trackedItemsCreateHref/);
    assert.match(mobileBottomNavSource, /budgetPlanning\(\)\.url/);
    assert.match(mobileBottomNavSource, /reports\(\)\.url/);
    assert.match(
        mobileBottomNavSource,
        /class="app-touch-interactive flex min-w-0 flex-1 flex-col items-center/,
    );
    assert.match(
        mobileBottomNavSource,
        /class="app-touch-interactive flex items-center justify-between rounded-3xl/,
    );
    assert.match(
        mobileBottomNavSource,
        /currentPath\.value\.startsWith\('\/settings\/categories'\)/,
    );
    assert.match(
        mobileBottomNavSource,
        /currentPath\.value\.startsWith\('\/settings\/shared-categories'\)/,
    );
    assert.match(
        mobileBottomNavSource,
        /currentPath\.value\.startsWith\('\/settings\/tracked-items'\)/,
    );
    assert.match(
        mobileBottomNavSource,
        /visitShellTarget\(budgetPlanning\(\)\.url\)/,
    );
    assert.doesNotMatch(mobileBottomNavSource, /<span>Budget<\/span>/);
});

test('mobile bottom nav shows reports and hides planning when reports are enabled', () => {
    assert.match(
        mobileBottomNavSource,
        /const reportsEnabled = computed\(\s*\(\) => page\.props\.features\?\.reports_enabled === true,\s*\)/,
    );
    assert.match(mobileBottomNavSource, /<button\s+v-if="reportsEnabled"/);
    assert.match(
        mobileBottomNavSource,
        /@click="visitShellTarget\(reports\(\)\.url\)"/,
    );
    assert.match(
        mobileBottomNavSource,
        /<span>\{\{ mobileNavLabels\.reports }}<\/span>/,
    );
    assert.match(mobileBottomNavSource, /isSectionActive\('reports'\)/);
    assert.match(
        mobileBottomNavSource,
        /<button\s+v-if="reportsEnabled"[\s\S]*?<button\s+v-else[\s\S]*?@click="visitShellTarget\(budgetPlanning\(\)\.url\)"/,
    );
});

test('mobile bottom nav falls back to planning when reports are disabled', () => {
    assert.match(mobileBottomNavSource, /<button\s+v-else/);
    assert.match(
        mobileBottomNavSource,
        /@click="visitShellTarget\(budgetPlanning\(\)\.url\)"/,
    );
    assert.match(
        mobileBottomNavSource,
        /<span>\{\{ mobileNavLabels\.planning }}<\/span>/,
    );
    assert.match(mobileBottomNavSource, /isSectionActive\('planning'\)/);
});

test('mobile bottom nav applies a clear but delicate active state style', () => {
    assert.match(
        mobileBottomNavSource,
        /isSectionActive\('reports'\)[\s\S]*\? 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500\/15 dark:text-sky-300 dark:ring-sky-500\/30'/,
    );
    assert.match(
        mobileBottomNavSource,
        /isSectionActive\('planning'\)[\s\S]*\? 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500\/15 dark:text-sky-300 dark:ring-sky-500\/30'/,
    );
    assert.match(
        mobileBottomNavSource,
        /items-end justify-between gap-2 rounded-4xl/,
    );
    assert.match(mobileBottomNavSource, /class="mx-1 mb-3 h-14 w-14 shrink-0/);
});

test('mobile bottom nav does not expose reports from the transactions destinations sheet', () => {
    const destinationsStart = mobileBottomNavSource.indexOf(
        '<Sheet v-model:open="isDestinationsOpen">',
    );
    const destinationsEnd = mobileBottomNavSource.indexOf(
        '<Sheet v-model:open="isPrimaryActionsOpen">',
        destinationsStart,
    );
    const destinationsSource = mobileBottomNavSource.slice(
        destinationsStart,
        destinationsEnd,
    );

    assert.match(
        destinationsSource,
        /visitShellTarget\(transactionsCreateHref\.url\)/,
    );
    assert.match(destinationsSource, /visitShellTarget\(recurringHref\.url\)/);
    assert.match(
        destinationsSource,
        /visitShellTarget\(budgetPlanning\(\)\.url\)/,
    );
    assert.doesNotMatch(
        destinationsSource,
        /visitShellTarget\(reports\(\)\.url\)/,
    );
});

test('mobile bottom nav exposes an admin chooser for admin users only', () => {
    assert.match(
        mobileBottomNavSource,
        /const isSettingsHubOpen = ref\(false\)/,
    );
    assert.match(
        mobileBottomNavSource,
        /const isAdminUser = computed\(\(\) => auth\.value\.user\.is_admin\)/,
    );
    assert.match(mobileBottomNavSource, /const adminLauncherHref = computed/);
    assert.match(
        mobileBottomNavSource,
        /adminIndex\(\{\s*query:\s*\{\s*mobile:\s*'launcher'/,
    );
    assert.match(
        mobileBottomNavSource,
        /<Sheet v-if="isAdminUser" v-model:open="isSettingsHubOpen">/,
    );
    assert.match(
        mobileBottomNavSource,
        /<Sheet v-model:open="isPrimaryActionsOpen">/,
    );
    assert.match(
        mobileBottomNavSource,
        /isSectionActive\(\['settings', 'admin']\)/,
    );
    assert.match(mobileBottomNavSource, /t\('app\.userMenu\.admin'\)/);
});
