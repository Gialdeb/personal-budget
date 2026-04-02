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
