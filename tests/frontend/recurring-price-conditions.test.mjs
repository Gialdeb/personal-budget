import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const occurrenceNotes = [
    ...(
        await readFile(
            'resources/js/pages/transactions/recurring/Show.vue',
            'utf8',
        )
    ).matchAll(/<p\b[^>]*data-service-price-note[\s\S]*?<\/p>/g),
].map((match) => match[0]);
assert.equal(occurrenceNotes.length, 2);
const detailSource = await readFile(
    'resources/js/pages/transactions/recurring/Show.vue',
    'utf8',
);
const toolbar = detailSource
    .slice(detailSource.indexOf('data-recurring-detail-panels'))
    .match(/<div\b[^>]*role="group"[\s\S]*?<\/div>/)[0];
const trendAction = detailSource
    .match(
        /<Button\s+v-if="hasCostTrend"[^>]*@click="costTrendPanel\?\.open\(\)"[\s\S]*?<\/Button>/,
    )[0]
    .replace('v-if="hasCostTrend"', '');
const fixture = `<script setup>
import {ref,computed} from 'vue';
import {useI18n} from 'vue-i18n';
import {ChartNoAxesCombined,ChevronDown,ArrowUpRight,ArrowDownLeft,ShieldCheck,Receipt} from 'lucide-vue-next';
import SensitiveValue from '@/components/SensitiveValue.vue';
import Trend from '@/components/recurring/RecurringCostTrend.vue';
import {usePage} from '@inertiajs/vue3';
import Price from '@/components/recurring/RecurringPriceConditions.vue';
import Manual from '@/components/recurring/RecurringManualCostSheet.vue';
import {Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription} from '@/components/ui/sheet';
import {Button} from '@/components/ui/button';
const manual=ref(false), long=ref(false), priceOpen=ref(false);
const page=usePage(); const conditions=computed(()=>page.props.price_conditions);
const {t}=useI18n(); const costTrendPanel=ref(null), priceSummaryPanel=ref(null), entry={uuid:'plan',currency:'EUR',direction:new URLSearchParams(location.search).get('direction') ?? 'expense'};
function formatMoney(value,currency='EUR'){return new Intl.NumberFormat('it-IT',{style:'currency',currency}).format(value);}
const points=[{transaction_uuid:'one',date:'2025-01-10',amount:10},{transaction_uuid:'two',date:'2026-03-10',amount:15}];
</script><template><main class="mx-auto max-w-3xl p-6">
<Button @click="priceOpen=true">{{t(entry.direction==='income'?'recurringDetail.income.priceConditions.manage':'recurringDetail.priceConditions.manage')}}</Button>${trendAction}
<section class="min-w-0 space-y-3 pb-1" data-recurring-detail-panels>
${toolbar}
<Price ref="priceSummaryPanel" collapsible-summary v-model:open="priceOpen" :conditions="conditions" :direction="entry.direction" recurring-entry-uuid="plan" :current-amount="10" currency="EUR" :can-edit="true" />
<Trend ref="costTrendPanel" hide-trigger :points="points" currency="EUR" :direction="entry.direction" recurring-entry-uuid="plan" :can-edit="true" />
</section><section class="mt-6 rounded-2xl border p-4" data-schedule-table><h2>Scadenze e storico</h2><p>9,99 €</p></section>
<Button @click="manual=true">Apri costo storico</Button><Button @click="long=true">Apri pannello lungo</Button>
<Manual v-model:open="manual" recurring-entry-uuid="plan" currency="EUR" :cost="null" />
<Sheet v-model:open="long"><SheetContent><SheetHeader><SheetTitle>Pannello lungo</SheetTitle><SheetDescription>Verifica scorrimento</SheetDescription></SheetHeader><form class="shrink-0 p-4" @submit.prevent><label v-for="i in 25" :key="i" class="block py-4">Campo {{i}}<input class="block border" :aria-label="'Campo '+i" /></label><Button type="submit">Salva pannello</Button></form></SheetContent></Sheet>
</main></template>`;

