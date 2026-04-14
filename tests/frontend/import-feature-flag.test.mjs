import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const settingsLayoutSource = readFileSync(
    new URL('../../resources/js/layouts/settings/Layout.vue', import.meta.url),
    'utf8',
);
const headerSource = readFileSync(
    new URL('../../resources/js/components/AppSidebarHeader.vue', import.meta.url),
    'utf8',
);
const footerSource = readFileSync(
    new URL('../../resources/js/components/AppShellFooter.vue', import.meta.url),
    'utf8',
);

test('settings navigation only exposes imports when the shared feature flag is enabled', () => {
    assert.match(settingsLayoutSource, /page\.props\.features\?\.imports_enabled/);
    assert.match(settingsLayoutSource, /importsEnabled\.value/);
});

test('global shell only exposes import shortcuts when the shared feature flag is enabled', () => {
    assert.match(headerSource, /page\.props\.features\?\.imports_enabled/);
    assert.match(headerSource, /importsEnabled\.value/);
    assert.match(footerSource, /page\.props\.features\?\.imports_enabled/);
    assert.match(footerSource, /importsEnabled\.value/);
});
