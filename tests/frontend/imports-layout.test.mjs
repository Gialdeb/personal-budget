import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const importsIndexSource = readFileSync(
    new URL('../../resources/js/pages/imports/Index.vue', import.meta.url),
    'utf8',
);

const importsShowSource = readFileSync(
    new URL('../../resources/js/pages/imports/Show.vue', import.meta.url),
    'utf8',
);

test('imports pages are wrapped in the settings layout', () => {
    assert.match(
        importsIndexSource,
        /import SettingsLayout from '@\/layouts\/settings\/Layout\.vue';/,
    );
    assert.match(importsIndexSource, /<SettingsLayout>/);
    assert.match(importsIndexSource, /<\/SettingsLayout>/);

    assert.match(
        importsShowSource,
        /import SettingsLayout from '@\/layouts\/settings\/Layout\.vue';/,
    );
    assert.match(importsShowSource, /<SettingsLayout>/);
    assert.match(importsShowSource, /<\/SettingsLayout>/);
});

test('imports pages keep long bank and account labels from breaking mobile layouts', () => {
    assert.match(importsIndexSource, /function importListMetaParts/);
    assert.match(
        importsIndexSource,
        /class="mt-1 flex flex-wrap items-center gap-x-1\.5 gap-y-1 text-sm break-words text-slate-500 dark:text-slate-400"/,
    );
    assert.match(
        importsIndexSource,
        /class="break-all text-base font-semibold text-slate-950 sm:break-words dark:text-slate-50"/,
    );

    assert.match(importsShowSource, /function importDetailMetaParts/);
    assert.match(
        importsShowSource,
        /class="flex flex-wrap items-center gap-x-1\.5 gap-y-1 text-sm leading-6 break-words"/,
    );
});
