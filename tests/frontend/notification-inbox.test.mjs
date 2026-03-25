import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const pageSource = readFileSync(
    new URL('../../resources/js/pages/Notifications/Index.vue', import.meta.url),
    'utf8',
);

test('notifications page renders real inbox actions and empty state copy', () => {
    assert.match(pageSource, /app\.shell\.notificationsPage\.title/);
    assert.match(pageSource, /app\.shell\.notificationsPage\.actions\.markAllAsRead/);
    assert.match(pageSource, /app\.shell\.notificationsPage\.actions\.markAsRead/);
    assert.match(pageSource, /presentation\.image_url/);
    assert.match(pageSource, /presentation\.layout/);
    assert.match(pageSource, /app\.shell\.notificationsPage\.richLabel/);
    assert.match(pageSource, /app\.shell\.notificationsPage\.empty\.title/);
    assert.match(pageSource, /router\.reload\(/);
});
