import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test(
    'annual history loads bounded chunks and focuses old occurrences on desktop and mobile',
    {
        skip: !process.env.RECURRING_HISTORY_BROWSER_MODULE_PATH,
        timeout: 120000,
    },
    async () => {
        const { chromium } = await import(
            pathToFileURL(
                process.env.RECURRING_HISTORY_BROWSER_MODULE_PATH +
                    '/index.mjs',
            )
        );
        const directory = process.env.RECURRING_HISTORY_FIXTURE;
        const entryUuid = readFileSync(directory + '/entry.txt', 'utf8');
        const highlight = readFileSync(directory + '/highlight.txt', 'utf8');
        const requests = [];
        const server = await createServer({
            configFile: false,
            root: process.cwd(),
            cacheDir: '/tmp/soamco-recurring-history-vite',
            resolve: { alias: { '@': resolve('resources/js') } },
            optimizeDeps: {
                entries: ['resources/js/pages/transactions/recurring/Show.vue'],
            },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'recurring-history-fixture',
                    resolveId(id) {
                        if (id === '/history-fixture.js') {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/history-fixture.js') {
                            return `
                    import { createApp,h } from 'vue';
                    import { createInertiaApp } from '@inertiajs/vue3';
                    import { createAppI18n } from '/resources/js/i18n/index.ts';
                    import Show from '/resources/js/pages/transactions/recurring/Show.vue';
                    import '/resources/css/app.css';
                    createInertiaApp({resolve:()=>Show,progress:false,setup({el,App,props,plugin}){createApp({render:()=>h(App,props)}).use(plugin).use(createAppI18n(props.initialPage.props)).mount(el)}});
                `;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use((req, res, next) => {
                            const url = new URL(req.url, 'http://local');

                            if (
                                !url.pathname.startsWith('/recurring-entries/')
                            ) {
                                return next();
                            }

                            if (url.pathname.endsWith('/payment-candidates')) {
                                res.setHeader(
                                    'Content-Type',
                                    'application/json',
                                );
                                res.end(
                                    readFileSync(
                                        directory + '/candidates.json',
                                    ),
                                );

                                return;
                            }

                            if (url.searchParams.has('occurrence_year')) {
                                requests.push(url.search);
                                res.setHeader(
                                    'Content-Type',
                                    'application/json',
                                );
                                const file = `${directory}/${url.searchParams.get('occurrence_year')}-${url.searchParams.get('occurrence_offset') ?? 'focus'}.json`;
                                setTimeout(
                                    () => res.end(readFileSync(file)),
                                    200,
                                );

                                return;
                            }

                            const html = readFileSync(
                                directory +
                                    (url.searchParams.has('highlight')
                                        ? '/deep.html'
                                        : '/initial.html'),
                                'utf8',
                            );
                            const match = html.match(/data-page="([^"]+)"/);
                            assert.ok(match);
                            res.setHeader('Content-Type', 'text/html');
                            res.end(
                                '<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app" data-page="' +
                                    match[1] +
                                    '"></div><script type="module" src="/history-fixture.js"></script></body></html>',
                            );
                        });
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 5193, hmr: false, watch: null },
        });
        let browser;

        try {
            await server.listen();
            browser = await chromium.launch({
                channel: 'chrome',
                headless: true,
            });

            for (const width of [1440, 375]) {
                const context = await browser.newContext({
                    viewport: { width, height: 1000 },
                    reducedMotion: 'reduce',
                });
                const page = await context.newPage();
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                const base =
                    'http://127.0.0.1:5193/recurring-entries/' + entryUuid;
                await page.goto(base);
                const group = (year) => page.locator(`[data-year="${year}"]`);
                await group(2026)
                    .getByRole('button', { expanded: true })
                    .waitFor();
                assert.deepEqual(
                    await page
                        .locator('[data-year]')
                        .evaluateAll((groups) =>
                            groups.map((group) => Number(group.dataset.year)),
                        ),
                    [2026, 2025, 2024],
                );
                assert.match(
                    await group(2024).innerText(),
                    /Importi non corrisposti/,
                );

                if (process.env.RECURRING_HISTORY_SCREENSHOTS) {
                    await group(2024).screenshot({
                        path: `${process.env.RECURRING_HISTORY_SCREENSHOTS}/recurring-overdue-year-${width}.png`,
                    });
                }

                assert.equal(
                    await group(2024).locator('[data-overdue-year]').count(),
                    1,
                );
                assert.equal(
                    await group(2024)
                        .getByRole('button', { expanded: false })
                        .count(),
                    1,
                );
                assert.equal(
                    await group(2024).locator('[data-occurrence-uuid]').count(),
                    0,
                );
                const visibleRows = (year) =>
                    group(year).locator(
                        width < 640
                            ? 'article[data-occurrence-uuid]'
                            : 'tr[data-occurrence-uuid]',
                    );
                assert.equal(await visibleRows(2026).count(), 7);
                const more = group(2026).getByRole('button', {
                    name: 'Scadenze precedenti',
                    exact: true,
                });
                await more.scrollIntoViewIfNeeded();
                const scrollBefore = await page.evaluate(() => scrollY);
                const beforeRequests = requests.length;
                await more.evaluate((button) => {
                    button.click();
                    button.click();
                });
                await page.waitForFunction(
                    () =>
                        document.querySelectorAll(
                            '[data-year="2026"] article[data-occurrence-uuid]',
                        ).length === 19,
                );
                assert.equal(requests.length - beforeRequests, 1);
                assert.equal(await visibleRows(2026).count(), 19);
                await group(2026)
                    .getByRole('button', {
                        name: 'Scadenze precedenti',
                        exact: true,
                    })
                    .click();
                await page.waitForFunction(
                    () =>
                        document.querySelectorAll(
                            '[data-year="2026"] article[data-occurrence-uuid]',
                        ).length === 24,
                );
                assert.equal(await visibleRows(2026).count(), 24);
                assert.ok(
                    Math.abs(
                        (await page.evaluate(() => scrollY)) - scrollBefore,
                    ) < 100,
                );
                await group(2025)
                    .getByRole('button', { expanded: false })
                    .click();
                await visibleRows(2025).first().waitFor();
                assert.equal(await visibleRows(2025).count(), 3);
                const row = visibleRows(2026).first();

                if (process.env.RECURRING_HISTORY_SCREENSHOTS) {
                    await row.screenshot({
                        path: `${process.env.RECURRING_HISTORY_SCREENSHOTS}/recurring-paid-${width}.png`,
                    });
                }

                assert.match(await row.innerText(), /Già pagato/);
                await row
                    .getByRole('button', {
                        name: 'Collega pagamento esistente',
                        exact: true,
                    })
                    .click();
                await page
                    .getByRole('button', { name: /Pagamento già registrato/ })
                    .click();

                if (width < 640) {
                    assert.match(
                        await page
                            .getByRole('button', {
                                name: 'Importo da collegare',
                                exact: true,
                            })
                            .innerText(),
                        /40,00/,
                    );
                } else {
                    assert.equal(
                        await page
                            .getByRole('textbox', {
                                name: 'Importo da collegare',
                            })
                            .inputValue(),
                        '40,00',
                    );
                }

                await page
                    .getByRole('button', { name: 'Conferma collegamento' })
                    .waitFor();
                await page.keyboard.press('Escape');

                assert.match(await row.innerText(), /Apri ultimo pagamento/);
                assert.doesNotMatch(
                    await row.innerText(),
                    /Nessuna transazione/,
                );
                assert.match(await row.innerText(), /Residuo/);
                await page
                    .getByRole('button', { name: /Vedi storico importi/ })
                    .click();
                assert.equal(
                    await page
                        .locator('#amount-history-details article')
                        .count(),
                    4,
                );
                assert.match(
                    await page.locator('#amount-history-details').innerText(),
                    /↑/,
                );
                assert.match(
                    await page.locator('#amount-history-details').innerText(),
                    /↓/,
                );
                assert.ok(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                );

                if (process.env.RECURRING_HISTORY_SCREENSHOTS) {
                    await page
                        .locator('[data-testid="plan-amount-history"]')
                        .screenshot({
                            path: `${process.env.RECURRING_HISTORY_SCREENSHOTS}/recurring-amounts-${width}.png`,
                        });
                    await page.screenshot({
                        path: `${process.env.RECURRING_HISTORY_SCREENSHOTS}/recurring-history-${width}.png`,
                        fullPage: true,
                    });
                }

                await page.goto(base + '?highlight=' + highlight);
                await visibleRows(2024).first().waitFor();
                assert.equal(
                    await group(2024)
                        .getByRole('button', { expanded: true })
                        .count(),
                    1,
                );
                assert.equal(await visibleRows(2024).count(), 12);
                assert.equal(
                    await page.evaluate(() =>
                        document.activeElement?.getAttribute(
                            'data-occurrence-uuid',
                        ),
                    ),
                    highlight,
                );
                assert.equal(
                    await group(2025).locator('[data-occurrence-uuid]').count(),
                    0,
                );
                assert.ok(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                );
                assert.equal(
                    await group(2026)
                        .getByRole('button', { expanded: true })
                        .count(),
                    1,
                );
                await visibleRows(2026).first().waitFor();
                assert.equal(await visibleRows(2026).count(), 7);
                assert.deepEqual(errors, []);
                await context.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
