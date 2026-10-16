import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test(
    'shared reference identity preserves logos and exposes accessible deterministic avatars',
    {
        skip: !process.env.BROWSER_MODULE_PATH,
        timeout: 60000,
    },
    async () => {
        const { chromium } = await import(
            pathToFileURL(`${process.env.BROWSER_MODULE_PATH}/index.mjs`)
        );
        const server = await createServer({
            configFile: false,
            root: process.cwd(),
            cacheDir: '/tmp/soamco-identity-vite-cache',
            optimizeDeps: {
                include: ['vue', 'reka-ui', 'clsx', 'tailwind-merge'],
            },
            resolve: { alias: { '@': resolve('resources/js') } },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'reference-identity-fixture',
                    resolveId(id) {
                        if (id === '/identity-fixture.js') {
                            return id;
                        }
                    },
                    load(id) {
                        if (id !== '/identity-fixture.js') {
                            return;
                        }

                        return `
                    import { createApp, h } from 'vue';
                    import Identity from '/resources/js/components/tracked-items/TrackedItemIdentity.vue';
                    import '/resources/css/app.css';
                    const logo = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="60" height="20"><rect width="60" height="20" fill="purple"/></svg>');
                    createApp({ render: () => h('main', { style: 'padding: 24px; display: grid; gap: 16px; max-width: 100%' }, [
                        h('div', { id: 'compact' }, h(Identity, { name: 'Banca Esempio', compact: true, showName: false })),
                        h('div', { id: 'same' }, h(Identity, { name: '  BANCA   ESEMPIO  ', compact: true })),
                        h('div', { id: 'named' }, h(Identity, { name: 'Élodie della Città', compact: true })),
                        h('div', { id: 'single' }, h(Identity, { name: 'Netflix' })),
                        h('div', { id: 'uppercase' }, h(Identity, { name: 'ß ß' })),
                        h('div', { id: 'empty' }, h(Identity, { name: '   ' })),
                        h('div', { id: 'logo' }, h(Identity, { name: 'Original logo', logoUrl: logo, compact: true, showName: false })),
                        h('div', { id: 'broken' }, h(Identity, { name: 'Missing Logo', logoUrl: '/missing-reference-logo.png' })),
                        h('div', { id: 'long' }, h(Identity, { name: 'RiferimentoMoltoLungo'.repeat(10), compact: true })),
                        h('button', { id: 'outside' }, 'Outside')
                    ]) }).mount('#app');
                `;
                    },
                    configureServer(server) {
                        server.middlewares.use(
                            '/identity-test',
                            (_req, res) => {
                                res.setHeader('Content-Type', 'text/html');
                                res.end(
                                    '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div id="app"></div><script type="module" src="/identity-fixture.js"></script></body></html>',
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
            const address = server.httpServer.address();
            browser = await chromium.launch({
                headless: true,
                timeout: 10000,
                executablePath: process.env.BROWSER_EXECUTABLE_PATH,
            });
            const page = await browser.newPage({
                viewport: { width: 1280, height: 900 },
            });
            page.setDefaultTimeout(5000);
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.goto(`http://127.0.0.1:${address.port}/identity-test`, {
                waitUntil: 'domcontentloaded',
            });
            await page.locator('#compact button').waitFor();
            assert.equal(
                await page.locator('#compact button').innerText(),
                'BE',
            );
            assert.equal(await page.locator('#compact').innerText(), 'BE');
            assert.match(
                await page.locator('#named').innerText(),
                /Élodie della Città/,
            );
            assert.equal(await page.locator('#named button').innerText(), 'ÉC');
            assert.equal(await page.locator('#single button').innerText(), 'N');
            assert.equal(
                Array.from(await page.locator('#uppercase button').innerText())
                    .length,
                2,
            );
            assert.equal(await page.locator('#empty button').innerText(), '?');
            assert.equal(
                await page
                    .locator('#compact button')
                    .getAttribute('aria-label'),
                'Banca Esempio',
            );
            const color = (selector) =>
                page
                    .locator(`${selector} button span`)
                    .evaluate((el) => getComputedStyle(el).backgroundColor);
            assert.equal(await color('#compact'), await color('#same'));
            const colors = await page
                .locator('button span[aria-hidden]')
                .evaluateAll((nodes) =>
                    nodes.map((node) => ({
                        bg: getComputedStyle(node).backgroundColor,
                        fg: getComputedStyle(node).color,
                    })),
                );
            const luminance = (rgb) =>
                rgb
                    .match(/\d+/g)
                    .slice(0, 3)
                    .map(Number)
                    .map((v) => v / 255)
                    .map((v) =>
                        v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4,
                    )
                    .reduce(
                        (sum, value, i) =>
                            sum + value * [0.2126, 0.7152, 0.0722][i],
                        0,
                    );

            for (const { bg, fg } of colors) {
                const [light, dark] = [luminance(bg), luminance(fg)].sort(
                    (a, b) => b - a,
                );
                assert.ok((light + 0.05) / (dark + 0.05) >= 4.5);
            }

            await page.locator('#compact button').hover();
            await page
                .getByRole('tooltip', { name: 'Banca Esempio', exact: true })
                .waitFor();
            await page.keyboard.press('Escape');
            await page.getByRole('tooltip').waitFor({ state: 'hidden' });
            await page.locator('#named button').focus();
            await page
                .getByRole('tooltip', {
                    name: 'Élodie della Città',
                    exact: true,
                })
                .waitFor();
            await page.locator('#outside').click();
            await page.getByRole('tooltip').waitFor({ state: 'hidden' });
            const image = page.locator('#logo img');
            assert.equal(await image.getAttribute('alt'), 'Original logo');
            assert.match(
                await image.getAttribute('class'),
                /rounded-md object-contain/,
            );
            assert.equal(await page.locator('#logo button').count(), 0);
            await page.locator('#broken button').waitFor();
            assert.equal(
                await page.locator('#broken button').innerText(),
                'ML',
            );
            const before = await color('#compact');
            await page.evaluate(() =>
                document.documentElement.classList.add('dark'),
            );
            assert.equal(await color('#compact'), before);
            const mobile = await browser.newContext({
                viewport: { width: 375, height: 812 },
                isMobile: true,
                hasTouch: true,
            });
            const mobilePage = await mobile.newPage();
            mobilePage.setDefaultTimeout(5000);
            await mobilePage.goto(
                `http://127.0.0.1:${address.port}/identity-test`,
            );
            await mobilePage.locator('#compact button').tap();
            await mobilePage
                .getByRole('tooltip', { name: 'Banca Esempio', exact: true })
                .waitFor();
            assert.ok(
                await mobilePage.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            );

            if (process.env.BROWSER_SCREENSHOT_PATH) {
                await mobilePage
                    .locator('[data-slot="tooltip-content"]')
                    .evaluate(async (element) => {
                        await Promise.all(
                            element
                                .getAnimations()
                                .map((animation) => animation.finished),
                        );
                    });
                await mobilePage.screenshot({
                    path: process.env.BROWSER_SCREENSHOT_PATH,
                    fullPage: true,
                });
            }

            await mobilePage.locator('#outside').tap();
            await mobilePage.getByRole('tooltip').waitFor({ state: 'hidden' });
            assert.deepEqual(errors, []);
            await mobile.close();
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
