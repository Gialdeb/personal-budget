import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

const source = readFileSync(
    'resources/js/pages/transactions/recurring/Show.vue',
    'utf8',
);
const actions = source
    .slice(
        source.indexOf('<div v-else class="flex flex-wrap gap-2">'),
        source.indexOf('                    <RecurringPlanRefund'),
    )
    .replace('v-else', '')
    .trim()
    .replace(/<\/div>\s*$/, '');
const fixture = /* language=TEXT */ `<script setup>
import {ref,h} from 'vue';
import {ChartNoAxesCombined,Pencil,Play,Square,Pause,CircleX,Receipt,FileText,Undo2,Trash2,SlidersHorizontal} from 'lucide-vue-next';
import {Button} from '@/components/ui/button';
const Link={props:['href'],setup:(props,{slots})=>()=>h('a',{href:props.href},slots.default?.())};
const salary=new URLSearchParams(location.search).has('salary');
const entry={salary_url:salary?'/salary/test':null,can_edit:true,entry_type:'recurring',direction:'income'};
const props={recurringEntry:{actions:{can_cancel:true,can_pause:true,can_delete:true}},planRefund:null};
const hasCostTrend=true,distributionLoading=false;
const costTrendPanel={open(){}},planRefundPanel=null;
const reactivationMode=ref(false),formOpen=ref(false),priceConditionsOpen=ref(false),cancelDialogOpen=ref(false),deleteDialogOpen=ref(false);
const t=k=>({'salary.details':'Dettaglio stipendio','recurringDetail.costTrend.action':'Andamento','transactions.recurring.actions.edit':'Modifica ricorrenza','transactions.recurring.actions.cancel':'Annulla piano','recurringDetail.income.priceConditions.manage':'Gestisci importo','transactions.recurring.actions.delete':'Elimina piano'}[k]??k);
const ui=x=>x;function handlePause(){}
</script><template><main class="p-4">${actions}<p data-state>{{priceConditionsOpen}}/{{cancelDialogOpen}}/{{formOpen}}</p></main></template>`;

test('salary action retains its condition and destination beside price management in both action bars', () => {
    assert.equal((source.match(/v-if="entry.salary_url"/g) ?? []).length, 2);
    assert.equal((source.match(/:href="entry.salary_url"/g) ?? []).length, 2);
    assert.doesNotMatch(
        source.slice(
            source.indexOf('<div class="space-y-2">'),
            source.indexOf('data-compound-plan-actions'),
        ),
        /salary\.details/,
    );
    assert.match(
        actions,
        /priceConditionsOpen = true[\s\S]*?<Receipt[\s\S]*?<Link\s+v-if="entry.salary_url"[\s\S]*?<FileText/,
    );
    assert.match(actions, /cancelDialogOpen = true[\s\S]*?<CircleX/);
});

test(
    'detail actions wrap without clipping and retain interactions on desktop and mobile',
    { skip: !process.env.BROWSER_MODULE_PATH, timeout: 90000 },
    async () => {
        const server = await createServer({
            configFile: false,
            cacheDir: '/tmp/soamco-actions-vite',
            resolve: { alias: { '@': resolve('resources/js') } },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'actions-fixture',
                    resolveId(id) {
                        if (['/actions.vue', '/actions.js'].includes(id)) {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/actions.vue') {
                            return fixture;
                        }

                        if (id === '/actions.js') {
                            return `import {createApp} from 'vue';import App from '/actions.vue';import '/resources/css/app.css';createApp(App).mount('#app');`;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use('/actions-test', (_req, res) => {
                            res.setHeader('Content-Type', 'text/html');
                            res.end(
                                /* language=TEXT */ '<html lang="it"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app"></div><script type="module" src="/actions.js"></script></body></html>',
                            );
                        });
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 0, hmr: false },
            optimizeDeps: {
                include: [
                    'vue',
                    'reka-ui',
                    'lucide-vue-next',
                    'class-variance-authority',
                    'clsx',
                    'tailwind-merge',
                ],
            },
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

            for (const width of [1440, 375]) {
                const page = await browser.newPage({
                    viewport: { width, height: 900 },
                });
                const errors = [];
                page.on('pageerror', (e) => errors.push(e.message));

                for (const salary of [false, true]) {
                    await page.goto(
                        `http://127.0.0.1:${server.httpServer.address().port}/actions-test${salary ? '?salary' : ''}`,
                    );
                    await page
                        .getByRole('button', {
                            name: 'Gestisci importo',
                            exact: true,
                        })
                        .waitFor();
                    const link = page.getByRole('link', {
                        name: 'Dettaglio stipendio',
                    });
                    assert.equal(await link.count(), salary ? 1 : 0);

                    if (salary) {
                        assert.equal(
                            await link.getAttribute('href'),
                            '/salary/test',
                        );
                        assert.equal(await link.locator('svg').count(), 1);
                    }

                    for (const button of await page.locator('button,a').all()) {
                        const bounds = await button.boundingBox();
                        assert.ok(
                            bounds.x >= 0 && bounds.x + bounds.width <= width,
                        );
                        assert.equal(await button.locator('svg').count(), 1);
                    }

                    await page
                        .getByRole('button', {
                            name: 'Gestisci importo',
                            exact: true,
                        })
                        .click();
                    await page
                        .getByRole('button', {
                            name: 'Annulla piano',
                            exact: true,
                        })
                        .click();
                    await page
                        .getByRole('button', {
                            name: 'Modifica ricorrenza',
                            exact: true,
                        })
                        .click();
                    assert.equal(
                        await page.locator('[data-state]').innerText(),
                        'true/true/true',
                    );
                    assert.equal(
                        await page.evaluate(
                            () =>
                                document.documentElement.scrollWidth >
                                innerWidth,
                        ),
                        false,
                    );

                    if (salary) {
                        await page.screenshot({
                            path: `/tmp/soamco-detail-actions-${width}.png`,
                        });
                    }
                }

                assert.deepEqual(errors, []);
                await page.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