function serverOptions(browser = false) {
    return {
        configFile: false,
        root: process.cwd(),
        cacheDir: '/tmp/soamco-price-conditions-vite',
        resolve: { alias: { '@': resolve('resources/js') } },
        plugins: [
            vue(),
            ...(browser ? [tailwind()] : []),
            {
                name: 'price-conditions-fixture',
                resolveId(id) {
                    if (
                        [
                            '/price-fixture.vue',
                            '/price-entry.js',
                            '/price-notes.vue',
                        ].includes(id)
                    ) {
                        return id;
                    }
                },
                load(id) {
                    if (id === '/price-notes.vue') {
                        return `<script setup>import {useI18n} from 'vue-i18n'; import SensitiveValue from '@/components/SensitiveValue.vue'; const props=defineProps({rows:Array,direction:{default:'expense'}}); const {t}=useI18n(); const entry={currency:'EUR',direction:props.direction}; function formatMoney(amount,currency='EUR'){return new Intl.NumberFormat('it-IT',{style:'currency',currency}).format(amount);}</script><template><div><div v-for="(occurrence,index) in rows" :key="index" :data-row="index"><span>{{formatMoney(occurrence.expected_amount)}}</span>${occurrenceNotes[0]}${occurrenceNotes[1]}</div></div></template>`;
                    }

                    if (id === '/price-fixture.vue') {
                        return fixture;
                    }

                    if (id === '/price-entry.js') {
                        return `import {createApp,h} from 'vue'; import {createInertiaApp} from '@inertiajs/vue3'; import Fixture from '/price-fixture.vue'; import {createAppI18n} from '/resources/js/i18n/index.ts'; import '/resources/css/app.css'; createInertiaApp({page:{component:'Fixture',url:'/price-test',version:null,props:{auth:{user:{format_locale:'it-IT'}}}},resolve:()=>Fixture,progress:false,setup({el,App,props,plugin}){createApp({render:()=>h(App,props)}).use(plugin).use(createAppI18n({locale:{current:'it'}})).mount(el)}});`;
                    }
                },
                configureServer(server) {
                    server.middlewares.use('/price-test', (_req, res) => {
                        res.setHeader('Content-Type', 'text/html');
                        res.end(
                            '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app"></div><script type="module" src="/price-entry.js"></script></body></html>',
                        );
                    });
                },
            },
        ],
        server: browser
            ? { host: '127.0.0.1', port: 0, watch: null }
            : { middlewareMode: true, hmr: false, ws: false, watch: null },
        optimizeDeps: {
            noDiscovery: true,
            include: browser
                ? [
                      'vue',
                      'vue-i18n',
                      'reka-ui',
                      '@vueuse/core',
                      '@inertiajs/vue3',
                      'lucide-vue-next',
                      'v-calendar',
                      'class-variance-authority',
                      'clsx',
                      'tailwind-merge',
                      'echarts/core',
                      'echarts/charts',
                      'echarts/components',
                      'echarts/renderers',
                  ]
                : [],
        },
        appType: 'custom',
    };
}

