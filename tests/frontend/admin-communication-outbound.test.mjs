import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const indexSource = readFileSync(
    new URL('../../resources/js/pages/admin/Communications/Outbound/Index.vue', import.meta.url),
    'utf8',
);
const showSource = readFileSync(
    new URL('../../resources/js/pages/admin/Communications/Outbound/Show.vue', import.meta.url),
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

test('admin outbound index renders filters, states, and detail action', () => {
    assert.match(indexSource, /admin\.communicationOutbound\.filters\.title/);
    assert.match(indexSource, /admin\.communicationOutbound\.table\.status/);
    assert.match(indexSource, /admin\.communicationOutbound\.actions\.open/);
    assert.match(indexSource, /statusClass/);
    assert.match(indexSource, /outboundShow/);
    assert.match(indexSource, /router\.get/);
    assert.match(indexSource, /admin\.communicationOutbound\.pagination\.page/);
    assert.match(indexSource, /props\.outboundMessages\.links\s*\.?\s*prev/);
});

test('admin outbound show renders summary, content, and payload sections', () => {
    assert.match(showSource, /admin\.communicationOutbound\.detail\.sections\.summary/);
    assert.match(showSource, /admin\.communicationOutbound\.detail\.sections\.content/);
    assert.match(showSource, /admin\.communicationOutbound\.detail\.sections\.payload/);
    assert.match(showSource, /JSON\.stringify/);
});

test('admin navigation and overview expose outbound history entry', () => {
    assert.match(layoutSource, /admin\.sections\.communicationOutbound/);
    assert.match(layoutSource, /communicationOutboundIndex/);
    assert.match(overviewSource, /admin\.overview\.cards\.communicationOutbound\.title/);
});
