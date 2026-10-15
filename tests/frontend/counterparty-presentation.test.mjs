import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

// Real server responses from the isolated Pest fixture; no operational database.
test(
    'person presentation and shared tabs at desktop and mobile widths',
    {
        skip:
            !process.env.SOAMCO_PRESENTATION_FIXTURE_DIR ||
            !process.env.COUNTERPARTY_BROWSER_MODULE_PATH,
        timeout: 90000,
    },
    async () => {
        const { chromium } = await import(
            pathToFileURL(
                process.env.COUNTERPARTY_BROWSER_MODULE_PATH + '/index.mjs',
            )
        );
        const directory = process.env.SOAMCO_PRESENTATION_FIXTURE_DIR;
        const output =
            process.env.SOAMCO_PRESENTATION_SCREENSHOTS ??
            '/tmp/soamco-presentation-screenshots';
        mkdirSync(output, { recursive: true });
        const payload = (name) => {
            const data = JSON.parse(
                readFileSync(`${directory}/${name}.json`, 'utf8'),
            );

            for (const pagination of [
                data.props.activity,
                data.props.pagination,
            ]) {
                if (!pagination) {
                    continue;
                }

                for (const key of ['prev_page_url', 'next_page_url']) {
                    if (pagination[key]) {
                        const parsed = new URL(
                            pagination[key],
                            'http://localhost',
                        );
                        pagination[key] = parsed.pathname + parsed.search;
                    }
                }
            }

            return data;
        };
        let requests = 0;
        const server = await createServer({
            configFile: false,
            cacheDir: '/tmp/soamco-presentation-vite-cache',
            optimizeDeps: {
                entries: [
                    'resources/js/pages/SharedExpenses.vue',
                    'resources/js/pages/counterparties/Show.vue',
                ],
            },
            root: process.cwd(),
            resolve: { alias: { '@': resolve('resources/js') } },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'counterparty-presentation-fixture',
                    resolveId(id) {
                        if (id === '/fixture-entry.js') {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/fixture-entry.js') {
                            return `
                            import { createApp, h } from 'vue';
                            import { createInertiaApp } from '@inertiajs/vue3';
                            import { createAppI18n } from '/resources/js/i18n/index.ts';
                            import SharedExpenses from '/resources/js/pages/SharedExpenses.vue';
                            import Counterparty from '/resources/js/pages/counterparties/Show.vue';
                            import '/resources/css/app.css';
                            createInertiaApp({
                                resolve: name => name === 'SharedExpenses' ? SharedExpenses : Counterparty,
                                progress: false,
                                setup({el, App, props, plugin}) {
                                    document.documentElement.removeAttribute('data-session-unverified');
                                    document.getElementById('session-state')?.remove();
                                    document.getElementById('soamco-startup')?.remove();
                                    createApp({ render: () => h(App, props) }).use(plugin).use(createAppI18n(props.initialPage.props)).mount(el);
                                }
                            }).catch(error => console.error(error));
                        `;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use((req, res, next) => {
                            const url = new URL(req.url, 'http://localhost');

                            if (url.pathname === '/session/identity') {
                                res.setHeader(
                                    'Content-Type',
                                    'application/json',
                                );

                                return res.end(
                                    JSON.stringify({
                                        user_uuid:
                                            payload('detail').props.auth.user
                                                .uuid,
                                    }),
                                );
                            }

                            const shared = url.pathname === '/shared-expenses';

                            if (
                                !shared &&
                                !url.pathname.startsWith('/counterparties/')
                            ) {
                                return next();
                            }

                            const name = shared
                                ? `shared-${url.searchParams.get('effect') ?? 'pending'}`
                                : url.searchParams.get('page') === '2'
                                  ? 'detail-page2'
                                  : 'detail';
                            const data = payload(name);
                            data.url = req.url;

                            if (req.headers['x-inertia']) {
                                requests++;
                                res.setHeader(
                                    'Content-Type',
                                    'application/json',
                                );
                                res.setHeader('X-Inertia', 'true');
                                res.end(JSON.stringify(data));
                            } else {
                                const html = readFileSync(
                                    `${directory}/${name}.html`,
                                    'utf8',
                                )
                                    .replace(
                                        /data-page="[^"]*"/,
                                        () =>
                                            `data-page="${JSON.stringify(data).replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"`,
                                    )
                                    .replace(
                                        '</head>',
                                        '<script type="module" src="/fixture-entry.js"></script></head>',
                                    );
                                res.setHeader('Content-Type', 'text/html');
                                res.end(html);
                            }
                        });
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 5189, hmr: false, watch: null },
        });
        let browser;

        try {
            await server.listen();
            const url = `http://127.0.0.1:${server.httpServer.address().port}`;
            browser = await chromium.launch({
                headless: true,
                channel: process.env.COUNTERPARTY_BROWSER_CHANNEL ?? 'chrome',
            });
            const errors = [];

            for (const width of [1440, 1024, 375]) {
                const context = await browser.newContext({
                    viewport: { width, height: 1000 },
                    reducedMotion: 'reduce',
                });
                await context.addInitScript(() => {
                    localStorage.setItem(
                        'soamco-budget-cookie-consent',
                        JSON.stringify({
                            version: 1,
                            updatedAt: new Date().toISOString(),
                            preferences: {
                                necessary: true,
                                preferences: false,
                                analytics: false,
                                marketing: false,
                            },
                        }),
                    );
                });
                const page = await context.newPage();

                page.on('pageerror', (error) => {
                    errors.push(error.message);
                });
                page.on('console', (message) => {
                    if (message.type() === 'error') {
                        console.error(message.text());
                    }
                });
                await page.goto(
                    url + new URL(payload('detail').url, url).pathname,
                );

                await page
                    .getByRole('heading', { name: 'Mario Rossi', exact: true })
                    .waitFor();
                await page
                    .getByRole('heading', {
                        name: 'Movimenti e pagamenti',
                        exact: true,
                    })
                    .waitFor();
                assert.equal(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                    true,
                    `detail overflow at ${width}`,
                );
                await page.screenshot({
                    path: `${output}/detail-${width}.png`,
                });
                await page
                    .getByRole('heading', {
                        name: 'Movimenti e pagamenti',
                        exact: true,
                    })
                    .evaluate((node) =>
                        node.scrollIntoView({ block: 'start' }),
                    );
                await page.screenshot({
                    path: `${output}/timeline-${width}.png`,
                });
                await page
                    .getByRole('button', { name: 'Successiva', exact: true })
                    .click();
                await page.waitForURL(/page=2/);
                assert.equal(await page.locator('article').count(), 1);
                await page.goto(
                    url + '/shared-expenses?effect=settled&month=10&year=2026',
                );
                const tab = page.getByRole('button', {
                    name: 'Da sistemare',
                    exact: true,
                });
                await tab.waitFor();
                await tab.scrollIntoViewIfNeeded();
                const y = await page.evaluate(() => scrollY);
                const previousRequests = requests;
                await tab.click();
                await page.waitForURL(/effect=pending/);
                assert.equal(
                    requests - previousRequests,
                    1,
                    'one request per tab',
                );
                assert.ok(
                    Math.abs((await page.evaluate(() => scrollY)) - y) < 3,
                    'tab preserves scroll',
                );
                assert.equal(
                    new URL(page.url()).searchParams.get('month'),
                    '10',
                );
                assert.equal(
                    new URL(page.url()).searchParams.get('year'),
                    '2026',
                );
                assert.equal(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                    true,
                    `shared overflow at ${width}`,
                );
                assert.equal(
                    await tab.evaluate(
                        (node) => node === document.activeElement,
                    ),
                    true,
                    'tab keeps focus',
                );
                await page
                    .getByRole('heading', {
                        name: 'Rapporti con le persone',
                        exact: true,
                    })
                    .evaluate((node) =>
                        node.scrollIntoView({ block: 'start' }),
                    );
                await page.screenshot({
                    path: `${output}/people-${width}.png`,
                });
                await page.evaluate(() => scrollTo(0, 0));
                await page.screenshot({
                    path: `${output}/shared-${width}.png`,
                });
                await context.close();
            }

            assert.deepEqual(errors, []);
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
