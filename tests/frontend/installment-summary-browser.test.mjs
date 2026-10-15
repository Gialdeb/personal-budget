import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test(
    'installment summary updates totals, currency and labels on mobile and desktop',
    { skip: !process.env.BROWSER_MODULE_PATH, timeout: 120000 },
    async () => {
        const { chromium } = await import(
            pathToFileURL(process.env.BROWSER_MODULE_PATH + '/index.mjs')
        );
        const source = readFileSync(
            'resources/js/components/recurring/RecurringEntryFormSheet.vue',
            'utf8',
        );
        const loader = source.match(
            /<span\s+v-if="exchangePreviewLoading"[\s\S]*?<\/span>/,
        )[0];
        assert.doesNotMatch(
            source,
            /exchangePreviewLoading \|\|\s*exchangePreview\?\.should_preview/,
        );
        const loadingFixture =
            '<div class="relative grid gap-2"><label>Importo</label>' +
            loader +
            '<input value="15" /><input data-testid="following-field" value="12" /></div>';
        const entry = `import {createApp,ref} from 'vue';
import {LoaderCircle} from 'lucide-vue-next';
import Summary from '/resources/js/components/recurring/InstallmentPlanSummary.vue';
import {createAppI18n} from '/resources/js/i18n/index.ts';
import '/resources/css/app.css';
const app={components:{Summary,LoaderCircle},setup(){const exchangePreviewLoading=ref(false);window.setLoading=v=>exchangePreviewLoading.value=v;const amount=ref('15'),total=ref('180'),count=ref('12'),label=ref('Rata mensile');window.updatePlan=(a,t,c,l)=>{amount.value=a;total.value=t;count.value=c;label.value=l};return{amount,total,count,label,exchangePreviewLoading,t:()=> 'Caricamento'}},template:${JSON.stringify(`<main class="max-w-xl p-4">${loadingFixture}<Summary :amount="amount" :total="total" :count="count" :amount-label="label" currency="EUR" locale="it-IT" :income="false" /></main>`)}};
createApp(app).use(createAppI18n({locale:{current:'it'}})).mount('#app');`;
        const server = await createServer({
            configFile: false,
            root: process.cwd(),
            cacheDir: '/tmp/soamco-installment-vite',
            resolve: {
                alias: {
                    '@': resolve('resources/js'),
                    vue: resolve('node_modules/vue/dist/vue.esm-bundler.js'),
                },
            },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'fixture',
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
                            if (req.url === '/') {
                                res.setHeader('Content-Type', 'text/html');
                                res.end(
                                    '<html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div id="app"></div><script type="module" src="/fixture.js"></script></body></html>',
                                );
                            } else {
                                next();
                            }
                        });
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 5196, watch: null, hmr: false },
        });
        let browser;

        try {
            await server.listen();
            browser = await chromium.launch({
                channel: 'chrome',
                headless: true,
            });

            for (const width of [375, 1280]) {
                const page = await browser.newPage({
                    viewport: { width, height: 850 },
                });
                const errors = [];
                page.on('pageerror', (e) => errors.push(e.message));
                await page.goto('http://127.0.0.1:5196');
                await page.waitForTimeout(1500);
                await page.getByText('Totale piano', { exact: true }).waitFor();
                const before = await page
                    .getByTestId('following-field')
                    .boundingBox();
                await page.evaluate(() => window.setLoading(true));
                await page.getByTestId('amount-preview-loading').waitFor();
                assert.deepEqual(
                    await page.getByTestId('following-field').boundingBox(),
                    before,
                );
                await page.evaluate(() => window.setLoading(false));
                assert.match(
                    await page.getByTestId('installment-total').innerText(),
                    /180,00/,
                );
                await page.evaluate(() =>
                    window.updatePlan('27,50', '330', '12', 'Rata mensile'),
                );
                await page.waitForTimeout(300);
                assert.match(
                    await page.getByTestId('installment-total').innerText(),
                    /330,00/,
                );
                assert.match(
                    await page.getByTestId('installment-amount').innerText(),
                    /27,50/,
                );
                await page.evaluate(() =>
                    window.updatePlan('27,50', '660', '24', 'Importo per rata'),
                );
                await page.waitForTimeout(300);
                assert.equal(
                    await page.getByTestId('installment-count').innerText(),
                    '24',
                );
                assert.match(
                    await page.getByTestId('installment-total').innerText(),
                    /660,00/,
                );
                await page
                    .getByText('Importo per rata', { exact: true })
                    .waitFor();
                assert.equal(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                    true,
                );
                await page.screenshot({
                    path: `/Users/gialdeb/.codex/visualizations/2026/10/15/01a1401d-22f9-7d21-9e4b-3c329a78d53a/installment-${width}.png`,
                });
                await page.emulateMedia({ reducedMotion: 'reduce' });
                await page.evaluate(() =>
                    window.updatePlan('', '', '', 'Rata mensile'),
                );
                await page
                    .getByText('Inserisci importo e numero di rate')
                    .waitFor();
                await page.waitForTimeout(500);
                assert.equal(
                    await page.getByTestId('installment-total').innerText(),
                    '—',
                );
                assert.deepEqual(errors, []);
                await page.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
