import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const composableSource = readFileSync(
    new URL(
        '../../resources/js/composables/useNotificationInboxRealtime.ts',
        import.meta.url,
    ),
    'utf8',
);
const headerSource = readFileSync(
    new URL('../../resources/js/components/AppSidebarHeader.vue', import.meta.url),
    'utf8',
);
const notificationsPageSource = readFileSync(
    new URL('../../resources/js/pages/Notifications/Index.vue', import.meta.url),
    'utf8',
);

test('notification inbox realtime composable listens on the user-scoped reverb channel once', () => {
    assert.match(composableSource, /users\.\$\{userUuid}.notifications/);
    assert.match(composableSource, /notification\.inbox\.updated/);
    assert.match(composableSource, /activeRealtimeUserUuid/);
    assert.match(composableSource, /unsubscribeFromRealtime/);
    assert.doesNotMatch(composableSource, /setInterval\(/);
});

test('header notifications use the centralized realtime inbox state', () => {
    assert.match(headerSource, /useNotificationInboxRealtime/);
    assert.match(headerSource, /replaceNotificationPreview/);
    assert.match(headerSource, /markNotificationReadLocally/);
    assert.match(headerSource, /markAllNotificationsReadLocally/);
    assert.doesNotMatch(headerSource, /notificationInbox\.value\.preview_url/);
});

test('notifications page updates from realtime state without router reload polling', () => {
    assert.match(notificationsPageSource, /useNotificationInboxRealtime/);
    assert.match(notificationsPageSource, /syncNotificationsPage/);
    assert.doesNotMatch(notificationsPageSource, /router\.reload\(/);
    assert.doesNotMatch(notificationsPageSource, /setInterval\(/);
});
