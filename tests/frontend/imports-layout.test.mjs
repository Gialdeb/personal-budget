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
        /class="flex min-w-0 flex-col gap-1 text-sm text-slate-500 md:flex-row md:flex-wrap md:items-center md:gap-x-1\.5 md:gap-y-1 dark:text-slate-400"/,
    );
    assert.match(
        importsIndexSource,
        /class="truncate text-base font-semibold text-slate-950 dark:text-slate-50"/,
    );

    assert.match(importsShowSource, /function importDetailMetaParts/);
    assert.match(
        importsShowSource,
        /class="flex flex-wrap items-center gap-x-1\.5 gap-y-1 text-sm leading-6 break-words"/,
    );
});

test('imports upload form does not expose a separate account selector', () => {
    assert.doesNotMatch(importsIndexSource, /form\.account_uuid/);
    assert.doesNotMatch(importsIndexSource, /id="import-account"/);
    assert.doesNotMatch(importsIndexSource, /props\.options\.accounts/);
    assert.match(importsIndexSource, /form\.import_format_uuid !== ''/);
    assert.match(importsIndexSource, /form\.file !== null/);
});
