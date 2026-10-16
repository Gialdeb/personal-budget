import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

const source = readFileSync(
    'resources/js/pages/transactions/recurring/Index.vue',
    'utf8',
);
const start = source.indexOf(
    '            <section\n',
    source.indexOf('<RecurringCalendarGrid'),
);
const end = source.indexOf(
    '                <div\n                    v-if="selectedDate"',
    start,
);
const filtersTemplate = source.slice(start, end) + '</section>';
const reset = source.slice(
    source.indexOf('function resetFilters()'),
    source.indexOf('function serverFilterValue('),
);

test(
    'recurring filters retain their controls in an accessible responsive layout',
    {
        skip: !process.env.BROWSER_MODULE_PATH,
        timeout: 60000,
    },
    async () => {
        const { chromium } = await import(
            pathToFileURL(`${process.env.BROWSER_MODULE_PATH}/index.mjs`)
        );
        const fixture = `<script setup lang="ts">
        import { computed, ref } from 'vue';
        import { useI18n } from 'vue-i18n';
        import { Filter, Search, X, RotateCcw, ChevronUp, ChevronDown } from 'lucide-vue-next';
        import { Button } from '@/components/ui/button';
        import { Input } from '@/components/ui/input';
        import { Label } from '@/components/ui/label';
        import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
        import { useUiCopy } from '@/i18n/ui-copy';
        import { cn } from '@/lib/utils';
        const { t } = useI18n(); const ui = useUiCopy();
        const isFiltersCollapsed = ref(false), searchQuery = ref('');
        const accountFilter = ref('all'), statusFilter = ref('all'), directionFilter = ref('all'), entryTypeFilter = ref('all'), conversionFilter = ref('all'), refundFilter = ref('all'), sharedExpenseFilter = ref('all');
        const groupedAccountFilterOptions = [{ key: 'payment', label: 'Conti', options: [{value: '1', label: 'Conto principale con un nome molto lungo', badgeLabel: 'Personale', badgeClass: 'bg-emerald-100 text-emerald-700'}] }];
        const selectedAccountFilterOption = computed(() => groupedAccountFilterOptions[0].options.find(option => option.value === accountFilter.value));
        function handleAccountSelection(value: string) { accountFilter.value = value; }
        function applySearch() {} function clearSearch() { searchQuery.value = ''; }
        ${reset}
        </script><template>${filtersTemplate}</template>`;
        const server = await createServer({
            configFile: false,
            root: process.cwd(),
            cacheDir: '/tmp/soamco-recurring-filters-vite',
            resolve: { alias: { '@': resolve('resources/js') } },
            optimizeDeps: {
                include: [
                    'vue',
                    'vue-i18n',
                    'lucide-vue-next',
                    'reka-ui',
                    '@vueuse/core',
                    'clsx',
                    'tailwind-merge',
                    'class-variance-authority',
                ],
            },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'recurring-filters-fixture',
                    resolveId(id) {
                        if (
                            id === '/filters-fixture.vue' ||
                            id === '/filters-entry.js'
                        ) {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/filters-fixture.vue') {
                            return fixture;
                        }

                        if (id === '/filters-entry.js') {
                            return `import { createApp } from 'vue'; import Filters from '/filters-fixture.vue'; import { createAppI18n } from '/resources/js/i18n/index.ts'; import '/resources/css/app.css'; createApp(Filters).use(createAppI18n({locale:{current:'it'}})).mount('#app');`;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use('/filters-test', (_req, res) => {
                            res.setHeader('Content-Type', 'text/html');
                            res.end(
                                '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="background:#f1f5f9"><main id="app" style="max-width:1280px;margin:24px auto;padding:0 16px"></main><script type="module" src="/filters-entry.js"></script></body></html>',
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
            browser = await chromium.launch({
                headless: true,
                executablePath: process.env.BROWSER_EXECUTABLE_PATH,
                timeout: 10000,
            });
            const address = server.httpServer.address();
            const errors = [];

            for (const width of [1440, 768, 375]) {
                const context = await browser.newContext({
                    viewport: { width, height: 1000 },
                    isMobile: width < 600,
                    hasTouch: width < 600,
                });
                const page = await context.newPage();
                page.setDefaultTimeout(5000);
                page.on('pageerror', (error) => errors.push(error.message));
                await page.goto(
                    `http://127.0.0.1:${address.port}/filters-test`,
                    { waitUntil: 'domcontentloaded' },
                );
                await page.locator('#recurring-search').waitFor();
                const controls = page.locator('[role="combobox"]');
                assert.equal(await controls.count(), 7);
                const visible = [];

                for (const control of await controls.all()) {
                    if (await control.isVisible()) {
                        visible.push(control);
                    }
                }

                assert.equal(visible.length, width < 768 ? 6 : 7);

                for (const control of visible) {
                    const box = await control.boundingBox();
                    assert.ok(
                        box.width > 100 && box.height >= 43,
                        JSON.stringify({ width, box }),
                    );
                    assert.ok(box.x + box.width <= width);
                }

                assert.ok(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <=
                            window.innerWidth,
                    ),
                );
                const search = await page
                    .locator('#recurring-search')
                    .boundingBox();
                assert.ok(
                    width < 768
                        ? search.width >= (await visible[0].boundingBox()).width
                        : search.width > (await visible[0].boundingBox()).width,
                );
                await page.locator('#recurring-search').fill('Affitto');
                await page
                    .getByRole('button', {
                        name: 'Cancella ricerca',
                        exact: true,
                    })
                    .click();
                assert.equal(
                    await page.locator('#recurring-search').inputValue(),
                    '',
                );
                const typeControl = visible[width < 768 ? 1 : 2];
                const initialType = await typeControl.innerText();
                await typeControl.click();
                await page.getByRole('option').last().click();
                await page
                    .getByRole('button', { name: 'Reset filtri', exact: true })
                    .click();
                assert.equal(await typeControl.innerText(), initialType);

                if (process.env.FILTERS_SCREENSHOT_DIR) {
                    await page.locator('section').screenshot({
                        path: `${process.env.FILTERS_SCREENSHOT_DIR}/filters-${width}.png`,
                    });
                }

                if (width < 768) {
                    const collapse = page.getByRole('button', {
                        expanded: true,
                    });
                    await collapse.click();
                    assert.ok(
                        await page.locator('#recurring-search').isVisible(),
                    );
                    assert.equal(await visible[0].isVisible(), false);
                    assert.equal(
                        await page
                            .getByRole('button', {
                                name: 'Reset filtri',
                                exact: true,
                            })
                            .isVisible(),
                        false,
                    );
                    await page.getByRole('button', { expanded: false }).click();
                    assert.ok(await visible[0].isVisible());
                }

                await context.close();
            }

            assert.deepEqual(errors, []);
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