test('guided summaries preserve old conditions without technical language or discounts', async () => {
    const server = await createServer(serverOptions());

    try {
        const { default: Price } = await server.ssrLoadModule(
            '/resources/js/components/recurring/RecurringPriceConditions.vue',
        );
        const { createAppI18n } = await server.ssrLoadModule(
            '/resources/js/i18n/index.ts',
        );
        async function render(
            conditions,
            currentAmount = 10,
            collapsibleSummary = false,
            direction = 'expense',
            locale = 'it',
        ) {
            const app = createSSRApp({
                render: () =>
                    h(Price, {
                        conditions,
                        direction,
                        currentAmount,
                        collapsibleSummary,
                        canEdit: false,
                        currency: 'EUR',
                        recurringEntryUuid: 'plan',
                    }),
            });
            app.use(createAppI18n({ locale: { current: locale } }));

            return renderToString(app);
        }

        for (const conditions of [
            null,
            {},
            { type: 'standard' },
            { list_price: 20, personal_price: 10 },
        ]) {
            assert.doesNotMatch(
                await render(conditions),
                /data-price-conditions-summary/,
            );
        }

        assert.match(await render({ type: 'locked' }), /Senza scadenza/);
        assert.match(
            await render({ type: 'locked_until', locked_until: '2027-01-01' }),
            /Fino al/,
        );
        const legacy = await render({ type: 'announced', new_amount: '12.99' });
        assert.match(legacy, /29,9%/);
        assert.match(legacy, /Prima/);
        const decreasing = await render({
            change_scope: 'personal',
            change_direction: 'decrease',
            personal_price: 10,
            new_amount: 8,
        });
        assert.match(decreasing, /-20%/);
        assert.match(decreasing, /diminuisce/);
        const combined = await render(
            {
                lock_mode: 'indefinite',
                list_price: 20,
                personal_price: 10,
                change_scope: 'personal',
                new_list_price: 25,
                new_amount: 15,
            },
            99,
        );
        assert.match(combined, /Senza scadenza/);
        assert.match(combined, /50%/);
        assert.match(combined, /25,00/);
        assert.doesNotMatch(
            combined,
            /listino|personale|risparmio|scont|promo/i,
        );
        const service = await render({
            list_price: 20,
            personal_price: 10,
            change_scope: 'list_only',
            new_list_price: 25,
        });
        assert.match(service, /25%/);
        assert.match(service, /Per te il prezzo non cambia/);
        const unknown = await render({
            change_scope: 'list_only',
            change_direction: 'increase',
            new_list_price: 25,
        });
        assert.match(unknown, /Prezzo precedente non disponibile/);
        assert.doesNotMatch(unknown, /Senza una data effettiva/);
        assert.doesNotMatch(unknown, /%/);
        const zero = await render(
            { change_scope: 'personal', new_amount: 12 },
            0,
        );
        assert.doesNotMatch(zero, /%/);
        assert.doesNotMatch(zero, /Infinity|NaN/);
        assert.match(legacy, /data-price-summary-card="increase"/);
        assert.match(legacy, /bg-orange-50/);
        assert.match(decreasing, /data-price-summary-card="decrease"/);
        assert.match(decreasing, /bg-emerald-50/);
        assert.match(combined, /data-price-summary-card="guarantee"/);
        assert.match(combined, /bg-sky-50/);

        for (const locale of ['it', 'en']) {
            for (const [changeDirection, next, delta, percentage, tone] of [
                ['increase', 15, '+5,00', '+50%', 'emerald'],
                ['decrease', 8, '-2,00', '-20%', 'orange'],
            ]) {
                const values = {
                    personal_price: 10,
                    list_price: 10,
                    change_scope: 'personal',
                    change_direction: changeDirection,
                    new_amount: next,
                    effective_from: '2028-01-01',
                };
                const snapshot = JSON.stringify(values);
                const income = await render(
                    values,
                    10,
                    false,
                    'income',
                    locale,
                );
                assert.match(income, new RegExp(`bg-${tone}-50`));
                assert.match(
                    income,
                    locale === 'it' ? /Riceverai/ : /You will receive/,
                );
                assert.match(
                    income,
                    locale === 'it'
                        ? /Maggiore entrata|Minore entrata/
                        : /Higher income|Lower income/,
                );
                assert.match(
                    income,
                    new RegExp(percentage.replace('+', '\\+')),
                );

                if (locale === 'it') {
                    assert.ok(income.includes(delta));
                }

                assert.doesNotMatch(
                    income.replace(/<[^>]*>/g, ''),
                    /prezzo|fornitor|servizio|risparmio|paghi|pagherai|pagamenti|price|supplier|service|saving|you pay/i,
                );
                assert.equal(JSON.stringify(values), snapshot);
                const unaffected = await render(
                    {
                        ...values,
                        change_scope: 'list_only',
                        new_list_price: next,
                    },
                    10,
                    false,
                    'income',
                    locale,
                );
                assert.match(
                    unaffected,
                    locale === 'it'
                        ? /Continui a ricevere|Per te l’importo non cambia/
                        : /You continue to receive|Your amount does not change/,
                );
                assert.doesNotMatch(
                    unaffected,
                    /prezzo|fornitor|servizio|risparmio|paghi|pagherai|supplier|service|saving/,
                );
            }
        }

        const incomeLegacyLock = await render(
            { type: 'locked' },
            10,
            false,
            'income',
        );
        assert.match(incomeLegacyLock, /L’importo che ricevi è garantito/);
        const incomeLegacyChange = await render(
            { type: 'announced', new_amount: '12.99' },
            10,
            false,
            'income',
        );
        assert.match(incomeLegacyChange, /29,9%/);
        assert.match(incomeLegacyChange, /bg-emerald-50/);
        const dated = await render({
            change_scope: 'list_only',
            new_list_price: 12.99,
            effective_from: '2026-10-15',
        });
        assert.doesNotMatch(dated, /scadenze precedenti non vengono annotate/);
        const equal = await render({
            personal_price: 10,
            change_scope: 'personal',
            new_amount: 10,
            change_direction: 'increase',
        });
        assert.match(equal, /data-price-summary-card="unchanged"/);
        const collapsed = await render(
            { change_scope: 'personal', new_amount: 15 },
            10,
            true,
        );
        assert.doesNotMatch(collapsed, /data-price-conditions-summary/);
        assert.match(detailSource, /costTrendPanel\?\.toggle\(\)/);
        assert.match(detailSource, /priceSummaryPanel.toggleSummary\(\)/);
        assert.match(detailSource, /collapsible-summary/);
        assert.match(detailSource, /hide-trigger/);
        const { default: Notes } =
            await server.ssrLoadModule('/price-notes.vue');
        const notesApp = createSSRApp({
            render: () =>
                h(Notes, {
                    rows: [
                        {
                            expected_amount: 9.99,
                            service_price_at_due_date: null,
                        },
                        {
                            expected_amount: 9.99,
                            service_price_at_due_date: 12.99,
                        },
                        {
                            expected_amount: 12.99,
                            service_price_at_due_date: null,
                        },
                        { expected_amount: 9.99, service_price_at_due_date: 0 },
                    ],
                }),
        });
        notesApp.use(createAppI18n({ locale: { current: 'it' } }));
        const notesHtml = await renderToString(notesApp);
        assert.equal(
            (notesHtml.match(/data-service-price-note/g) ?? []).length,
            4,
        );
        assert.match(notesHtml, /Invece di/);
        assert.match(notesHtml, /12,99/);
        assert.match(notesHtml, /0,00/);
        const noteRows = notesHtml.split(/data-row="\d"/).slice(1);
        assert.doesNotMatch(noteRows[0], /data-service-price-note/);
        assert.doesNotMatch(noteRows[2], /data-service-price-note/);
        const incomeNotesApp = createSSRApp({
            render: () =>
                h(Notes, {
                    direction: 'income',
                    rows: [
                        {
                            expected_amount: 9.99,
                            service_price_at_due_date: 12.99,
                        },
                        {
                            expected_amount: 9.99,
                            service_price_at_due_date: null,
                        },
                    ],
                }),
        });
        incomeNotesApp.use(createAppI18n({ locale: { current: 'it' } }));
        const incomeNotes = await renderToString(incomeNotesApp);
        assert.match(incomeNotes, /Importo di riferimento:/);
        assert.match(incomeNotes, /12,99/);
        assert.equal(
            (incomeNotes.match(/data-service-price-note/g) ?? []).length,
            2,
        );
        assert.doesNotMatch(incomeNotes, /Invece di|prezzo|servizio|risparmio/);
        const show = await readFile(
            'resources/js/pages/transactions/recurring/Show.vue',
            'utf8',
        );
        assert.equal(
            (show.match(/@click="priceConditionsOpen = true"/g) ?? []).length,
            2,
        );
        assert.match(show, /v-model:open="priceConditionsOpen"/);
    } finally {
        await server.close();
    }
});

