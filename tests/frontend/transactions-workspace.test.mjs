import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

const source = readFileSync(
    new URL('../../resources/js/pages/transactions/Show.vue', import.meta.url),
    'utf8',
);
const selectFunction = source.slice(
    source.indexOf('async function selectCalendarDay('),
    source.indexOf('const auditTransaction'),
);
const toggleFunction = source.slice(
    source.indexOf('function toggleMonthlySummary('),
    source.indexOf('async function selectCalendarDay('),
);

function fixture({
    bank = 1,
    shared = 0,
    reduced = false,
    storageError = false,
} = {}) {
    const frames = [];
    const scrolls = [];
    const writes = [];
    const context = vm.createContext({
        selectedDay: { value: '' },
        calendarSelectionVersion: 0,
        ledgerVisitPending: false,
        displayedTransactions: { value: Array(bank).fill({}) },
        filteredSharedLedgerMovements: { value: Array(shared).fill({}) },
        showShared: { value: true },
        ledgerResults: {
            value: {
                scrollIntoView: (options) =>
                    scrolls.push({ target: 'bank', ...options }),
            },
        },
        sharedResults: {
            value: {
                scrollIntoView: (options) =>
                    scrolls.push({ target: 'shared', ...options }),
            },
        },
        monthlySummaryOpen: { value: true },
        monthlySummaryStorageKey: 'soamco:transactions:monthly-summary-open:v1',
        nextTick: () => Promise.resolve(),
        requestAnimationFrame: (callback) => frames.push(callback),
        window: {
            matchMedia: () => ({ matches: reduced }),
            localStorage: {
                setItem: (...args) => {
                    if (storageError) {
                        throw new Error('Storage blocked');
                    }

                    writes.push(args);
                },
            },
        },
    });
    vm.runInContext(
        ts.transpile(toggleFunction + selectFunction, {
            target: ts.ScriptTarget.ES2022,
        }),
        context,
    );
    const flush = async () => {
        await new Promise(setImmediate);
        frames.splice(0).forEach((callback) => callback());
    };

    return { context, frames, scrolls, writes, flush };
}

test('calendar waits for updated rows and the next frame before scrolling', async () => {
    const f = fixture();
    const pending = f.context.selectCalendarDay('2026-10-15');
    assert.equal(f.context.selectedDay.value, '2026-10-15');
    assert.equal(f.scrolls.length, 0);
    await f.flush();
    await pending;
    assert.equal(f.scrolls.length, 1);
    assert.equal(f.scrolls[0].target, 'bank');
    assert.equal(f.scrolls[0].behavior, 'smooth');
});

test('calendar reaches shared advances when no bank movements exist', async () => {
    const f = fixture({ bank: 0, shared: 1, reduced: true });
    const pending = f.context.selectCalendarDay('2026-10-10');
    await f.flush();
    await pending;
    assert.equal(f.scrolls[0].target, 'shared');
    assert.equal(f.scrolls[0].behavior, 'instant');
});

test('empty days and hidden shared advances never scroll', async () => {
    for (const shared of [0, 1]) {
        const f = fixture({ bank: 0, shared });
        f.context.showShared.value = false;
        const pending = f.context.selectCalendarDay('2026-10-20');
        await f.flush();
        await pending;
        assert.equal(f.scrolls.length, 0);
    }
});

test('rapid calendar selections scroll only the latest result', async () => {
    const f = fixture();
    const first = f.context.selectCalendarDay('2026-10-15');
    const last = f.context.selectCalendarDay('2026-10-13');
    await f.flush();
    await Promise.all([first, last]);
    assert.equal(f.scrolls.length, 1);
    assert.equal(f.context.selectedDay.value, '2026-10-13');
});

test('navigation, reset, and a second click on the selected day cancel scrolling', async () => {
    for (const cancel of [
        (f) => {
            f.context.ledgerVisitPending = true;
        },
        (f) => {
            f.context.selectedDay.value = '';
        },
        (f) => {
            f.context.calendarSelectionVersion++;
        },
    ]) {
        const f = fixture();
        const pending = f.context.selectCalendarDay('2026-10-15');
        cancel(f);
        await f.flush();
        await pending;
        assert.equal(f.scrolls.length, 0);
    }

    const f = fixture();
    f.context.selectedDay.value = '2026-10-15';
    const pending = f.context.selectCalendarDay('2026-10-15');
    await f.flush();
    await pending;
    assert.equal(f.context.selectedDay.value, '');
    assert.equal(f.scrolls.length, 0);
});

test('summary toggle persists independently and remains usable with blocked storage', () => {
    const f = fixture();
    f.context.toggleMonthlySummary();
    assert.equal(f.context.monthlySummaryOpen.value, false);
    assert.deepEqual(f.writes, [
        ['soamco:transactions:monthly-summary-open:v1', 'false'],
    ]);
    f.context.toggleMonthlySummary();
    assert.equal(f.context.monthlySummaryOpen.value, true);
    const blocked = fixture({ storageError: true });
    assert.doesNotThrow(() => blocked.context.toggleMonthlySummary());
    assert.equal(blocked.context.monthlySummaryOpen.value, false);
});

test('open summary follows page scrolling and preserves desktop widths', () => {
    const rail = source.match(/    \.summary-rail \{([^}]+)\}/)[1];
    assert.match(rail, /position:\s*sticky/);
    assert.match(
        rail,
        /top: min\(1rem, calc\(100dvh - var\(--summary-height, 0px\) - 1rem\)\)/,
    );
    assert.doesNotMatch(rail, /overflow(?:-y)?:\s*(auto|scroll)/);
    assert.match(source, /useElementSize\(monthlySummaryElement\)/);
    assert.match(source, /ref="monthlySummaryElement"/);
    assert.match(source, /grid-template-columns: minmax\(0, 1fr\) 300px/);
    assert.match(source, /grid-template-columns: minmax\(0, 1fr\) 340px/);
    assert.match(source, /transaction-workspace grid min-w-0 gap-6/);
});

test('summary tab occupies the left border and follows scrolling when closed', () => {
    assert.match(source, /inset: 0 auto 0 -18px/);
    assert.match(source, /top: 50%/);
    assert.match(source, /height: 44px/);
    assert.match(
        source,
        /\.summary-closed \.summary-toggle-track \{\s*position: sticky/,
    );
    assert.match(source, /aria-controls="monthly-summary"/);
    assert.match(source, /:aria-expanded="monthlySummaryOpen"/);
    assert.match(source, /prefers-reduced-motion: reduce/);
    assert.match(source, /translateX\(24px\)/);
});

test('summary collapse and expansion share a slower transition and respect reduced motion', () => {
    assert.match(source, /grid-template-columns 450ms ease-in-out/);
    assert.match(source, /transform 450ms ease-in-out/);
    assert.match(source, /opacity 450ms ease-in-out/);
    assert.match(source, /display 450ms allow-discrete/);
    assert.match(
        source,
        /@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.transaction-workspace,\s*\.summary-content\s*\{\s*transition: none/,
    );
    assert.match(
        source,
        /\.summary-closed \.summary-rail\s*\{\s*position: relative;\s*top: auto/,
    );
});
