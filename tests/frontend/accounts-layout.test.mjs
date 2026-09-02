import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const accountsPageSource = readFileSync(
    new URL('../../resources/js/pages/settings/Accounts.vue', import.meta.url),
    'utf8',
);
const accountsListSource = readFileSync(
    new URL(
        '../../resources/js/components/accounts/AccountsList.vue',
        import.meta.url,
    ),
    'utf8',
);

test('settings accounts selected account panel stacks long detail rows on very small mobile screens', () => {
    assert.match(accountsPageSource, /min-w-0 space-y-4/);
    assert.match(
        accountsPageSource,
        /rounded-\[1\.4rem] border border-slate-200\/80 bg-white\/95 p-4[\s\S]*sm:rounded-\[1\.75rem] sm:p-5/,
    );
    assert.match(
        accountsPageSource,
        /flex flex-col gap-1\.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3/,
    );
    assert.match(
        accountsPageSource,
        /text-left font-medium break-words text-slate-950 sm:text-right/,
    );
    assert.match(accountsPageSource, /font-medium break-all text-slate-950/);
    assert.match(
        accountsPageSource,
        /max-w-full rounded-2xl px-3 py-1\.5 text-left text-base font-bold tracking-tight sm:text-right sm:text-lg/,
    );
});

test('settings accounts group real types and expose safe ordering controls', () => {
    assert.match(accountsListSource, /const accountGroups = computed/);
    assert.match(accountsListSource, /account\.account_type_uuid/);
    assert.match(accountsListSource, /@pointerdown="startPointerDrag\(\$event, account\)"/);
    assert.match(accountsListSource, /@pointermove="movePointerDrag"/);
    assert.match(accountsListSource, /data-account-uuid/);
    assert.match(accountsListSource, /moveWithinGroup\(account, -1\)/);
    assert.match(accountsListSource, /moveWithinGroup\(account, 1\)/);
    assert.match(
        accountsListSource,
        /dragged\.account_type_uuid === target\.account_type_uuid/,
    );
});

test('settings accounts expose a direct, accessible default account control', () => {
    assert.match(accountsListSource, /accounts\.list\.setDefault/);
    assert.match(accountsListSource, /emit\('setDefault', account\)/);
    assert.match(
        accountsListSource,
        /:fill="\s*account\.is_default \? 'currentColor' : 'none'\s*"/,
    );
    assert.match(accountsPageSource, /function handleSetDefault/);
    assert.match(accountsPageSource, /setDefault\.url\(item\.uuid\)/);
    assert.match(accountsPageSource, /localAccounts\.value = previousAccounts/);
});

test('account rows wrap mobile actions and keep account names inside the available width', () => {
    assert.match(accountsListSource, /flex min-w-0 flex-wrap items-start/);
    assert.match(accountsListSource, /min-w-0 leading-5 font-semibold break-words/);
    assert.match(
        accountsListSource,
        /flex w-full shrink-0 justify-end gap-1 border-t/,
    );
});
