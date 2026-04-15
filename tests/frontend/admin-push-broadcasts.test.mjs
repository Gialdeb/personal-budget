import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const pageSource = readFileSync(
    new URL('../../resources/js/pages/admin/PushBroadcasts/Index.vue', import.meta.url),
    'utf8',
);
const entrySource = readFileSync(
    new URL('../../resources/js/pages/admin/PushBroadcasts.vue', import.meta.url),
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

test('admin push broadcasts page renders form, audience summary, and history copy', () => {
    assert.match(entrySource, /PushBroadcasts\/Index\.vue/);
    assert.match(pageSource, /admin\.pushBroadcasts\.title/);
    assert.match(pageSource, /admin\.pushBroadcasts\.audience\.eligibleUsers/);
    assert.match(pageSource, /admin\.pushBroadcasts\.form\.title/);
    assert.match(pageSource, /admin\.pushBroadcasts\.history\.title/);
    assert.match(pageSource, /push-broadcast-submit/);
});

test('admin navigation and overview expose push broadcasts only behind the feature flag', () => {
    assert.match(layoutSource, /features\?\.push_notifications_enabled/);
    assert.match(layoutSource, /admin\.sections\.pushBroadcasts/);
    assert.match(overviewSource, /admin\.overview\.cards\.pushBroadcasts\.title/);
    assert.match(overviewSource, /features\?\.push_notifications_enabled/);
});
