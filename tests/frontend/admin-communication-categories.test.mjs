import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const indexSource = readFileSync(
    new URL('../../resources/js/pages/admin/CommunicationCategories/Index.vue', import.meta.url),
    'utf8',
);
const showSource = readFileSync(
    new URL('../../resources/js/pages/admin/CommunicationCategories/Show.vue', import.meta.url),
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

test('admin communication categories index renders search and pagination controls', () => {
    assert.match(indexSource, /admin\.communicationCategories\.filters\.title/);
    assert.match(indexSource, /communicationCategories\.pagination\.page/);
    assert.match(indexSource, /showCommunicationCategory/);
});

test('admin communication categories show renders channel configuration form', () => {
    assert.match(showSource, /admin\.communicationCategories\.sections\.channels/);
    assert.match(showSource, /admin\.communicationCategories\.form\.enableChannel/);
    assert.match(showSource, /updateCommunicationCategoryChannels/);
});

test('admin navigation and overview expose communication categories entry', () => {
    assert.match(layoutSource, /admin\.sections\.communicationCategories/);
    assert.match(overviewSource, /admin\.overview\.cards\.communicationCategories\.title/);
});
