import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const panelSource = readFileSync(
    new URL(
        '../../resources/js/components/accounts/AccountSharingPanel.vue',
        import.meta.url,
    ),
    'utf8',
);

const accountsPageSource = readFileSync(
    new URL('../../resources/js/pages/settings/Accounts.vue', import.meta.url),
    'utf8',
);

const messagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/accounts.ts', import.meta.url),
    'utf8',
);

test('account sharing panel uses dedicated i18n keys instead of hardcoded english labels', () => {
    assert.match(panelSource, /accounts\.sharing\.title/);
    assert.match(panelSource, /accounts\.sharing\.form\.title/);
    assert.match(panelSource, /accounts\.sharing\.members\.title/);
    assert.match(panelSource, /accounts\.sharing\.invitations\.title/);
    assert.doesNotMatch(panelSource, />\s*Sharing\s*</);
});

test('italian account sharing copy uses the updated natural labels', () => {
    assert.match(messagesSource, /title: 'Condivisione conto'/);
    assert.match(messagesSource, /Invita una persona/);
    assert.match(messagesSource, /Persone con accesso/);
    assert.match(messagesSource, /Inviti in attesa/);
    assert.match(messagesSource, /Solo visualizzazione/);
    assert.match(messagesSource, /Può modificare/);
    assert.match(messagesSource, /Revoca accesso/);
    assert.match(messagesSource, /Ripristina accesso/);
});

test('accounts i18n messages do not contain malformed placeholder syntax', () => {
    assert.doesNotMatch(messagesSource, /emailPlaceholder:\s*"\{'.*'}"/);
    assert.match(messagesSource, /emailPlaceholder: "nome\{'@'}esempio\.com"/);
    assert.match(
        messagesSource,
        /emailPlaceholder: "person\{'@'}example\.com"/,
    );
});

test('settings accounts page excludes cash accounts from the sharing panel dataset', () => {
    assert.match(
        accountsPageSource,
        /const shareableAccounts = computed\(\(\) =>/,
    );
    assert.match(
        accountsPageSource,
        /item\.account_type\?\.code !== 'cash_account'/,
    );
    assert.match(
        accountsPageSource,
        /item\.account_type\?\.code !== 'credit_card'/,
    );
    assert.match(accountsPageSource, /:accounts="shareableAccounts"/);
});

test('settings accounts page lets invited users leave shared accounts only through a confirmation dialog', () => {
    assert.match(
        accountsPageSource,
        /const leavingSharedAccount = ref<SharedAccountItem \| null>\(null\)/,
    );
    assert.match(
        accountsPageSource,
        /function requestLeaveSharedAccount\(item: SharedAccountItem\): void/,
    );
    assert.match(
        accountsPageSource,
        /function confirmLeaveSharedAccount\(\): void/,
    );
    assert.match(
        accountsPageSource,
        /leaveMembership\.url\(leavingSharedAccount\.value\.membership_uuid\)/,
    );
    assert.match(accountsPageSource, /t\('accounts\.page\.leaveConfirm'\)/);
});

test('account sharing panel lets owners update an active member access level without revoking the membership', () => {
    assert.match(panelSource, /updateRole as updateMembershipRole/);
    assert.match(panelSource, /async function changeMembershipRole\(/);
    assert.match(panelSource, /method: 'PATCH'/);
    assert.match(panelSource, /updateMembershipRole\.url\(membership\.uuid\)/);
    assert.match(panelSource, /accounts\.sharing\.actions\.updateRole/);
    assert.match(panelSource, /accounts\.sharing\.feedback\.roleUpdatedTitle/);
    assert.match(
        panelSource,
        /membership\.status === 'active'[\s\S]*membership\.role !== 'owner'/,
    );
});

test('account sharing panel inserts the newly created invitation into local state before the reload completes', () => {
    assert.match(
        panelSource,
        /function asInvitation\(item: unknown\): AccountSharingInvitation \| null/,
    );
    assert.match(
        panelSource,
        /function upsertInvitation\(invitation: AccountSharingInvitation\): void/,
    );
    assert.match(
        panelSource,
        /const createdInvitation = asInvitation\(payload\?\.data\);/,
    );
    assert.match(
        panelSource,
        /if \(createdInvitation\) \{\s*upsertInvitation\(createdInvitation\);/s,
    );
});

test('account sharing member cards stack cleanly on mobile before switching to side by side actions', () => {
    assert.match(
        panelSource,
        /class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"/,
    );
    assert.match(
        panelSource,
        /class="flex w-full shrink-0 flex-col items-stretch gap-2 md:w-auto md:min-w-\[12rem] md:items-end"/,
    );
    assert.match(panelSource, /class="w-full rounded-full md:w-auto"/);
});

test('account sharing picker and selected account summary stay compact on mobile', () => {
    assert.match(
        panelSource,
        /min-w-0 rounded-\[1\.25rem][\s\S]*p-3\.5[\s\S]*sm:rounded-\[1\.75rem][\s\S]*sm:p-6/,
    );
    assert.match(panelSource, /mt-4 grid min-w-0 gap-4 sm:mt-6 sm:gap-5/);
    assert.match(
        panelSource,
        /class="w-fit rounded-full px-2\.5 py-0\.5 text-\[11px] sm:px-3 sm:py-1 sm:text-xs"/,
    );
    assert.match(
        panelSource,
        /mt-4 grid min-w-0 gap-4 sm:mt-6 sm:gap-5 xl:grid-cols-\[minmax\(0,1\.1fr\)_minmax\(0,0\.9fr\)]/,
    );
    assert.match(
        panelSource,
        /trigger-class="min-h-11 rounded-\[1\.1rem] border-slate-200 bg-white px-3 pr-10 text-sm sm:min-h-12 sm:rounded-2xl/,
    );
    assert.match(
        panelSource,
        /rounded-\[1\.25rem] border border-sky-200\/70[\s\S]*p-4[\s\S]*sm:rounded-\[1\.5rem][\s\S]*sm:p-5/,
    );
    assert.match(panelSource, /class="min-w-0"/);
    assert.match(
        panelSource,
        /class="truncate text-base font-semibold tracking-tight/,
    );
    assert.match(
        panelSource,
        /class="truncate text-sm leading-5 text-slate-600/,
    );
    assert.match(panelSource, /class="flex flex-wrap gap-1\.5 sm:gap-2"/);
    assert.match(
        panelSource,
        /rounded-full px-2\.5 py-0\.5 text-\[11px] sm:px-3 sm:py-1 sm:text-xs/,
    );
});
