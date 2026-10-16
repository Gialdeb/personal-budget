import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse, compileTemplate } from '@vue/compiler-sfc';
import ts from 'typescript';
import { createI18n } from 'vue-i18n';

const root = new URL('../../resources/js/', import.meta.url);
const helper = readFileSync(
    new URL('lib/recurring-plan-progress.ts', root),
    'utf8',
);
const js = ts.transpile(helper.replace('export function', 'function'), {
    target: ts.ScriptTarget.ES2022,
});
const progress = new Function(`${js}; return recurringPlanProgress;`)();
const page = readFileSync(
    new URL('pages/transactions/recurring/Show.vue', root),
    'utf8',
);

test('unlimited recurring plans never present generated rows as a final total', () => {
    const entry = {
        entry_type: 'recurring',
        end_mode: 'never',
        end_date: null,
        occurrences_limit: null,
    };
    assert.deepEqual(progress(entry, 10), {
        openEnded: true,
        total: null,
        value: '10 / ∞',
        percentage: null,
    });
});

test('installments and count limited plans retain exact progress while date limited plans show a deadline', () => {
    assert.deepEqual(
        progress(
            {
                entry_type: 'installment',
                end_mode: 'after_occurrences',
                installments_count: 20,
            },
            10,
        ),
        { openEnded: false, total: 20, value: '10 / 20', percentage: 50 },
    );
    assert.equal(
        progress(
            {
                entry_type: 'recurring',
                end_mode: 'after_occurrences',
                occurrences_limit: 12,
            },
            3,
        ).percentage,
        25,
    );
    assert.deepEqual(
        progress(
            {
                entry_type: 'recurring',
                end_mode: 'until_date',
                end_date: '2028-12-31',
            },
            3,
        ),
        { openEnded: false, total: null, value: '3', percentage: null },
    );
    assert.equal(
        progress(
            {
                entry_type: 'installment',
                end_mode: 'after_occurrences',
                installments_count: 20,
            },
            21,
        ).percentage,
        100,
    );
});

test('summary separates incoming and outgoing money and identifies the paid period and generated horizon', () => {
    for (const key of [
        'received',
        'paid',
        'toReceive',
        'toPay',
        'nextIncome',
        'nextExpense',
        'preparedUntil',
        'year',
        'allTime',
    ]) {
        assert.ok(page.includes(`recurringDetail.${key}`), key);
    }

    assert.doesNotMatch(
        page,
        /labels\.plannedExpenses|labels\.installmentPaid/,
    );
    assert.match(page, /summary\.paid_amount/);
    assert.match(page, /summary\.paid_count/);
    assert.match(page, /summary\.paid_year/);
    assert.match(page, /planProgress\.percentage !== null/);
});

test('timeline focuses on today with explicit earlier and later navigation and preserves lazy loading', () => {
    assert.match(page, /offset: number \| null = null/);
    assert.match(
        page,
        /offset === null \? \{\} : \{ occurrence_offset: offset \}/,
    );
    assert.match(page, /recurringDetail\.today/);
    assert.match(page, /recurringDetail\.previous/);
    assert.match(page, /recurringDetail\.next/);
    assert.match(
        page,
        /replace \? \[\] : \(yearRows\.value\[year\] \?\? \[\]\)/,
    );
    assert.match(page, /recurringDetail\.window/);
    const { descriptor } = parse(page);
    const result = compileTemplate({
        source: descriptor.template.content,
        filename: 'Show.vue',
        id: 'recurring-detail',
        compilerOptions: { expressionPlugins: ['typescript'] },
    });
    assert.deepEqual(result.errors, []);
});

test('summary counts use singular and plural forms in both languages', () => {
    const source = readFileSync(
        new URL('i18n/messages/recurring-detail.ts', root),
        'utf8',
    );
    const js = ts.transpile(source.replace('export const', 'const'), {
        target: ts.ScriptTarget.ES2022,
    });
    const messages = new Function(`${js}; return recurringDetailMessages;`)();
    const i18n = createI18n({ legacy: false, locale: 'it', messages });
    assert.equal(
        i18n.global.t('settledCount', { count: 1 }),
        'Una scadenza saldata',
    );
    assert.equal(
        i18n.global.t('settledCount', { count: 10 }),
        '10 scadenze saldate',
    );
    i18n.global.locale.value = 'en';
    assert.equal(
        i18n.global.t('pendingCount', { count: 1 }),
        'One occurrence to manage',
    );
    assert.equal(
        i18n.global.t('pendingCount', { count: 2 }),
        '2 occurrences to manage',
    );
});

