import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { matchesSharedLedgerDisplay } from '../../resources/js/lib/transaction-presentation.js';

const source = readFileSync('resources/js/pages/transactions/Show.vue', 'utf8');
const component = readFileSync(
    'resources/js/components/transactions/SharedLedgerMovements.vue',
    'utf8',
);
const movement = (payer, date = '2026-10-10', extra = {}) => ({
    uuid: `${payer}-${date}`,
    date,
    is_shared_ledger: true,
    shared_expense_paid_by: 'counterparty',
    shared_expense_counterparty_uuid: payer,
    shared_expense_counterparty_label: payer.toUpperCase(),
    ...extra,
});

test('box options use only actual monthly advances and remain independent of the current payer filter', async () => {
    const props = reactive({ year: 2026, month: 10 });
    const sheet = ref({
        editor: {
            shared_expense_counterparties: [
                { value: 'no-advance', label: 'Unused' },
            ],
        },
        shared_ledger_movements: [
            movement('b'),
            movement('a'),
            movement('a', '2026-10-11'),
            movement('previous-month', '2026-09-30'),
            movement('next-month', '2026-11-01'),
            movement('self-paid', '2026-10-10', {
                shared_expense_paid_by: 'user',
            }),
            movement('bank', '2026-10-10', { is_shared_ledger: false }),
        ],
    });
    const selectedAdvancedBy = ref('all');
    const block = source.slice(
        source.indexOf('const sharedLedgerCounterparties = computed'),
        source.indexOf('function matchesSharedLedgerFilters'),
    );
    const code = ts.transpile(block, { target: ts.ScriptTarget.ES2022 });
    const options = new Function(
        'computed',
        'watch',
        'sheet',
        'props',
        'locale',
        'selectedAdvancedBy',
        'matchesSharedLedgerDisplay',
        `${code}; return sharedLedgerCounterparties;`,
    )(
        computed,
        watch,
        sheet,
        props,
        ref('it'),
        selectedAdvancedBy,
        matchesSharedLedgerDisplay,
    );
    assert.deepEqual(
        options.value.map((option) => option.value),
        ['a', 'b'],
    );
    selectedAdvancedBy.value = 'a';
    await nextTick();
    assert.deepEqual(
        options.value.map((option) => option.value),
        ['a', 'b'],
    );
    props.month = 11;
    await nextTick();
    assert.deepEqual(
        options.value.map((option) => option.value),
        ['next-month'],
    );
    assert.equal(selectedAdvancedBy.value, 'all');
    selectedAdvancedBy.value = 'next-month';
    sheet.value = {
        ...sheet.value,
        shared_ledger_movements: [movement('next-month', '2026-12-01')],
    };
    props.month = 12;
    await nextTick();
    assert.equal(selectedAdvancedBy.value, 'next-month');
    props.year = 2027;
    await nextTick();
    assert.deepEqual(options.value, []);
    assert.equal(selectedAdvancedBy.value, 'all');
});

test('empty results retain the box and its local selector and reset without touching transaction filters', () => {
    const box = source.match(/<SharedLedgerMovements\b[\s\S]*?\/>/)[0];
    assert.match(
        box,
        /v-if="\s*showShared &&\s*sharedLedgerCounterparties.length > 0\s*"/,
    );
    assert.doesNotMatch(box, /v-if="[^"]*filteredSharedLedgerMovements/);
    assert.match(
        component,
        /<p v-else[\s\S]*?role="status"[\s\S]*?transactions\.sharedLedger\.empty/,
    );
    assert.match(
        component,
        /v-if="counterparty !== 'all'"[\s\S]*?@click="counterparty = 'all'"/,
    );
    assert.match(component, /<SearchableSelect[\s\S]*?v-model="counterparty"/);
    const generalFilters = source.match(
        /useTransactionFilters\(\{[\s\S]*?}\)/,
    )[0];
    assert.doesNotMatch(generalFilters, /selectedAdvancedBy|advancedBy/);
    const reset = source.match(
        /function resetFilters\(\): void \{[\s\S]*?\n}/,
    )[0];
    assert.doesNotMatch(reset, /selectedAdvancedBy/);
});

