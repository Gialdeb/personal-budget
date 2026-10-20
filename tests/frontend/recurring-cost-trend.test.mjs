import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createI18n } from 'vue-i18n';

const root = new URL('../../', import.meta.url);

test('cost trend starts collapsed without mounting a chart on desktop or mobile', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        resolve: { alias: { '@': new URL('resources/js', root).pathname } },
        server: { middlewareMode: true, hmr: false, ws: false, watch: null },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });

    try {
        const { default: Trend } = await server.ssrLoadModule(
            '/resources/js/components/recurring/RecurringCostTrend.vue',
        );
        const app = createSSRApp({
            render: () =>
                h(Trend, {
                    points: [
                        {
                            transaction_uuid: 'one',
                            date: '2026-01-10',
                            amount: 80,
                        },
                        {
                            transaction_uuid: 'two',
                            date: '2026-03-10',
                            amount: 145,
                        },
                    ],
                    currency: 'EUR',
                    direction: 'expense',
                }),
        });
        app.use(
            createI18n({
                legacy: false,
                locale: 'it',
                missingWarn: false,
                fallbackWarn: false,
                messages: {},
            }),
        );
        const html = await renderToString(app);
        assert.match(html, /aria-expanded="false"/);
        assert.match(html, /data-state="closed"/);
        assert.match(html, /2026/);
        assert.doesNotMatch(html, /role="img"|<canvas|80,00|145,00/);
        const component = await readFile(
            new URL(
                'resources/js/components/recurring/RecurringCostTrend.vue',
                root,
            ),
            'utf8',
        );
        const page = await readFile(
            new URL('resources/js/pages/transactions/recurring/Show.vue', root),
            'utf8',
        );
        assert.match(component, /const expanded = ref\(false\)/);
        assert.match(component, /ResizeObserver/);
        assert.match(component, /scrollIntoView/);
        assert.match(component, /chart\.value\.on\('click', onChartClick\)/);
        assert.match(component, /highlight: point\.transaction_uuid/);
        assert.match(
            component,
            /const point = visiblePoints\.value\[params\.dataIndex\]/,
        );
        assert.match(component, /v-if="years\.length > 1"/);
        assert.match(component, /v-for="point in visiblePoints\.filter/);
        assert.match(page, /@click="costTrendPanel\?\.open\(\)"/);
    } finally {
        await server.close();
    }
});

test('latest payment comparison handles increases decreases unchanged payments and a zero baseline', async () => {
    const { latestPaymentComparison } =
        await import('../../resources/js/lib/recurring-cost-trend.js');
    const payments = (...amounts) => amounts.map((amount) => ({ amount }));

    assert.deepEqual(latestPaymentComparison(payments(100, 125)), {
        amount: 125,
        changeRatio: 0.25,
    });
    assert.deepEqual(latestPaymentComparison(payments(200, 150)), {
        amount: 150,
        changeRatio: -0.25,
    });
    assert.deepEqual(latestPaymentComparison(payments(80, 80)), {
        amount: 80,
        changeRatio: 0,
    });
    assert.deepEqual(latestPaymentComparison(payments(0, 100)), {
        amount: 100,
        changeRatio: null,
    });
    assert.deepEqual(latestPaymentComparison(payments(100)), {
        amount: 100,
        changeRatio: null,
    });
    assert.deepEqual(latestPaymentComparison([]), {
        amount: null,
        changeRatio: null,
    });
    assert.equal(
        latestPaymentComparison(
            payments(145, 151.5, 143.8, 205, 197.3),
        ).changeRatio.toFixed(4),
        '-0.0376',
    );
});

test('axis labels show payment months and disambiguate years without changing payment dates', async () => {
    const { paymentMonthLabels } =
        await import('../../resources/js/lib/recurring-cost-trend.js');
    const points = [
        { date: '2026-01-10' },
        { date: '2026-03-20' },
        { date: '2026-09-10' },
    ];

    assert.deepEqual(paymentMonthLabels(points, 'it'), ['gen', 'mar', 'set']);
    assert.deepEqual(paymentMonthLabels(points, 'en'), ['Jan', 'Mar', 'Sep']);
    assert.deepEqual(
        paymentMonthLabels(
            [{ date: '2025-12-10' }, { date: '2026-01-10' }],
            'it',
        ),
        ['dic 25', 'gen 26'],
    );
    assert.equal(points[0].date, '2026-01-10');
});

