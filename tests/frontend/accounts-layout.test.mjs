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

test('settings accounts mobile cards avoid horizontal clipping on narrow devices', () => {
    assert.match(
        accountsListSource,
        /min-w-0 overflow-hidden rounded-\[1\.25rem] border bg-white\/95 p-3\.5[\s\S]*sm:rounded-\[1\.5rem] sm:p-4/,
    );
    assert.match(
        accountsListSource,
        /max-w-full rounded-2xl bg-slate-50\/90 px-3 py-2 text-left text-base font-bold tracking-tight break-words/,
    );
    assert.match(
        accountsListSource,
        /flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3/,
    );
    assert.match(accountsListSource, /class="mt-3 grid gap-2"/);
    assert.match(
        accountsListSource,
        /class="h-10 w-full justify-start rounded-2xl px-4"/,
    );
    assert.match(
        accountsListSource,
        /class="max-w-56 px-5 py-4 align-top text-slate-600 break-words dark:text-slate-300"/,
    );
});
