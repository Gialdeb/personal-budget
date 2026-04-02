import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const appSidebarSource = readFileSync(
    new URL('../../resources/js/components/AppSidebar.vue', import.meta.url),
    'utf8',
);

test('app sidebar no longer exposes imports in the primary navigation', () => {
    assert.doesNotMatch(appSidebarSource, /title:\s*t\('nav\.imports'\)/);
    assert.doesNotMatch(appSidebarSource, /href:\s*imports\(\)/);
});
