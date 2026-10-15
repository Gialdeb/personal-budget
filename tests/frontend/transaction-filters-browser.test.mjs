import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test(
    'refund chips and opt-in transaction filter persistence across navigation',
    {
        skip: !process.env.TRANSACTION_FILTER_BROWSER_MODULE_PATH,
        timeout: 60000,
    },
    async () => {
        const { chromium } = await import(
            pathToFileURL(
                process.env.TRANSACTION_FILTER_BROWSER_MODULE_PATH +
                    '/index.mjs',
            )
        );
        const entry = `
        import { createApp, reactive, computed, toRefs } from 'vue';
        import { createInertiaApp, Link } from '@inertiajs/vue3';
        import { createAppI18n } from '/resources/js/i18n/index.ts';
        import Toggle from '/resources/js/components/transactions/TransactionVisibilityToggle.vue';
        import Badges from '/resources/js/components/transactions/TransactionClassificationBadges.vue';
        import { useTransactionFilters } from '/resources/js/composables/useTransactionFilters.ts';
        import { transactionFilterDefaults } from '/resources/js/lib/transaction-filter-preferences.js';
        import { matchesTransactionDisplay } from '/resources/js/lib/transaction-presentation.js';
        import '/resources/css/app.css';
        const controls = [['income','income','Entrate'],['expense','expense','Uscite'],['opening','opening','Aperture contabili'],['refunded','refunded','Rimborsate'],['shared','shared','Condivise'],['deleted','deleted','Eliminate'],['recurring','recurring','Ricorrenze']];
        const rows = [
            {uuid:'income',direction:'income',direction_label:'Entrata',type:'Entrata',is_reimbursement:false},
            {uuid:'refund',direction:'income',direction_label:'Entrata',type:'Rimborso',kind:'refund',is_reimbursement:true,reimbursement_kind:'transaction_refund'},
            {uuid:'original',direction:'expense',direction_label:'Uscita',type:'Uscita',is_refunded:true,is_reimbursement:false},
            {uuid:'shared',direction:'income',direction_label:'Entrata',type:'Entrata',is_reimbursement:true,reimbursement_kind:'shared_expense_settlement',shared_expense_counterparty_uuid:'person'},
            {uuid:'recurring',direction:'expense',direction_label:'Uscita',type:'Uscita',is_recurring_transaction:true},
            {uuid:'opening',direction:'income',direction_label:'Entrata',type:'Entrata',is_opening_balance:true},
            {uuid:'deleted',direction:'expense',direction_label:'Uscita',type:'Uscita',is_deleted:true},
        ];
        const Fixture = {components:{Toggle,Badges,Link},setup(){
            const filters=reactive({...transactionFilterDefaults});
            const preferences=useTransactionFilters(toRefs(filters));
            const visible=computed(()=>rows.filter(row=>matchesTransactionDisplay(row,filters)&&(!row.is_deleted||filters.deleted)));
            return {filters,...preferences,controls,visible};
        },template:'<main class="mx-auto max-w-5xl space-y-4 p-4"><div class="flex flex-wrap gap-2"><Toggle v-for="[key,category,label] in controls" :key="key" v-model="filters[key]" :category="category" :label="label" /></div><button type="button" :aria-pressed="rememberFilters" @click="rememberFilters=!rememberFilters">{{rememberFilters ? "Filtri salvati" : "Ricorda filtri"}}</button><button v-if="customized||rememberFilters" type="button" @click="restoreDefaults">Ripristina predefiniti</button><Link href="/other">Altra pagina</Link><article v-for="row in visible" :key="row.uuid" :data-row="row.uuid" class="flex flex-wrap gap-2 rounded-xl border p-4"><Badges :transaction="row" /></article></main>'};
        const Other={components:{Link},template:'<main><h1>Altra pagina</h1><Link href="/transactions">Transazioni</Link></main>'};
        createInertiaApp({resolve:name=>name==='transactions/Show'?Fixture:Other,progress:false,setup({el,App,props,plugin}){createApp({render:()=>h(App,props)}).use(plugin).use(createAppI18n(props.initialPage.props)).mount(el)}});
    `.replace(
            'import { createApp, reactive, computed, toRefs }',
            'import { createApp, reactive, computed, toRefs, h }',
        );
        const server = await createServer({
            configFile: false,
            root: process.cwd(),
            cacheDir: '/tmp/soamco-transaction-filters-vite',
            resolve: {
                alias: {
                    '@': resolve('resources/js'),
                    vue: resolve('node_modules/vue/dist/vue.esm-bundler.js'),
                },
            },
            optimizeDeps: {
                entries: [
                    'resources/js/components/transactions/TransactionVisibilityToggle.vue',
                    'resources/js/components/transactions/TransactionClassificationBadges.vue',
                    'resources/js/composables/useTransactionFilters.ts',
                ],
            },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'transaction-filter-fixture',
                    resolveId(id) {
                        if (id === '/fixture.js') {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/fixture.js') {
                            return entry;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use((req, res, next) => {
                            if (
                                !['/transactions', '/other'].includes(
                                    new URL(req.url, 'http://local').pathname,
                                )
                            ) {
                                return next();
                            }

                            const data = {
                                component: req.url.startsWith('/transactions')
                                    ? 'transactions/Show'
                                    : 'Other',
                                url: req.url,
                                version: 'fixture',
                                props: {
                                    auth: { user: { uuid: 'fixture-user' } },
                                    locale: { current: 'it' },
                                },
                            };

                            if (req.headers['x-inertia']) {
                                res.setHeader(
                                    'Content-Type',
                                    'application/json',
                                );
                                res.setHeader('X-Inertia', 'true');
                                res.end(JSON.stringify(data));

                                return;
                            }

                            res.setHeader('Content-Type', 'text/html');
                            res.end(
                                '<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app" data-page="' +
                                    JSON.stringify(data)
                                        .replaceAll('&', '&amp;')
                                        .replaceAll('"', '&quot;') +
                                    '"></div><script type="module" src="/fixture.js"></script></body></html>',
                            );
                        });
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 5191, hmr: false, watch: null },
        });
        let browser;

        try {
            await server.listen();
            browser = await chromium.launch({
                headless: true,
                channel: 'chrome',
            });

            for (const width of [1440, 375]) {
                const context = await browser.newContext({
                    viewport: { width, height: 1000 },
                });
                const page = await context.newPage();
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                const url = 'http://127.0.0.1:5191';
                const chip = (name) =>
                    page.getByRole('checkbox', { name, exact: true });
                const count = async (expected) => {
                    await page.waitForFunction(
                        (expected) =>
                            document.querySelectorAll('article').length ===
                            expected,
                        expected,
                    );
                };
                const awayAndBack = async () => {
                    await page
                        .getByRole('link', {
                            name: 'Altra pagina',
                            exact: true,
                        })
                        .click();
                    await page
                        .getByRole('heading', { name: 'Altra pagina' })
                        .waitFor();
                    await page
                        .getByRole('link', { name: 'Transazioni', exact: true })
                        .click();
                    await chip('Entrate').waitFor();
                };
                await page.goto(url + '/transactions');
                await chip('Entrate').waitFor();
                await count(6);
                assert.equal(await chip('Rimborsate').isChecked(), false);
                assert.equal(
                    await page
                        .locator('[data-row="refund"]')
                        .getByText('Entrata', { exact: true })
                        .count(),
                    1,
                );
                assert.equal(
                    await page
                        .locator('[data-row="refund"]')
                        .getByText('Rimborsata', { exact: true })
                        .count(),
                    1,
                );
                await chip('Rimborsate').click();
                await count(2);
                assert.equal(
                    await page.locator('[data-row="income"]').count(),
                    0,
                );
                assert.equal(
                    await page.locator('[data-row="original"]').count(),
                    0,
                );
                await chip('Uscite').click();
                await count(2);
                await chip('Rimborsate').click();
                await count(4);
                await page
                    .getByRole('button', { name: 'Ripristina predefiniti' })
                    .click();
                await count(6);
                await chip('Uscite').click();
                await count(4);
                await page
                    .getByRole('link', { name: 'Altra pagina', exact: true })
                    .click();
                await page
                    .getByRole('heading', { name: 'Altra pagina' })
                    .waitFor();
                await page.goBack();
                await chip('Entrate').waitFor();
                await count(6);
                assert.equal(
                    new URL(page.url()).searchParams.has('tx_expense'),
                    false,
                );
                await awayAndBack();
                await count(6);
                assert.equal(await chip('Uscite').isChecked(), true);
                await chip('Uscite').click();
                await count(4);
                await page
                    .getByRole('button', { name: 'Ricorda filtri' })
                    .click();
                await page
                    .getByRole('button', { name: 'Filtri salvati' })
                    .waitFor();
                await awayAndBack();
                await count(4);
                assert.equal(await chip('Uscite').isChecked(), false);
                await chip('Rimborsate').click();
                await count(2);
                await awayAndBack();
                await count(2);
                assert.equal(await chip('Rimborsate').isChecked(), true);
                await page.goto(
                    url + '/transactions?tx_refunded=0&tx_expense=1',
                );
                assert.equal(
                    await page.evaluate(
                        () =>
                            JSON.parse(
                                localStorage.getItem(
                                    'soamco:transactions:filters:v1:fixture-user',
                                ),
                            ).filters.refunded,
                    ),
                    true,
                );
                await chip('Entrate').waitFor();
                await count(6);
                assert.equal(await chip('Uscite').isChecked(), true);
                assert.equal(await chip('Rimborsate').isChecked(), false);
                await page
                    .getByRole('button', { name: 'Ripristina predefiniti' })
                    .click();
                await count(6);
                assert.equal(
                    await page
                        .getByRole('button', { name: 'Ricorda filtri' })
                        .count(),
                    1,
                );
                assert.equal(
                    await page
                        .getByRole('button', { name: 'Ripristina predefiniti' })
                        .count(),
                    0,
                );
                assert.equal(
                    await page.evaluate(() =>
                        localStorage.getItem(
                            'soamco:transactions:filters:v1:fixture-user',
                        ),
                    ),
                    null,
                );
                assert.deepEqual(errors, []);
                await context.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