test(
    'box stays usable with no matching rows and reset stays local on desktop and mobile',
    {
        skip: !process.env.BROWSER_MODULE_PATH,
        timeout: 120000,
    },
    async () => {
        const [
            { createServer },
            { default: vue },
            { default: tailwind },
            { resolve },
            { pathToFileURL },
        ] = await Promise.all([
            import('vite'),
            import('@vitejs/plugin-vue'),
            import('@tailwindcss/vite'),
            import('node:path'),
            import('node:url'),
        ]);
        const block = source.slice(
            source.indexOf('const sharedLedgerCounterparties = computed'),
            source.indexOf('function matchesSharedLedgerFilters'),
        );
        const rows = [
            movement('a'),
            movement('b', '2026-10-11'),
            movement('next-month', '2026-11-01'),
        ];
        const fixture = /* language=TEXT */ `<script setup lang="ts">import {computed,reactive,ref,watch} from 'vue';import {useI18n} from 'vue-i18n';import SharedLedgerMovements from '@/components/transactions/SharedLedgerMovements.vue';import {matchesSharedLedgerDisplay} from '@/lib/transaction-presentation.js';const {locale}=useI18n();const props=reactive({year:2026,month:10});const sheet=ref({editor:{shared_expense_counterparties:[{value:'unused',label:'Unused'}]},shared_ledger_movements:${JSON.stringify(rows)}});const selectedAdvancedBy=ref('all');const showShared=ref(true);const day=ref('2026-10-10');const generalSearch=ref('unchanged');${block}const filteredSharedLedgerMovements=computed(()=>sheet.value.shared_ledger_movements.filter(movement=>matchesSharedLedgerDisplay(movement,{shared:true,refunded:false,day:day.value,period:props.year+'-'+String(props.month).padStart(2,'0'),advancedBy:selectedAdvancedBy.value,category:'all',macrogroup:'all',account:'all'})));</script><template><main><label>Giorno transazioni<input v-model="day" /></label><label>Ricerca transazioni<input v-model="generalSearch" /></label><button @click="props.month=11;day=''">Mese successivo</button><button @click="props.month=12;day=''">Mese senza anticipi</button><output data-payer>{{selectedAdvancedBy}}</output><SharedLedgerMovements v-if="showShared && sharedLedgerCounterparties.length > 0" v-model:counterparty="selectedAdvancedBy" :movements="filteredSharedLedgerMovements" :counterparties="sharedLedgerCounterparties" currency="EUR" :can-edit="false" /></main></template>`;
        const server = await createServer({
            configFile: false,
            cacheDir: '/tmp/shared-ledger-box-vite',
            resolve: { alias: { '@': resolve('resources/js') } },
            optimizeDeps: {
                noDiscovery: true,
                include: [
                    'vue',
                    'vue-i18n',
                    '@vueuse/core',
                    'lucide-vue-next',
                    'reka-ui',
                    '@inertiajs/vue3',
                    'clsx',
                    'tailwind-merge',
                ],
            },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'shared-ledger-fixture',
                    resolveId(id) {
                        if (
                            [
                                '/shared-ledger-fixture.vue',
                                '/shared-ledger-fixture.js',
                            ].includes(id)
                        ) {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/shared-ledger-fixture.vue') {
                            return fixture;
                        }

                        if (id === '/shared-ledger-fixture.js') {
                            return `import {createApp} from 'vue';import {createAppI18n} from '/resources/js/i18n/index.ts';import App from '/shared-ledger-fixture.vue';import '/resources/css/app.css';createApp(App).use(createAppI18n({locale:{current:'it'}})).mount('#app');`;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use(
                            '/shared-ledger-fixture',
                            (req, res, next) => {
                                if (req.url !== '/') {
                                    next();

                                    return;
                                }

                                res.setHeader('Content-Type', 'text/html');
                                res.end(
                                    /* language=TEXT */ '<html lang="it"><body><div id="app"></div><script type="module" src="/shared-ledger-fixture.js"></script></body></html>',
                                );
                            },
                        );
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 0 },
        });
        let browser;

        try {
            await server.listen();
            const { chromium } = await import(
                pathToFileURL(process.env.BROWSER_MODULE_PATH + '/index.mjs')
            );
            browser = await chromium.launch({
                headless: true,
                executablePath: process.env.BROWSER_EXECUTABLE_PATH,
            });

            for (const width of [375, 1440]) {
                const page = await browser.newPage({
                    viewport: { width, height: 900 },
                });
                await page.goto(
                    `http://127.0.0.1:${server.httpServer.address().port}/shared-ledger-fixture/`,
                );
                const box = page.locator(
                    '[data-test="shared-ledger-movements"]',
                );
                await box.waitFor();
                assert.equal(await box.locator('li').count(), 1);
                await box.locator('button').first().click();
                assert.equal(
                    await page
                        .getByRole('button', { name: 'Unused', exact: true })
                        .count(),
                    0,
                );
                assert.equal(
                    await page
                        .getByRole('button', {
                            name: 'NEXT-MONTH',
                            exact: true,
                        })
                        .count(),
                    0,
                );
                await page
                    .getByRole('button', { name: 'B', exact: true })
                    .click();
                await box.getByRole('status').waitFor();
                assert.equal(await box.isVisible(), true);
                await box
                    .getByRole('button', {
                        name: 'Tutte le spese del mese',
                        exact: true,
                    })
                    .click();
                await box.locator('li').waitFor();
                assert.equal(
                    await page.locator('[data-payer]').innerText(),
                    'all',
                );
                assert.equal(
                    await page.getByLabel('Giorno transazioni').inputValue(),
                    '2026-10-10',
                );
                assert.equal(
                    await page.getByLabel('Ricerca transazioni').inputValue(),
                    'unchanged',
                );
                await box.locator('button').first().click();
                await page
                    .getByRole('button', { name: 'B', exact: true })
                    .click();
                await page
                    .getByRole('button', {
                        name: 'Mese successivo',
                        exact: true,
                    })
                    .click();
                assert.equal(
                    await page.locator('[data-payer]').innerText(),
                    'all',
                );
                await box.locator('button').first().click();
                await page
                    .getByRole('button', { name: 'NEXT-MONTH', exact: true })
                    .waitFor();
                assert.equal(
                    await page
                        .getByRole('button', { name: 'B', exact: true })
                        .count(),
                    0,
                );
                await page.keyboard.press('Escape');
                await page
                    .getByRole('button', {
                        name: 'Mese senza anticipi',
                        exact: true,
                    })
                    .click();
                await box.waitFor({ state: 'hidden' });
                assert.equal(await box.count(), 0);
                await page.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
