import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createI18n } from 'vue-i18n';
import {
    transactionCalendarDate,
    transactionCalendarDays,
    transactionDateFromDay,
    transactionCalendarPeriod,
} from '../../resources/js/lib/transaction-calendar.js';

const root = new URL('../../', import.meta.url);

test('fixed year date selection validates leap days and rejects other years without normalizing dates', () => {
    assert.deepEqual(transactionCalendarDate('2024-02-29', 2024), {
        year: 2024,
        month: 2,
        day: 29,
    });

    for (const value of [
        '2025-02-29',
        '2025-04-31',
        '2025-13-01',
        '2025-00-01',
        '2025-01-00',
        '2024-12-31',
        '',
    ]) {
        assert.equal(transactionCalendarDate(value, 2025), null, value);
    }

    assert.equal(transactionCalendarDate('2025-12-31', 2025).month, 12);
});

test('calendar generates all twelve months within the fixed year with Monday aligned days', () => {
    for (const year of [2024, 2025]) {
        for (let month = 1; month <= 12; month++) {
            const days = transactionCalendarDays(year, month);
            const dated = days.filter(Boolean);
            assert.equal(
                dated.length,
                new Date(Date.UTC(year, month, 0)).getUTCDate(),
            );
            assert.equal(
                days.findIndex(Boolean),
                (new Date(Date.UTC(year, month - 1, 1)).getUTCDay() + 6) % 7,
            );
            assert.ok(
                dated.every((day) => transactionCalendarDate(day.date, year)),
            );
            assert.equal(dated[0].day, 1);
        }
    }

    assert.deepEqual(transactionCalendarDays(2025, 13), []);
});

test('shared day input keeps the full date below and calendar closed until requested', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        resolve: { alias: { '@': new URL('resources/js', root).pathname } },
        server: { middlewareMode: true, hmr: false, ws: false, watch: null },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });

    try {
        const { default: Picker } = await server.ssrLoadModule(
            '/resources/js/components/transactions/TransactionDatePicker.vue',
        );
        const { transactionsMessages } = await server.ssrLoadModule(
            '/resources/js/i18n/messages/transactions.ts',
        );
        const app = createSSRApp({
            render: () =>
                h(Picker, {
                    modelValue: '2024-02-29',
                    year: 2024,
                    month: 3,
                }),
        });
        app.use(
            createI18n({
                legacy: false,
                locale: 'it',
                messages: { it: { transactions: transactionsMessages.it } },
            }),
        );
        const html = await renderToString(app);
        assert.match(html, /29 febbraio 2024/);
        assert.match(html, /Giorno/);
        assert.match(
            html,
            /Attenzione: stai registrando in un mese precedente/,
        );
        assert.match(html, /<label[\s\S]*?<input[\s\S]*?29 febbraio 2024/);
        assert.match(html, /type="number"/);
        assert.match(html, /value="29"/);
        assert.match(html, /aria-expanded="false"/);
        assert.doesNotMatch(html, /<button/);
        const lockedApp = createSSRApp({
            render: () =>
                h(Picker, {
                    modelValue: '2026-12-15',
                    year: 2026,
                    month: 12,
                    fixedMonth: 12,
                    hideContext: true,
                    hideLabel: true,
                }),
        });
        lockedApp.use(
            createI18n({
                legacy: false,
                locale: 'it',
                messages: { it: { transactions: transactionsMessages.it } },
            }),
        );
        const lockedHtml = await renderToString(lockedApp);
        assert.match(lockedHtml, /Giorno/);
        assert.match(lockedHtml, /<label[^>]*class="[^"]*sr-only/);
        assert.match(lockedHtml, /type="number"/);
        assert.match(lockedHtml, /value="15"/);
        assert.doesNotMatch(
            lockedHtml,
            /aria-describedby|aria-live|15 dicembre|Stai registrando|Attenzione/,
        );

        const now = new Date();

        for (const [offset, tone, message] of [
            [
                -1,
                'amber',
                'Attenzione: stai registrando in un mese precedente.',
            ],
            [0, 'emerald', 'Stai registrando nel mese corrente.'],
            [1, 'sky', 'Stai registrando in un mese futuro.'],
        ]) {
            const date = new Date(
                now.getFullYear(),
                now.getMonth() + offset,
                15,
            );
            const year = date.getFullYear();
            const month = date.getMonth() + 1;
            const value = `${year}-${String(month).padStart(2, '0')}-15`;
            const feedbackApp = createSSRApp({
                render: () =>
                    h(Picker, {
                        modelValue: value,
                        year,
                        month: month === 12 ? 1 : 12,
                    }),
            });
            feedbackApp.use(
                createI18n({
                    legacy: false,
                    locale: 'it',
                    messages: { it: { transactions: transactionsMessages.it } },
                }),
            );
            const feedback = await renderToString(feedbackApp);
            assert.ok(feedback.includes(message));
            assert.ok(feedback.includes(`bg-${tone}-50`));
        }
    } finally {
        await server.close();
    }
});

