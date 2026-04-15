import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const composeSource = readFileSync(
    new URL('../../resources/js/pages/admin/Communications/Compose.vue', import.meta.url),
    'utf8',
);
const layoutSource = readFileSync(
    new URL('../../resources/js/layouts/admin/Layout.vue', import.meta.url),
    'utf8',
);
const overviewSource = readFileSync(
    new URL('../../resources/js/pages/admin/Index.vue', import.meta.url),
    'utf8',
);

test('admin communication composer page renders guided steps with backend preview wiring', () => {
    assert.match(composeSource, /admin\.communicationComposer\.sections\.category/);
    assert.match(composeSource, /admin\.communicationComposer\.sections\.recipient/);
    assert.match(composeSource, /admin\.communicationComposer\.sections\.content/);
    assert.match(composeSource, /admin\.communicationComposer\.sections\.preview/);
    assert.match(composeSource, /admin\.communicationComposer\.actions\.send/);
    assert.match(composeSource, /props\.preview_url/);
    assert.match(composeSource, /props\.send_url/);
    assert.match(composeSource, /props\.recipient_lookup_url/);
    assert.match(composeSource, /selectedChannels/);
    assert.match(composeSource, /selectedRecipients/);
    assert.match(composeSource, /selectedContentMode/);
    assert.match(composeSource, /locale_options/);
    assert.match(composeSource, /customContent/);
    assert.match(composeSource, /channel\.is_fixed/);
    assert.match(composeSource, /channel\.is_disabled/);
    assert.match(
        composeSource,
        /item(?:\s*\.\s*content)+\s*\.\s*cta_label\s*&&\s*item(?:\s*\.\s*content)+\s*\.\s*cta_url/,
    );
});

test('admin layout exposes communication composer navigation entry', () => {
    assert.match(layoutSource, /admin\.sections\.communicationComposer/);
    assert.match(layoutSource, /communicationComposerIndex/);
});

test('admin overview exposes communication composer card', () => {
    assert.match(overviewSource, /admin\.overview\.cards\.communicationComposer\.title/);
    assert.match(overviewSource, /communicationComposerIndex/);
});
