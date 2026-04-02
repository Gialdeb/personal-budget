import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const adminUserBillingSource = readFileSync(
    new URL('../../resources/js/pages/admin/UserBilling.vue', import.meta.url),
    'utf8',
);

test('admin user billing page uses mobile-first cards and stacked actions while preserving desktop table', () => {
    assert.match(
        adminUserBillingSource,
        /data-test="admin-user-billing-history-card"/,
    );
    assert.match(
        adminUserBillingSource,
        /<Table\s+v-if="props\.transactions\.length > 0"\s+class="hidden md:table"/,
    );
    assert.match(adminUserBillingSource, /class="w-full rounded-xl sm:w-auto"/);
    assert.match(adminUserBillingSource, /class="grid gap-4 sm:grid-cols-2"/);
    assert.match(
        adminUserBillingSource,
        /class="flex flex-col items-stretch gap-3 rounded-2xl border border-slate-200 px-4 py-3 sm:flex-row/,
    );
});
