import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const layoutSource = readFileSync(
    new URL('../../resources/js/layouts/settings/Layout.vue', import.meta.url),
    'utf8',
);

const pageSource = readFileSync(
    new URL('../../resources/js/pages/settings/Support.vue', import.meta.url),
    'utf8',
);

const settingsMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/settings.ts', import.meta.url),
    'utf8',
);

test('settings navigation exposes the support entry', () => {
    assert.match(layoutSource, /settings\.sections\.support/);
    assert.match(layoutSource, /settings\.summaries\.support/);
    assert.match(layoutSource, /supportIndex\(\)/);
});

test('support page uses the settings layout and localized support copy', () => {
    assert.match(pageSource, /<SettingsLayout>/);
    assert.match(pageSource, /settings\.supportPage\.title/);
    assert.match(pageSource, /settings\.supportPage\.fields\.category/);
    assert.match(pageSource, /settings\.supportPage\.summaryCard\.description/);
});

test('settings translations include support copy in both locales', () => {
    assert.match(settingsMessagesSource, /support: 'Supporto'/);
    assert.match(settingsMessagesSource, /support: 'Support'/);
    assert.match(settingsMessagesSource, /Contatta il supporto/);
    assert.match(settingsMessagesSource, /Contact support/);
});
