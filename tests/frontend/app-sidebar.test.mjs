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

test('app sidebar exposes both reports and planning as distinct desktop areas', () => {
    assert.match(appSidebarSource, /const reportsEnabled = computed/);
    assert.match(appSidebarSource, /title:\s*t\('nav\.reports'\)/);
    assert.match(appSidebarSource, /href:\s*reports\(\)/);
    assert.match(appSidebarSource, /title:\s*t\('nav\.planning'\)/);
    assert.match(appSidebarSource, /href:\s*budgetPlanning\(\)/);
});

test('app sidebar keeps the requested desktop order with reports last', () => {
    assert.match(
        appSidebarSource,
        /title:\s*t\('nav\.dashboard'\)[\s\S]*title:\s*t\('nav\.planning'\)[\s\S]*title:\s*t\('nav\.recurring'\)[\s\S]*title:\s*t\('nav\.transactions'\)[\s\S]*title:\s*t\('nav\.reports'\)/,
    );
});

test('app sidebar gates only reports behind the reports feature flag and never planning', () => {
    assert.match(
        appSidebarSource,
        /reportsEnabled\.value[\s\S]*title:\s*t\('nav\.reports'\)/,
    );
    assert.match(appSidebarSource, /title:\s*t\('nav\.planning'\)/);
    assert.doesNotMatch(
        appSidebarSource,
        /reportsEnabled\.value[\s\S]*title:\s*t\('nav\.planning'\)/,
    );
});
