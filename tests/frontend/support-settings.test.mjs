import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const layoutSource = readFileSync(
    new URL('../../resources/js/layouts/settings/Layout.vue', import.meta.url),
    'utf8',
);

const pageSource = readFileSync(
    new URL('../../resources/js/pages/settings/Support.vue', import.meta.url),
    'utf8',
);

const settingsMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/settings.ts', import.meta.url),
    'utf8',
);

test('settings navigation exposes the support entry', () => {
    assert.match(layoutSource, /settings\.sections\.support/);
    assert.match(layoutSource, /settings\.summaries\.support/);
    assert.match(layoutSource, /supportIndex\(\)/);
});

test('support page uses the settings layout and localized support copy', () => {
    assert.match(pageSource, /<SettingsLayout>/);
    assert.match(pageSource, /supportTickets\.title/);
    assert.match(pageSource, /supportTickets\.type/);
    assert.match(pageSource, /supportTickets\.intro/);
});

test('settings translations include support copy in both locales', () => {
    assert.match(settingsMessagesSource, /support: 'Supporto'/);
    assert.match(settingsMessagesSource, /support: 'Support'/);
    assert.match(settingsMessagesSource, /Contatta il supporto/);
    assert.match(settingsMessagesSource, /Contact support/);
});

test('support conversation exposes only the permitted reply turn and safe shared timeline', () => {
    assert.match(pageSource, /currentTicket.status === 'waiting_user'/);
    assert.match(pageSource, /:open="!props.hasActiveTicket"/);
    assert.doesNotMatch(pageSource, /v-if="!props.hasActiveTicket"/);
    assert.match(pageSource, /openTicketCounts\[category.value\]/);
    assert.match(pageSource, /SupportTicketConversation/);
    assert.match(pageSource, /onSuccess:.*replyForm.reset/s);
    assert.doesNotMatch(pageSource, /admin_id|admin_name|admin_email/);
});