test('year periods use available years and scope all indicators and transaction indices to the same payments', async () => {
    const {
        paymentYears,
        paymentsForYear,
        paymentStatistics,
        latestPaymentComparison,
        paymentMonthLabels,
    } = await import('../../resources/js/lib/recurring-cost-trend.js');
    const points = [
        { transaction_uuid: 'latest', date: '2026-09-10', amount: 150 },
        { transaction_uuid: 'old', date: '2024-01-10', amount: 50 },
        { transaction_uuid: 'previous', date: '2026-01-10', amount: 100 },
    ];
    assert.deepEqual(paymentYears(points), ['2024', '2026']);
    const selected = paymentsForYear(points, '2026');
    assert.deepEqual(
        selected.map((point) => point.transaction_uuid),
        ['previous', 'latest'],
    );
    assert.deepEqual(paymentStatistics(selected), {
        minimum: 100,
        maximum: 150,
        average: 125,
    });
    assert.deepEqual(latestPaymentComparison(selected), {
        amount: 150,
        changeRatio: 0.5,
    });
    const old = paymentsForYear(points, '2024');
    assert.deepEqual(paymentStatistics(old), {
        minimum: 50,
        maximum: 50,
        average: 50,
    });
    assert.deepEqual(latestPaymentComparison(old), {
        amount: 50,
        changeRatio: null,
    });
    const all = paymentsForYear(points, 'all');
    assert.deepEqual(
        all.map((point) => point.transaction_uuid),
        ['old', 'previous', 'latest'],
    );
    assert.equal(paymentStatistics(all).average, 100);
    assert.deepEqual(paymentMonthLabels(selected, 'it', true), [
        'gen 26',
        'set 26',
    ]);
    assert.deepEqual(paymentStatistics(paymentsForYear(points, '2025')), {
        minimum: null,
        maximum: null,
        average: null,
    });
    assert.equal(points[0].transaction_uuid, 'latest');
});

test('manual and automatic price points remain distinct while payment statistics only use real transactions', async () => {
    const {
        costChartPoints,
        realPaymentPoints,
        paymentsForYear,
        paymentStatistics,
        paymentYears,
    } = await import('../../resources/js/lib/recurring-cost-trend.js');
    const real = [
        { transaction_uuid: 'paid', date: '2026-01-10', amount: 100 },
    ];
    const manual = [
        { uuid: 'manual', date: '2026-01-10', amount: 100, currency: 'EUR' },
        { uuid: 'older', date: '1999-01-01', amount: 500, currency: 'EUR' },
    ];
    const automatic = [
        { id: 1, effective_from: '2026-01-10', new_amount: 120 },
    ];
    const variable = costChartPoints(real, manual, automatic, false);
    assert.equal(variable.length, 3);
    assert.equal(new Set(variable.map((point) => point.key)).size, 3);
    assert.deepEqual(paymentYears(variable), ['1999', '2026']);
    const fixed = costChartPoints(real, manual, automatic, true);
    assert.equal(fixed.length, 4);
    assert.equal(
        fixed.filter((point) => point.date === '2026-01-10').length,
        3,
    );
    assert.deepEqual(paymentStatistics(realPaymentPoints(fixed)), {
        minimum: 100,
        maximum: 100,
        average: 100,
    });
    const manualOnlyYear = paymentsForYear(fixed, '1999');
    assert.equal(manualOnlyYear.length, 1);
    assert.equal(realPaymentPoints(manualOnlyYear).length, 0);
    assert.equal(
        fixed.find((point) => point.source === 'real').transaction_uuid,
        'paid',
    );
    assert.equal(
        fixed.find((point) => point.key === 'manual-manual').manual_uuid,
        'manual',
    );
    const component = await readFile(
        new URL(
            'resources/js/components/recurring/RecurringCostTrend.vue',
            root,
        ),
        'utf8',
    );
    const sheet = await readFile(
        new URL(
            'resources/js/components/recurring/RecurringManualCostSheet.vue',
            root,
        ),
        'utf8',
    );
    assert.match(component, /v-if="visiblePayments.length"/);
    assert.match(component, /point\?\.source === 'manual' && props\.canEdit/);
    assert.match(sheet, /isMobile \? 'bottom' : 'right'/);
    assert.match(sheet, /<AppDatePicker/);
    assert.match(sheet, /<MobileAmountInput/);
});

