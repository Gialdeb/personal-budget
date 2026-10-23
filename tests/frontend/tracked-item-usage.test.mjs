import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test(
    'settings reference counts show distinct direct and indirect entities without merging homonyms',
    {
        skip:
            !process.env.BROWSER_MODULE_PATH ||
            !process.env.TRACKED_ITEM_USAGE_FIXTURE,
        timeout: 120000,
    },
    async () => {
        const data = JSON.parse(
            readFileSync(process.env.TRACKED_ITEM_USAGE_FIXTURE, 'utf8'),
        );
        const fixture = /* language=TEXT */ `<script setup>import List from '@/components/tracked-items/TrackedItemsTreeList.vue';const data=${JSON.stringify(data)};</script><template><main class="p-4"><List :items="data.trackedItems.flat" :category-labels-by-uuid="{}" /></main></template>`;
        const server = await createServer({
            configFile: false,
            cacheDir: '/tmp/tracked-item-usage-vite',
            resolve: { alias: { '@': resolve('resources/js') } },
            optimizeDeps: {
                noDiscovery: true,
                include: [
                    'vue',
                    'vue-i18n',
                    '@vueuse/core',
                    'lucide-vue-next',
                    'reka-ui',
                    'clsx',
                    'tailwind-merge',
                ],
            },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'tracked-item-usage-fixture',
                    resolveId(id) {
                        if (
                            [
                                '/usage-fixture.vue',
                                '/usage-fixture.js',
                            ].includes(id)
                        ) {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/usage-fixture.vue') {
                            return fixture;
                        }

                        if (id === '/usage-fixture.js') {
                            return `import {createApp} from 'vue';import {createAppI18n} from '/resources/js/i18n/index.ts';import List from '/usage-fixture.vue';import '/resources/css/app.css';createApp(List).use(createAppI18n({locale:{current:'it'}})).mount('#app');`;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use('/usage-test', (req, res) => {
                            res.setHeader('Content-Type', 'text/html');
                            res.end(
                                /* language=TEXT */ '<html lang="it"><body><div id="app"></div><script type="module" src="/usage-fixture.js"></script></body></html>',
                            );
                        });
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
                    viewport: { width, height: 1000 },
                });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.goto(
                    `http://127.0.0.1:${server.httpServer.address().port}/usage-test`,
                );
                await page
                    .getByText('In uso · 7 collegamenti', { exact: true })
                    .waitFor();
                assert.equal(await page.locator('article').count(), 2);
                const counts = page
                    .locator('[data-reference-link-counts]')
                    .first();
                const text = await counts.innerText();

                for (const label of [
                    'Diretti: 5',
                    'Indiretti: 2',
                    'Spese condivise: 2',
                    'Ricorrenze: 1',
                    'Occorrenze: 1',
                    'Template: 1',
                    'Crediti e debiti: 1',
                ]) {
                    assert.ok(
                        text.includes(label),
                        `${width}: missing ${label}: ${text}`,
                    );
                }

                assert.equal(
                    await page.getByText('Mai usato', { exact: true }).count(),
                    1,
                );
                assert.equal(
                    await page.evaluate(
                        () => document.documentElement.scrollWidth > innerWidth,
                    ),
                    false,
                );
                assert.deepEqual(errors, []);
                await page.screenshot({
                    path: `/tmp/tracked-item-usage-${width}.png`,
                });
                await page.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
