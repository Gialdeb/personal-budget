import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const profileSource = readFileSync(
    new URL('../../resources/js/pages/settings/Profile.vue', import.meta.url),
    'utf8',
);

test('settings profile renders notification preferences section and save action', () => {
    assert.match(profileSource, /settings\.profile\.notifications\.title/);
    assert.match(profileSource, /settings\.profile\.notifications\.description/);
    assert.match(profileSource, /settings\.profile\.notifications\.save/);
    assert.match(profileSource, /updateNotificationPreferencesAction/);
});

test('settings profile exposes email and dashboard notification toggles', () => {
    assert.match(profileSource, /settings\.profile\.notifications\.channels\.email/);
    assert.match(profileSource, /settings\.profile\.notifications\.channels\.dashboard/);
    assert.doesNotMatch(profileSource, />\s*Dashboard\s*</);
    assert.match(profileSource, /email_enabled/);
    assert.match(profileSource, /in_app_enabled/);
});

test('settings profile exposes notification preferences empty state', () => {
    assert.match(profileSource, /settings\.profile\.notifications\.empty\.title/);
    assert.match(profileSource, /settings\.profile\.notifications\.empty\.description/);
});