test('compound annual chart preserves original year totals and year navigation without transaction destinations', async () => {
    const {
        annualCostChartPoints,
        paymentYears,
        paymentsForYear,
        realPaymentPoints,
    } = await import('../../resources/js/lib/recurring-cost-trend.js');
    const points = annualCostChartPoints([
        { year: 2024, total: 200 },
        { year: 2025, total: 240 },
        { year: 2026, total: 50 },
    ]);
    assert.deepEqual(paymentYears(points), ['2024', '2025', '2026']);
    assert.deepEqual(
        paymentsForYear(points, '2025').map((point) => point.amount),
        [240],
    );
    assert.equal(paymentsForYear(points, 'all').length, 3);
    assert.deepEqual(realPaymentPoints(points), []);
    assert.ok(points.every((point) => !point.transaction_uuid));
    const component = await readFile(
        new URL(
            'resources/js/components/recurring/RecurringCostTrend.vue',
            root,
        ),
        'utf8',
    );
    const page = await readFile(
        new URL('resources/js/pages/transactions/recurring/Show.vue', root),
        'utf8',
    );
    assert.match(component, /v-for="year in visibleAnnualSummaries"/);
    assert.match(component, /v-if="year.comparison"/);
    assert.match(component, /component\.archived/);
    assert.match(
        component,
        /annualMode\.value\s*\? point\.date\.slice\(0, 4\)/,
    );
    assert.match(page, /:annual-summaries=/);
    const header = page.slice(
        page.indexOf('data-compound-plan-actions'),
        page.indexOf('<div v-else class="flex flex-wrap gap-2">'),
    );
    assert.match(header, /costTrendPanel\?\.open/);
    assert.match(header, /recurringDetail\.compound\.add/);
});

test('compound annual trend reuses the collapsed responsive chart and exposes only payment years', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        resolve: { alias: { '@': new URL('resources/js', root).pathname } },
        server: { middlewareMode: true, hmr: false, ws: false, watch: null },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });

    try {
        const { default: Trend } = await server.ssrLoadModule(
            '/resources/js/components/recurring/RecurringCostTrend.vue',
        );
        const app = createSSRApp({
            render: () =>
                h(Trend, {
                    points: [],
                    currency: 'EUR',
                    direction: 'expense',
                    annualSummaries: [
                        {
                            year: 2025,
                            total: 240,
                            complete: true,
                            components: [],
                            comparison: null,
                        },
                    ],
                }),
        });
        app.use(
            createI18n({
                legacy: false,
                locale: 'it',
                missingWarn: false,
                fallbackWarn: false,
                messages: {},
            }),
        );
        const html = await renderToString(app);
        assert.match(html, /aria-expanded="false"/);
        assert.match(html, /2025/);
        assert.doesNotMatch(html, /<canvas|240,00|role="img"/);
    } finally {
        await server.close();
    }
});

test('ordinary and compound trend titles follow the actual direction in Italian and English', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        resolve: { alias: { '@': new URL('resources/js', root).pathname } },
        server: { middlewareMode: true, hmr: false, ws: false, watch: null },
        optimizeDeps: { noDiscovery: true, include: [] },
        appType: 'custom',
    });

    try {
        const { default: Trend } = await server.ssrLoadModule(
            '/resources/js/components/recurring/RecurringCostTrend.vue',
        );
        const { recurringDetailMessages } = await server.ssrLoadModule(
            '/resources/js/i18n/messages/recurring-detail.ts',
        );
        const expected = {
            it: {
                expense: 'Andamento delle spese',
                income: 'Andamento delle entrate',
            },
            en: { expense: 'Expense trend', income: 'Income trend' },
        };

        for (const locale of ['it', 'en']) {
            for (const direction of ['expense', 'income']) {
                for (const compound of [false, true]) {
                    const app = createSSRApp({
                        render: () =>
                            h(Trend, {
                                points: [],
                                currency: 'EUR',
                                direction,
                                ...(compound ? { annualSummaries: [] } : {}),
                            }),
                    });
                    app.use(
                        createI18n({
                            legacy: false,
                            locale,
                            messages: {
                                [locale]: {
                                    recurringDetail:
                                        recurringDetailMessages[locale],
                                },
                            },
                        }),
                    );
                    const html = await renderToString(app);
                    assert.ok(
                        html.includes(expected[locale][direction]),
                        `${locale} ${direction} compound=${compound}`,
                    );
                    assert.ok(
                        !html.includes(
                            expected[locale][
                                direction === 'income' ? 'expense' : 'income'
                            ],
                        ),
                    );
                }
            }
        }

        const component = await readFile(
            new URL(
                'resources/js/components/recurring/RecurringCostTrend.vue',
                root,
            ),
            'utf8',
        );
        assert.match(component, /:aria-label="title"/);
        const page = await readFile(
            new URL('resources/js/pages/transactions/recurring/Show.vue', root),
            'utf8',
        );
        assert.match(
            page,
            /ref="costTrendPanel"\s+:direction="entry.direction"/,
        );
    } finally {
        await server.close();
    }
});
