import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const settingsLayoutSource = readFileSync(
    new URL('../../resources/js/layouts/settings/Layout.vue', import.meta.url),
    'utf8',
);
const settingsMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/settings.ts', import.meta.url),
    'utf8',
);
const appearancePageSource = readFileSync(
    new URL(
        '../../resources/js/pages/settings/Appearance.vue',
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

test('settings layout exposes a mobile launcher and a dedicated page header with back navigation', () => {
    assert.match(settingsLayoutSource, /data-test="settings-mobile-launcher"/);
    assert.match(
        settingsLayoutSource,
        /data-test="settings-mobile-page-header"/,
    );
    assert.match(settingsLayoutSource, /class="mt-4 grid grid-cols-2 gap-3"/);
    assert.match(settingsLayoutSource, /const isSettingsRoot = computed/);
    assert.match(
        settingsLayoutSource,
        /currentUrl\.value\.pathname === '\/settings'/,
    );
    assert.match(
        settingsLayoutSource,
        /const mobileLauncherHref = computed\(\(\) =>[\s\S]*settingsIndex\(\),?[\s\S]*\)/,
    );
    assert.match(settingsLayoutSource, /ArrowLeft/);
    assert.match(
        settingsLayoutSource,
        /useMediaQuery\('\(max-width: 767px\)'\)/,
    );
    assert.match(settingsLayoutSource, /const showMobileLauncher = computed/);
    assert.match(settingsLayoutSource, /v-if="showMobileLauncher"/);
    assert.match(settingsLayoutSource, /v-if="!showMobileLauncher"/);
    assert.match(
        settingsLayoutSource,
        /<aside class="hidden space-y-4 md:block">/,
    );
    assert.match(
        settingsLayoutSource,
        /summary:\s*t\('settings\.summaries\.profile'\)/,
    );
    assert.match(settingsLayoutSource, /editSharedCategories\(\)/);
    assert.match(
        settingsLayoutSource,
        /title:\s*t\('settings\.sections\.imports'\)/,
    );
    assert.match(
        settingsLayoutSource,
        /summary:\s*t\('settings\.summaries\.imports'\)/,
    );
    assert.match(settingsLayoutSource, /href:\s*imports\(\)/);
    assert.match(
        settingsLayoutSource,
        /title:\s*t\('settings\.sections\.exports'\)/,
    );
    assert.match(
        settingsLayoutSource,
        /summary:\s*t\('settings\.summaries\.exports'\)/,
    );
    assert.match(settingsLayoutSource, /href:\s*editExports\(\)/);
    assert.match(settingsLayoutSource, /t\('settings\.navigationLabel'\)/);
});

test('settings layout localizes navigation labels in both locales', () => {
    assert.match(settingsMessagesSource, /navigationLabel: 'Impostazioni'/);
    assert.match(settingsMessagesSource, /navigationLabel: 'Settings'/);
});

test('settings layout hides appearance from the primary settings navigation', () => {
    assert.doesNotMatch(settingsLayoutSource, /settings\.sections\.appearance/);
    assert.doesNotMatch(
        settingsLayoutSource,
        /settings\.summaries\.appearance/,
    );
    assert.doesNotMatch(settingsLayoutSource, /editAppearance\(/);
});

test('settings desktop navigation keeps readable hover contrast for text and icons', () => {
    assert.match(
        settingsLayoutSource,
        /text-muted-foreground hover:bg-foreground hover:text-background/,
    );
    assert.match(
        settingsLayoutSource,
        /text-foreground group-hover:text-background/,
    );
    assert.match(
        settingsLayoutSource,
        /border-border bg-muted text-muted-foreground group-hover:border-background\/15 group-hover:bg-background\/10 group-hover:text-background/,
    );
    assert.match(
        settingsLayoutSource,
        /text-muted-foreground group-hover:text-background\/72/,
    );
});

test('legacy appearance page reuses the shared theme preference control', () => {
    assert.match(
        appearancePageSource,
        /border-border\/80 bg-card\/95 text-card-foreground/,
    );
    assert.match(
        appearancePageSource,
        /from-accent\/80 via-muted\/55 to-secondary\/70/,
    );
    assert.match(
        appearancePageSource,
        /<ThemePreferenceControl variant="inline" \/>/,
    );
    assert.match(
        themePreferenceControlSource,
        /value: 'light'[\s\S]*value: 'dark'[\s\S]*value: 'system'/,
    );
    assert.match(themePreferenceControlSource, /props\.tone === 'sidebar'/);
    assert.match(themePreferenceControlSource, /role="radiogroup"/);
});
