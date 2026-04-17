import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const bankFormSource = readFileSync(
    new URL(
        '../../resources/js/components/banks/BankFormSheet.vue',
        import.meta.url,
    ),
    'utf8',
);
const supportFiltersSource = readFileSync(
    new URL(
        '../../resources/js/components/admin/support/SupportRequestFilters.vue',
        import.meta.url,
    ),
    'utf8',
);
const supportIndexSource = readFileSync(
    new URL(
        '../../resources/js/pages/admin/SupportRequests/Index.vue',
        import.meta.url,
    ),
    'utf8',
);
const supportShowSource = readFileSync(
    new URL(
        '../../resources/js/pages/admin/SupportRequests/Show.vue',
        import.meta.url,
    ),
    'utf8',
);
const supportDetailSource = readFileSync(
    new URL(
        '../../resources/js/components/admin/support/SupportRequestDetailCard.vue',
        import.meta.url,
    ),
    'utf8',
);
const accountsFormSource = readFileSync(
    new URL(
        '../../resources/js/components/accounts/AccountFormSheet.vue',
        import.meta.url,
    ),
    'utf8',
);
const banksPageSource = readFileSync(
    new URL('../../resources/js/pages/settings/Banks.vue', import.meta.url),
    'utf8',
);
const settingsMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/settings.ts', import.meta.url),
    'utf8',
);
const adminMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/admin.ts', import.meta.url),
    'utf8',
);
const accountsMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/accounts.ts', import.meta.url),
    'utf8',
);

test('bank form uses localized copy instead of hardcoded Italian strings', () => {
    assert.match(bankFormSource, /useI18n/);
    assert.match(bankFormSource, /settings\.banks\.form\.titleCreate/);
    assert.match(bankFormSource, /settings\.banks\.form\.titleEdit/);
    assert.match(bankFormSource, /settings\.banks\.form\.fields\.name/);
    assert.match(bankFormSource, /settings\.banks\.form\.toggles\.active/);
    assert.match(bankFormSource, /settings\.banks\.form\.actions\.create/);
    assert.match(settingsMessagesSource, /titleCreate: 'Nuova banca personalizzata'/);
    assert.match(settingsMessagesSource, /titleCreate: 'New custom bank'/);
    assert.match(settingsMessagesSource, /created: 'Banca personalizzata creata con successo\.'/);
    assert.match(settingsMessagesSource, /created: 'Custom bank created successfully\.'/);
});

test('admin support requests page and filters are localized through admin messages', () => {
    assert.match(supportFiltersSource, /admin\.supportRequestsPage\.filters\.status/);
    assert.match(supportFiltersSource, /admin\.supportRequestsPage\.filters\.allStatuses/);
    assert.match(supportFiltersSource, /admin\.supportRequestsPage\.filters\.apply/);
    assert.match(supportIndexSource, /admin\.supportRequestsPage\.title/);
    assert.match(supportIndexSource, /admin\.supportRequestsPage\.summary/);
    assert.match(supportIndexSource, /admin\.supportRequestsPage\.pagination\.page/);
    assert.match(supportShowSource, /admin\.supportRequestsPage\.detail\.updateStatusTitle/);
    assert.match(supportShowSource, /admin\.supportRequestsPage\.actions\.saveStatus/);
    assert.match(supportDetailSource, /admin\.supportRequestsPage\.detail\.message/);
    assert.match(supportDetailSource, /admin\.supportRequestsPage\.detail\.userCardTitle/);
    assert.match(supportIndexSource, /toLocaleString\(\s*locale/s);
    assert.match(adminMessagesSource, /supportRequestsPage: \{/);
    assert.match(adminMessagesSource, /title: 'Richieste supporto'/);
    assert.match(adminMessagesSource, /title: 'Support requests'/);
    assert.match(adminMessagesSource, /allStatuses: 'Tutti gli stati'/);
    assert.match(adminMessagesSource, /allStatuses: 'All statuses'/);
});

test('bank search placeholders are localized in settings and account forms', () => {
    assert.match(
        banksPageSource,
        /settings\.banks\.catalog\.searchPlaceholder/,
    );
    assert.match(
        accountsFormSource,
        /accounts\.form\.fields\.bankSearchPlaceholder/,
    );
    assert.match(
        settingsMessagesSource,
        /searchPlaceholder: 'Cerca banca, slug o paese'/,
    );
    assert.match(
        settingsMessagesSource,
        /searchPlaceholder: 'Search bank, slug, or country'/,
    );
    assert.match(
        accountsMessagesSource,
        /bankSearchPlaceholder: 'Cerca banca, slug o paese'/,
    );
    assert.match(
        accountsMessagesSource,
        /bankSearchPlaceholder: 'Search bank, slug, or country'/,
    );
});