test('go to today waits for target loading before focusing and scrolling the rendered occurrence', async () => {
    const functions = page.slice(
        page.indexOf('async function focusOccurrence'),
        page.indexOf('function occurrenceAmountLabel'),
    );
    const js = ts.transpile(functions, { target: ts.ScriptTarget.ES2022 });
    const events = [];
    const hidden = { offsetParent: null };
    const visible = {
        offsetParent: {},
        focus: () => events.push('focus'),
        scrollIntoView: (options) => events.push(['scroll', options]),
    };
    const openYears = { value: [2026] };
    const focused = { value: null };
    const go = new Function(
        'props',
        'todayTargetYear',
        'loadingYears',
        'openYears',
        'loadYear',
        'failedYears',
        'focusedOccurrenceUuid',
        'nextTick',
        'document',
        'window',
        `${js}; return goToToday;`,
    )(
        {
            recurringEntry: {
                summary: {
                    nearest_occurrence: { uuid: 'target', date: '2025-12-31' },
                },
            },
        },
        { value: 2025 },
        { value: [] },
        openYears,
        async (...args) => {
            events.push(['load', ...args]);
            await Promise.resolve();
            events.push('loaded');
        },
        { value: [] },
        focused,
        async () => {
            events.push('render');
        },
        { querySelectorAll: () => [hidden, visible] },
        { matchMedia: () => ({ matches: true }) },
    );
    await go();
    assert.deepEqual(openYears.value, [2025]);
    assert.equal(focused.value, 'target');
    assert.deepEqual(events, [
        ['load', 2025, null, true, 'target'],
        'loaded',
        'render',
        'focus',
        ['scroll', { behavior: 'auto', block: 'center' }],
    ]);
});

test('automatic future amounts explanation is bilingual and limited to active automatic open ended plans', () => {
    const source = readFileSync(
        new URL('i18n/messages/recurring-detail.ts', root),
        'utf8',
    );
    const js = ts.transpile(source.replace('export const', 'const'), {
        target: ts.ScriptTarget.ES2022,
    });
    const messages = new Function(`${js}; return recurringDetailMessages;`)();
    assert.match(
        messages.it.automaticDescription,
        /importi previsti saranno aggiunti automaticamente/,
    );
    assert.match(
        messages.en.automaticDescription,
        /expected amounts will be added automatically/,
    );
    const code = page.slice(
        page.indexOf('const automaticOccurrencesNoteVisible'),
        page.indexOf('const todayTargetYear'),
    );
    const fn = ts.transpile(code, { target: ts.ScriptTarget.ES2022 });

    for (const [openEnded, automatic, active, status, expected] of [
        [true, true, true, 'active', true],
        [false, true, true, 'active', false],
        [true, false, true, 'active', false],
        [true, true, false, 'paused', false],
        [true, true, true, 'cancelled', false],
    ]) {
        const visible = new Function(
            'computed',
            'planProgress',
            'entry',
            `${fn}; return automaticOccurrencesNoteVisible;`,
        )(
            (f) => f(),
            { value: { openEnded } },
            {
                value: {
                    auto_generate_occurrences: automatic,
                    is_active: active,
                    status,
                },
            },
        );
        assert.equal(visible, expected);
    }

    assert.match(page, /data-testid="automatic-occurrences-note"/);
    assert.match(page, /@click="goToToday"/);
});

test('automatic explanation stays after the timeline and remembers each user and plan separately', () => {
    assert.ok(
        page.indexOf('data-testid="automatic-occurrences-note"') >
            page.indexOf("t('recurringDetail.next')"),
    );
    assert.match(page, /useStorage<Record<string, boolean>>/);
    assert.match(page, /entry\.value\.uuid/);
    assert.match(page, /auth\.value\.user\?\.uuid/);
    assert.match(
        page,
        /automaticNotePreferences\.value\[automaticNoteKey\.value\] \?\? false/,
    );
    assert.match(page, /:open="automaticNoteOpen"/);
    assert.match(page, /@toggle="toggleAutomaticNote"/);
    const code = page.slice(
        page.indexOf('function toggleAutomaticNote'),
        page.indexOf('const automaticOccurrencesNoteVisible'),
    );
    const js = ts.transpile(code, { target: ts.ScriptTarget.ES2022 });
    const preferences = { value: { 'user:other-plan': true } };
    const toggle = new Function(
        'automaticNotePreferences',
        'automaticNoteKey',
        'automaticNoteOpen',
        `${js}; return toggleAutomaticNote;`,
    )(preferences, { value: 'user:this-plan' }, { value: false });
    toggle({ currentTarget: { open: true } });
    assert.deepEqual(preferences.value, {
        'user:other-plan': true,
        'user:this-plan': true,
    });
});

test('finite progress uses matching zero one and many forms in every supported language', () => {
    const dictionary = readFileSync(
        new URL('i18n/messages/recurring-detail.ts', root),
        'utf8',
    );
    const code = ts.transpile(dictionary.replace('export const', 'const'), {
        target: ts.ScriptTarget.ES2022,
    });
    const messages = new Function(`${code}; return recurringDetailMessages;`)();

    for (const locale of Object.keys(messages)) {
        assert.equal(messages[locale].finiteProgress.split('|').length, 3);
        const i18n = createI18n({
            legacy: false,
            locale,
            messages: { [locale]: { recurringDetail: messages[locale] } },
        });

        for (const count of [0, 1, 4]) {
            const text = i18n.global.t('recurringDetail.finiteProgress', {
                count,
                total: 12,
            });
            assert.ok(text.includes('12'));
            assert.doesNotMatch(text, /[{}|]/);
            assert.ok(count < 2 || text.includes('4'));
        }
    }
});