test('ordinary sheets and inline rows share the picker and submit the canonical date', async () => {
    for (const path of [
        'resources/js/components/transactions/TransactionFormSheet.vue',
        'resources/js/pages/transactions/Show.vue',
    ]) {
        const source = await readFile(new URL(path, root), 'utf8');
        assert.match(source, /TransactionDatePicker/);
        assert.match(
            source,
            /selected_date: (?:props.paymentOccurrence \? null : )?(?:form|editForm|inlineForm)\.transaction_date/,
        );
        assert.doesNotMatch(
            source,
            /<Input\s[^>]*v-model="\s*(?:form|editForm|inlineForm)\.transaction_day/,
        );
    }
});

test('typed days retain the chosen month and month navigation clamps only impossible days', () => {
    let value = transactionDateFromDay('31', 2026, 10);
    assert.equal(value, '2026-10-31');
    value = transactionDateFromDay(value.slice(8, 10), 2026, 11, true);
    assert.equal(value, '2026-11-30');
    value = transactionDateFromDay(
        '12',
        2026,
        transactionCalendarDate(value, 2026).month,
    );
    assert.equal(value, '2026-11-12');
    assert.equal(transactionDateFromDay('31', 2026, 11), '');
    assert.equal(transactionDateFromDay('', 2026, 11), '');
    assert.equal(transactionDateFromDay('0', 2026, 11), '');
    assert.equal(transactionDateFromDay('32', 2026, 11), '');
    assert.equal(transactionDateFromDay('1.5', 2026, 11), '');
    assert.equal(transactionDateFromDay('31', 2024, 2, true), '2024-02-29');
    assert.equal(transactionDateFromDay('31', 2025, 2, true), '2025-02-28');
    assert.equal(transactionDateFromDay('10', 2026, 0, true), '');
    assert.equal(transactionDateFromDay('10', 2026, 13, true), '');
});

test('feedback compares real calendar months including year boundaries and ignores individual days', () => {
    const now = new Date(2026, 9, 21);

    for (const day of ['01', '21', '31']) {
        assert.equal(
            transactionCalendarPeriod(`2026-10-${day}`, 2026, now),
            'current',
        );
    }

    assert.equal(transactionCalendarPeriod('2026-09-30', 2026, now), 'past');
    assert.equal(transactionCalendarPeriod('2026-11-01', 2026, now), 'future');
    assert.equal(transactionCalendarPeriod('2025-12-31', 2025, now), 'past');
    assert.equal(transactionCalendarPeriod('2027-01-01', 2027, now), 'future');
    assert.equal(
        transactionCalendarPeriod('2026-12-31', 2026, new Date(2027, 0, 1)),
        'past',
    );
    assert.equal(transactionCalendarPeriod('', 2026, now), 'invalid');
});

test('picker keeps typing focus when opened and updates canonical date on month selection', async () => {
    const source = await readFile(
        new URL(
            'resources/js/components/transactions/TransactionDatePicker.vue',
            root,
        ),
        'utf8',
    );
    assert.match(source, /@open-auto-focus\.prevent/);
    assert.match(source, /@input="editDay"/);
    assert.match(source, /@click="open = true"/);
    assert.match(source, /@change="[\s\S]*?changeMonth/);
    assert.match(source, /open\.value = false/);
    assert.match(source, /useNow/);
    assert.match(source, /period === 'current'/);
    assert.match(source, /period === 'past'/);
    assert.match(source, /period === 'future'/);
    assert.doesNotMatch(source, /PopoverTrigger|context\?: string/);
});

test('desktop inline pickers lock the page month and hide context while the complete form stays unchanged', async () => {
    const page = await readFile(
        new URL('resources/js/pages/transactions/Show.vue', root),
        'utf8',
    );
    const inlinePickers = page.match(/<TransactionDatePicker\b[\s\S]*?\/>/g);
    assert.equal(inlinePickers.length, 2);

    for (const picker of inlinePickers) {
        assert.match(picker, /:fixed-month="month"/);
        assert.match(picker, /hide-context/);
        assert.match(picker, /hide-label/);
        assert.match(picker, /class="w-28"/);
    }

    const sheet = await readFile(
        new URL(
            'resources/js/components/transactions/TransactionFormSheet.vue',
            root,
        ),
        'utf8',
    );
    const formPicker = sheet.match(/<TransactionDatePicker\b[\s\S]*?\/>/)[0];
    assert.doesNotMatch(formPicker, /fixed-month|hide-context|hide-label/);
    const component = await readFile(
        new URL(
            'resources/js/components/transactions/TransactionDatePicker.vue',
            root,
        ),
        'utf8',
    );
    assert.match(component, /v-if="fixedMonth === undefined"/);
    assert.match(component, /v-if="!hideContext"/);
    assert.match(component, /props.fixedMonth !== undefined \|\|\s*month/);
    assert.match(component, /date.month !== props.fixedMonth/);
});