test(
    'price editing and side panels remain usable on desktop and mobile',
    { skip: !process.env.BROWSER_MODULE_PATH, timeout: 90000 },
    async () => {
        const { chromium } = await import(
            pathToFileURL(`${process.env.BROWSER_MODULE_PATH}/index.mjs`)
        );
        const server = await createServer(serverOptions(true));
        let browser;

        try {
            await server.listen();
            browser = await chromium.launch({
                headless: true,
                executablePath: process.env.BROWSER_EXECUTABLE_PATH,
            });

            for (const width of [1440, 375]) {
                const context = await browser.newContext({
                    viewport: { width, height: 600 },
                    reducedMotion: 'reduce',
                });
                const page = await context.newPage();
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                const direction = width === 375 ? 'income' : 'expense';
                const copy = (text) =>
                    direction === 'income'
                        ? ({
                              'Gestisci prezzo': 'Gestisci importo',
                              'Il mio prezzo è bloccato.':
                                  'Il mio importo è garantito.',
                              'Il prezzo è aumentato.':
                                  'L’importo è aumentato.',
                              'Il prezzo è diminuito.':
                                  'L’importo è diminuito.',
                              'Sì, cambia anche per me':
                                  'Sì, cambia anche l’importo che ricevo',
                              'No, per me non cambia':
                                  'No, ricevo lo stesso importo',
                              'Modifica · Prezzo bloccato':
                                  'Modifica · Importo garantito',
                              'Modifica · Variazione di prezzo':
                                  'Modifica · Variazione dell’importo',
                              'Rimuovi · Prezzo bloccato':
                                  'Rimuovi · Importo garantito',
                              'Rimuovi · Variazione di prezzo':
                                  'Rimuovi · Variazione dell’importo',
                          }[text] ?? text)
                        : text;
                const trendLabel =
                    direction === 'income'
                        ? 'Andamento entrate'
                        : 'Andamento spese';
                await page.goto(
                    `http://127.0.0.1:${server.httpServer.address().port}/price-test?direction=${direction}`,
                );
                const chartToggle = page.getByRole('button', {
                    name: trendLabel,
                    exact: true,
                });
                await chartToggle.waitFor();
                assert.equal(
                    await chartToggle.getAttribute('aria-expanded'),
                    'false',
                );
                assert.equal(await page.locator('canvas').count(), 0);
                assert.equal(
                    await page
                        .getByRole('button', {
                            name: /Prezzo (aumentato|diminuito|garantito)/,
                        })
                        .count(),
                    0,
                );
                await chartToggle.click();
                assert.equal(
                    await chartToggle.getAttribute('aria-expanded'),
                    'true',
                );
                await page
                    .locator('#recurring-cost-trend-panel canvas')
                    .waitFor();
                const chartPanel = page.getByRole('region', {
                    name: trendLabel,
                    exact: true,
                });
                await chartPanel
                    .getByRole('button', {
                        name: 'Anno precedente',
                        exact: true,
                    })
                    .click();
                await chartPanel
                    .getByRole('button', {
                        name: 'Anno successivo',
                        exact: true,
                    })
                    .click();
                await chartPanel
                    .getByRole('button', {
                        name: '+ Aggiungi costo storico',
                        exact: true,
                    })
                    .click();
                await page.getByRole('dialog').waitFor();
                await page.keyboard.press('Escape');
                await page.getByRole('dialog').waitFor({ state: 'hidden' });
                await chartToggle.click();
                assert.equal(
                    await chartToggle.getAttribute('aria-expanded'),
                    'false',
                );
                await page
                    .locator('#recurring-cost-trend-panel')
                    .waitFor({ state: 'hidden' });
                assert.equal(await page.locator('canvas').count(), 0);
                assert.match(
                    await chartToggle.getAttribute('class'),
                    direction === 'income'
                        ? /text-emerald-700/
                        : /text-sky-700/,
                );
                assert.match(
                    await chartToggle
                        .locator('span')
                        .first()
                        .getAttribute('class'),
                    direction === 'income' ? /bg-emerald-100/ : /bg-sky-100/,
                );
                await page.evaluate(() => {
                    window.trendScrolls = [];
                    Element.prototype.scrollIntoView = function (options) {
                        window.trendScrolls.push({
                            role: this.getAttribute('role'),
                            options,
                        });
                    };
                });
                const upperTrend = page.getByRole('button', {
                    name: 'Andamento',
                    exact: true,
                });

                for (let click = 0; click < 2; click++) {
                    await upperTrend.click();
                    await page.waitForFunction(
                        (count) => window.trendScrolls.length === count,
                        click + 1,
                    );
                    assert.equal(
                        await chartToggle.getAttribute('aria-expanded'),
                        'true',
                    );
                }

                const scrolls = await page.evaluate(() => window.trendScrolls);
                assert.ok(
                    scrolls.every(
                        (scroll) =>
                            scroll.role === 'img' &&
                            scroll.options.block === 'start',
                    ),
                );
                assert.match(
                    await chartPanel.locator('h3').getAttribute('class'),
                    direction === 'income'
                        ? /text-emerald-700/
                        : /text-sky-700/,
                );
                await chartToggle.click();
                await page
                    .locator('#recurring-cost-trend-panel')
                    .waitFor({ state: 'hidden' });
                const saved = [];
                await page.route(
                    '**/recurring-entries/plan/price-conditions',
                    async (route) => {
                        const data = route.request().postDataJSON();
                        saved.push(data);
                        const conditions = Object.fromEntries(
                            Object.entries(data).filter(
                                ([, value]) => value !== null && value !== '',
                            ),
                        );
                        await route.fulfill({
                            status: 200,
                            headers: {
                                'X-Inertia': 'true',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                component: 'Fixture',
                                url: '/price-test',
                                version: null,
                                props: {
                                    auth: { user: { format_locale: 'it-IT' } },
                                    price_conditions: Object.keys(conditions)
                                        .length
                                        ? conditions
                                        : null,
                                },
                            }),
                        });
                    },
                );
                assert.equal(
                    await page
                        .locator('[data-price-conditions-summary]')
                        .count(),
                    0,
                );
                const open = page.getByRole('button', {
                    name: copy('Gestisci prezzo'),
                    exact: true,
                });
                await open.click();
                const priceDialog = page.getByRole('dialog', {
                    name: copy('Gestisci prezzo'),
                    exact: true,
                });
                await priceDialog.waitFor();
                assert.match(
                    await priceDialog.innerText(),
                    direction === 'income' ? /Oggi ricevi/ : /Oggi paghi/,
                );

                if (direction === 'income') {
                    assert.doesNotMatch(
                        await priceDialog.innerText(),
                        /prezzo|fornitore|servizio|risparmio|paghi|pagherai|pagamenti/i,
                    );
                }

                assert.equal(
                    await priceDialog.getByRole('checkbox').count(),
                    0,
                );
                assert.equal(
                    await priceDialog.getByRole('combobox').count(),
                    0,
                );
                assert.equal(
                    await page.locator('#price-next-amount').count(),
                    0,
                );

                for (const name of [
                    'Il mio prezzo è bloccato.',
                    'Il prezzo è aumentato.',
                    'Il prezzo è diminuito.',
                ]) {
                    assert.equal(
                        await priceDialog
                            .getByRole('button', {
                                name: copy(name),
                                exact: true,
                            })
                            .count(),
                        1,
                    );
                }

                async function setAmount(value) {
                    if (width < 768) {
                        await page.locator('#price-next-amount').click();
                        const amountDialog = page.getByRole('dialog').last();
                        await amountDialog
                            .getByRole('button', {
                                name: 'Azzera',
                                exact: true,
                            })
                            .first()
                            .click();

                        for (const key of value) {
                            await amountDialog
                                .getByRole('button', { name: key, exact: true })
                                .click();
                        }

                        await amountDialog
                            .getByRole('button', { name: 'Salva', exact: true })
                            .click();
                        await page
                            .locator('[data-slot="sheet-content"]')
                            .waitFor({ state: 'hidden' });
                    } else {
                        await page.locator('#price-next-amount').fill('');
                        await page.locator('#price-next-amount').fill(value);
                        await page.locator('#price-next-amount').blur();
                    }
                }
                const apply = priceDialog.getByRole('button', {
                    name: 'Aggiungi al riepilogo',
                    exact: true,
                });
                const save = priceDialog.getByRole('button', {
                    name: 'Salva condizioni',
                    exact: true,
                });
                await priceDialog
                    .getByRole('button', {
                        name: copy('Il mio prezzo è bloccato.'),
                        exact: true,
                    })
                    .click();
                assert.equal(
                    await page.locator('#price-next-amount').count(),
                    0,
                );
                await priceDialog
                    .getByRole('button', {
                        name: 'Senza scadenza',
                        exact: true,
                    })
                    .click();
                await priceDialog
                    .locator('[data-price-preview]')
                    .getByText(
                        direction === 'income'
                            ? /garantito senza scadenza/
                            : /bloccato senza scadenza/,
                    )
                    .waitFor();
                await apply.click();
                assert.equal(
                    await priceDialog
                        .locator('[data-price-registered]')
                        .count(),
                    1,
                );
                await priceDialog
                    .getByRole('button', {
                        name: copy('Il prezzo è aumentato.'),
                        exact: true,
                    })
                    .click();
                assert.equal(
                    await page.locator('#price-next-amount').count(),
                    0,
                );
                await priceDialog
                    .getByRole('button', {
                        name: copy('Sì, cambia anche per me'),
                        exact: true,
                    })
                    .click();
                await setAmount('9');
                assert.equal(await apply.isDisabled(), true);
                await setAmount('15');
                assert.equal(await apply.isDisabled(), true);
                await priceDialog
                    .getByText(
                        direction === 'income'
                            ? /Modifica o rimuovi prima la garanzia/
                            : /Modifica o rimuovi prima il blocco/,
                    )
                    .waitFor();
                await priceDialog
                    .getByRole('button', { name: 'Indietro', exact: true })
                    .click();
                await priceDialog
                    .getByRole('button', {
                        name: copy('Modifica · Prezzo bloccato'),
                        exact: true,
                    })
                    .click();
                await priceDialog
                    .getByRole('button', {
                        name: 'Fino a una data',
                        exact: true,
                    })
                    .click();
                await page.locator('#price-locked-until').fill('01/01/2027');
                await page.locator('#price-locked-until').blur();
                await apply.click();
                await priceDialog
                    .getByRole('button', {
                        name: copy('Il prezzo è aumentato.'),
                        exact: true,
                    })
                    .click();
                await priceDialog
                    .getByRole('button', {
                        name: copy('Sì, cambia anche per me'),
                        exact: true,
                    })
                    .click();
                await setAmount('15');
                await page.locator('#price-effective-from').fill('01/01/2026');
                await page.locator('#price-effective-from').blur();
                assert.equal(await apply.isDisabled(), true);
                await page.locator('#price-effective-from').fill('01/01/2028');
                await page.locator('#price-effective-from').blur();
                await priceDialog
                    .locator('[data-price-preview]')
                    .getByText(/\(\+50%\)/)
                    .waitFor();
                assert.equal(
                    await page.locator('#price-effective-from').inputValue(),
                    '01/01/2028',
                );
                await apply.click();
                assert.equal(
                    await priceDialog
                        .locator('[data-price-registered] .rounded-xl')
                        .count(),
                    2,
                );
                assert.doesNotMatch(
                    await priceDialog.innerText(),
                    /listino|personale|risparmio|scont|promo/i,
                );
                const priceBox = await save.boundingBox();
                assert.ok(
                    priceBox.y >= 0 && priceBox.y + priceBox.height <= 600,
                );
                await page.screenshot({
                    path: `/tmp/soamco-price-${width}.png`,
                    fullPage: true,
                });
                await save.click();
                await priceDialog.waitFor({ state: 'hidden' });
                assert.equal(saved.at(-1).lock_mode, 'until');
                assert.equal(saved.at(-1).change_direction, 'increase');
                assert.equal(Number(saved.at(-1).new_amount), 15);
                const summary = page.locator('[data-price-conditions-summary]');
                const priceToggle = page.getByRole('button', {
                    name:
                        direction === 'income'
                            ? /Importo aumentato/
                            : /Prezzo aumentato/,
                });
                await priceToggle.waitFor();
                assert.equal(
                    await priceToggle.getAttribute('aria-expanded'),
                    'false',
                );
                assert.equal(await summary.count(), 0);
                await priceToggle.click();
                assert.equal(
                    await priceToggle.getAttribute('aria-expanded'),
                    'true',
                );
                await summary.getByText(/^\+50%$/).waitFor();
                assert.match(
                    await summary
                        .locator('[data-price-summary-card="increase"]')
                        .getAttribute('class'),
                    direction === 'income' ? /bg-emerald-50/ : /bg-orange-50/,
                );
                assert.match(
                    await priceToggle
                        .locator('span')
                        .first()
                        .getAttribute('class'),
                    direction === 'income' ? /bg-emerald-100/ : /bg-orange-100/,
                );

                if (direction === 'income') {
                    assert.doesNotMatch(
                        await summary.innerText(),
                        /prezzo|fornitore|servizio|risparmio|paghi|pagherai|pagamenti/i,
                    );
                }

                await priceToggle.click();
                assert.equal(
                    await priceToggle.getAttribute('aria-expanded'),
                    'false',
                );
                await summary.waitFor({ state: 'hidden' });
                await priceToggle.click();
                await summary.waitFor();
                assert.doesNotMatch(
                    await summary.innerText(),
                    /scadenze precedenti|ultima variazione|data effettiva/i,
                );

                for (const card of await summary
                    .locator('[data-price-summary-card]')
                    .all()) {
                    const bounds = await card.boundingBox();
                    assert.ok(
                        bounds.x >= 0 && bounds.x + bounds.width <= width + 1,
                    );
                    assert.equal(
                        await card.evaluate(
                            (el) => el.scrollWidth <= el.clientWidth + 1,
                        ),
                        true,
                    );
                }

                const bar = page
                    .locator('[data-recurring-detail-panels]')
                    .first();

                for (const button of await bar
                    .getByRole('group')
                    .getByRole('button')
                    .all()) {
                    const bounds = await button.boundingBox();
                    assert.ok(
                        bounds.x >= 0 && bounds.x + bounds.width <= width + 1,
                    );
                }

                const summaryBounds = await summary.boundingBox();
                const tableBounds = await page
                    .locator('[data-schedule-table]')
                    .boundingBox();
                assert.ok(
                    tableBounds.y >= summaryBounds.y + summaryBounds.height,
                );
                await page.screenshot({
                    path: `/tmp/soamco-price-summary-${width}.png`,
                    fullPage: true,
                });

                await open.click();
                assert.equal(
                    await page.locator('#price-next-amount').count(),
                    0,
                );
                assert.equal(
                    await priceDialog
                        .locator('[data-price-registered] .rounded-xl')
                        .count(),
                    2,
                );
                await priceDialog
                    .getByRole('button', {
                        name: copy('Modifica · Prezzo bloccato'),
                        exact: true,
                    })
                    .click();
                await priceDialog
                    .getByRole('button', {
                        name: 'Fino a una data',
                        exact: true,
                    })
                    .click();
                await page.locator('#price-locked-until').fill('01/01/2026');
                await page.locator('#price-locked-until').blur();
                await apply.click();
                await save.click();
                await priceDialog.waitFor({ state: 'hidden' });
                assert.equal(Number(saved.at(-1).new_amount), 15);
                assert.equal(saved.at(-1).lock_mode, 'until');
                await open.click();
                await priceDialog
                    .getByRole('button', {
                        name: copy('Modifica · Variazione di prezzo'),
                        exact: true,
                    })
                    .click();
                await priceDialog
                    .getByRole('button', {
                        name: copy('No, per me non cambia'),
                        exact: true,
                    })
                    .click();
                await setAmount('20');
                await priceDialog
                    .locator('[data-price-preview]')
                    .getByText(
                        direction === 'income'
                            ? /Importo precedente non disponibile/
                            : /Prezzo precedente non disponibile/,
                    )
                    .waitFor();
                await apply.click();
                await save.click();
                await priceDialog.waitFor({ state: 'hidden' });
                assert.equal(saved.at(-1).new_amount, null);
                assert.equal(Number(saved.at(-1).new_list_price), 20);
                await summary
                    .getByText(
                        direction === 'income'
                            ? /Per te l’importo non cambia/
                            : /Per te il prezzo non cambia/,
                    )
                    .waitFor();
                await open.click();
                await priceDialog
                    .getByRole('button', {
                        name: copy('Il prezzo è diminuito.'),
                        exact: true,
                    })
                    .click();
                await priceDialog
                    .getByRole('button', {
                        name: copy('Sì, cambia anche per me'),
                        exact: true,
                    })
                    .click();
                await setAmount('8');
                await priceDialog
                    .locator('[data-price-preview]')
                    .getByText(/-20%/)
                    .waitFor();
                await apply.click();
                await save.click();
                await priceDialog.waitFor({ state: 'hidden' });
                assert.equal(saved.at(-1).change_direction, 'decrease');
                assert.equal(Number(saved.at(-1).new_list_price), 20);
                assert.match(
                    await summary
                        .locator('[data-price-summary-card="decrease"]')
                        .getAttribute('class'),
                    direction === 'income' ? /bg-orange-50/ : /bg-emerald-50/,
                );
                await open.click();
                await priceDialog
                    .getByRole('button', {
                        name: copy('Rimuovi · Prezzo bloccato'),
                        exact: true,
                    })
                    .click();
                await priceDialog
                    .getByRole('button', {
                        name: copy('Rimuovi · Variazione di prezzo'),
                        exact: true,
                    })
                    .click();
                await save.click();
                await priceDialog.waitFor({ state: 'hidden' });
                assert.equal(await summary.count(), 0);
                await open.click();
                await priceDialog
                    .getByRole('button', {
                        name: copy('Il prezzo è aumentato.'),
                        exact: true,
                    })
                    .click();
                await priceDialog
                    .getByRole('button', { name: 'Annulla', exact: true })
                    .click();
                await open.click();
                assert.equal(
                    await page.locator('#price-next-amount').count(),
                    0,
                );
                assert.equal(
                    await priceDialog
                        .locator('[data-price-registered]')
                        .count(),
                    0,
                );
                await page.keyboard.press('Escape');
                await priceDialog.waitFor({ state: 'hidden' });
                await page
                    .getByRole('button', {
                        name: 'Apri costo storico',
                        exact: true,
                    })
                    .click();
                const dialog = page.getByRole('dialog');
                await dialog.waitFor();
                await dialog.evaluate((el) =>
                    Promise.all(
                        el
                            .getAnimations()
                            .map((animation) => animation.finished),
                    ),
                );
                const box = await dialog.boundingBox();
                assert.ok(
                    box.y >= -1 && box.y + box.height <= 601,
                    JSON.stringify({ width, box }),
                );

                if (width === 1440) {
                    assert.ok(box.height >= 599);
                }

                await dialog
                    .getByRole('button', { name: 'Salva', exact: true })
                    .scrollIntoViewIfNeeded();
                assert.ok(
                    await dialog
                        .getByRole('button', { name: 'Salva', exact: true })
                        .isVisible(),
                );
                await page.keyboard.press('Escape');
                await page
                    .getByRole('button', {
                        name: 'Apri pannello lungo',
                        exact: true,
                    })
                    .click();
                await dialog.waitFor();
                await dialog.evaluate((el) =>
                    Promise.all(
                        el
                            .getAnimations()
                            .map((animation) => animation.finished),
                    ),
                );
                await dialog
                    .getByRole('button', {
                        name: 'Salva pannello',
                        exact: true,
                    })
                    .scrollIntoViewIfNeeded();
                const saveBox = await dialog
                    .getByRole('button', {
                        name: 'Salva pannello',
                        exact: true,
                    })
                    .boundingBox();
                assert.ok(saveBox.y >= 0 && saveBox.y + saveBox.height <= 601);
                await dialog
                    .getByRole('button', {
                        name: 'Salva pannello',
                        exact: true,
                    })
                    .focus();
                assert.equal(
                    await dialog
                        .getByRole('button', {
                            name: 'Salva pannello',
                            exact: true,
                        })
                        .evaluate((el) => el === document.activeElement),
                    true,
                );
                await page.keyboard.press('Escape');
                assert.deepEqual(errors, []);
                await context.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
