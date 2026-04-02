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
    assert.match(mobileBottomNavSource, /t\('nav\.recurring'\)/);
    assert.match(mobileBottomNavSource, /t\('app\.userMenu\.settings'\)/);
    assert.match(mobileBottomNavSource, /dashboard:\s*isItalian \? 'Panor\.'/);
    assert.match(
        mobileBottomNavSource,
        /transactions:\s*isItalian \? 'Transaz\.'/,
    );
    assert.match(mobileBottomNavSource, /planning:\s*isItalian \? 'Prevent\.'/);
    assert.match(mobileBottomNavSource, /const settingsHref = computed/);
    assert.match(mobileBottomNavSource, /mobile:\s*'launcher'/);
    assert.doesNotMatch(mobileBottomNavSource, /<span>Budget<\/span>/);
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
        /isSectionActive\(\['settings', 'admin']\)/,
    );
    assert.match(mobileBottomNavSource, /t\('app\.userMenu\.admin'\)/);
});
