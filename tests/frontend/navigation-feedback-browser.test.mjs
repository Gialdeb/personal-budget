import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

// Opt in with an existing Playwright installation; no application database required.
test(
    'real Inertia navigation feedback in desktop, mobile and standalone emulation',
    {
        skip: !process.env.NAVIGATION_BROWSER_MODULE_PATH,
        timeout: 120000,
    },
    async () => {
        const { chromium } = await import(
            pathToFileURL(
                process.env.NAVIGATION_BROWSER_MODULE_PATH + '/index.mjs',
            )
        );
        const props = {
            auth: { user: { is_admin: false } },
            features: {},
            locale: { current: 'it' },
        };
        const payload = (url) => ({
            component: 'Fixture',
            props,
            url,
            version: 'test',
        });
        const entry = `
        import { createApp, h } from 'vue';
        import { createInertiaApp, router } from '@inertiajs/vue3';
        import { createAppI18n } from '/resources/js/i18n/index.ts';
        import { initializeNavigationFeedback } from '/resources/js/composables/useNavigationFeedback.ts';
        import NavigationProgress from '/resources/js/components/NavigationProgress.vue';
        import NavMain from '/resources/js/components/NavMain.vue';
        import MobileBottomNav from '/resources/js/components/MobileBottomNav.vue';
        import SidebarProvider from '/resources/js/components/ui/sidebar/SidebarProvider.vue';
        import '/resources/css/app.css';
        const Fixture = { render: () => h('div', { style: 'transform:translateZ(0);overflow:hidden;height:400px' }, [
            h('header', {style:'position:fixed;top:0;z-index:100;background:white;width:100%;height:50px'}, 'Header'),
            h(SidebarProvider, {style:'padding-top:64px'}, {default: () => h(NavMain, {items:[{title:'Dashboard',href:'/'},{title:'Reports',href:'/reports'}]})}),
            h(MobileBottomNav)
        ]) };
        window.events = [];
        for (const name of ['start','finish','error','exception']) {
            router.on(name, () => window.events.push({name, at:performance.now()}));
        }
        router.on('exception', () => false);
        window.cancelNavigation = () => router.cancel();
        createInertiaApp({resolve: () => Fixture, progress:false, setup({el,App,props,plugin}) {
            initializeNavigationFeedback();
            createApp({render:()=>h('div',[h(App,props),h(NavigationProgress)])})
                .use(plugin).use(createAppI18n(props.initialPage.props)).mount(el);
        }});
    `;
        const workerConfig = {
            version: 'navigation-test',
            offline_url: '/offline',
            cache_prefix: 'navigation-test-',
            cache_names: {
                static: 'navigation-test-static',
                images: 'navigation-test-images',
            },
            precache_urls: [],
            static_asset_path_prefixes: ['/build/'],
            stable_image_path_prefixes: ['/pwa/'],
            firebase_messaging: null,
        };
        // Actual app worker, with only Blade values substituted and external Firebase SDKs stubbed.
        const worker = readFileSync(
            'resources/views/pwa/service-worker.blade.php',
            'utf8',
        )
            .replace(
                /const config = .*;/,
                `const config = ${JSON.stringify(workerConfig)};`,
            )
            .replace(/\{!! .*file_get_contents.* !!\}/g, () =>
                JSON.stringify('Offline'),
            )
            .replace("{{ $config['version'] }}", 'navigation-test')
            .replace("{{ $config['offline_url'] }}", '/offline')
            .replace(/self.importScripts\([\s\S]*?\);/g, '');
        assert.ok(!worker.includes('self.importScripts'));
        new Function(worker);
        let requests = 0;
        let outcome = 'success';
        const server = await createServer({
            configFile: false,
            root: process.cwd(),
            resolve: { alias: { '@': resolve('resources/js') } },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'navigation-fixture',
                    resolveId(id) {
                        if (id === '/fixture-entry.js') {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/fixture-entry.js') {
                            return entry;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use(async (req, res, next) => {
                            if (req.url === '/service-worker.js') {
                                res.setHeader(
                                    'Content-Type',
                                    'text/javascript',
                                );
                                res.end(
                                    `const firebase = { initializeApp() {}, messaging: () => ({ onBackgroundMessage() {} }) };\n${worker}`,
                                );

                                return;
                            }

                            if (req.headers['x-inertia']) {
                                requests++;
                                await new Promise((r) => setTimeout(r, 850));

                                if (outcome === 'network') {
                                    res.destroy();

                                    return;
                                }

                                const page = payload(req.url);

                                if (outcome === 'error') {
                                    page.props = {
                                        ...props,
                                        errors: { navigation: 'Failed' },
                                    };
                                }

                                res.setHeader(
                                    'Content-Type',
                                    'application/json',
                                );
                                res.setHeader('X-Inertia', 'true');
                                res.end(JSON.stringify(page));

                                return;
                            }

                            if (req.url === '/fixture') {
                                res.setHeader('Content-Type', 'text/html');
                                res.end(
                                    await server.transformIndexHtml(
                                        '/fixture',
                                        `<html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div id="app" data-page='${JSON.stringify(payload('/fixture'))}'></div><script type="module" src="/fixture-entry.js"></script></body></html>`,
                                    ),
                                );

                                return;
                            }

                            next();
                        });
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 0 },
        });
        await server.listen();
        const browser = await chromium.launch({
            headless: true,
            channel: 'chrome',
        });

        try {
            const origin = `http://127.0.0.1:${server.httpServer.address().port}`;

            for (const mode of ['desktop', 'mobile', 'standalone-reduced']) {
                const mobile = mode !== 'desktop';
                const context = await browser.newContext({
                    viewport: { width: mobile ? 390 : 1440, height: 844 },
                    isMobile: mobile,
                    hasTouch: mobile,
                    reducedMotion: mode.includes('reduced')
                        ? 'reduce'
                        : 'no-preference',
                });

                if (mode.includes('standalone')) {
                    await context.addInitScript(() => {
                        const original = matchMedia;
                        window.matchMedia = (query) => {
                            const result = original(query);

                            if (query === '(display-mode: standalone)') {
                                Object.defineProperty(result, 'matches', {
                                    value: true,
                                });
                            }

                            return result;
                        };
                    });
                }

                context.on('console', (msg) => {
                    if (msg.type() === 'error') {
                        console.log('Browser:', msg.text());
                    }
                });
                context.on('weberror', (error) =>
                    console.log('Browser error:', error.error().message),
                );
                const page = await context.newPage();
                await page.goto(origin + '/fixture');
                await page
                    .locator('[data-sidebar="menu-button"]')
                    .first()
                    .waitFor();
                await page.evaluate(async () => {
                    await navigator.serviceWorker.register(
                        '/service-worker.js',
                    );
                    await navigator.serviceWorker.ready;

                    if (!navigator.serviceWorker.controller) {
                        await new Promise((r) =>
                            navigator.serviceWorker.addEventListener(
                                'controllerchange',
                                r,
                                { once: true },
                            ),
                        );
                    }
                });
                const bar = page.locator('[data-navigation-progress]');
                const target = mobile
                    ? page.getByRole('button', { name: 'Panor.', exact: true })
                    : page.getByRole('button', {
                          name: 'Dashboard',
                          exact: true,
                      });

                for (outcome of ['success', 'error', 'network', 'cancel']) {
                    const before = requests;
                    await page.evaluate(() => {
                        window.events = [];
                    });
                    const at = Date.now();

                    // Real input followed by a second click exercises the duplicate GET guard.
                    if (mobile) {
                        await target.tap();
                    } else {
                        await target.click();
                    }

                    await target.evaluate((el) => el.click());
                    await bar.waitFor({ state: 'visible' });
                    assert.equal(
                        await target.getAttribute('aria-busy'),
                        'true',
                    );
                    const appearance = Date.now() - at;
                    assert.equal(
                        await page
                            .getByRole('button', {
                                name: 'Reports',
                                exact: true,
                            })
                            .getAttribute('aria-busy'),
                        null,
                    );

                    assert.ok(
                        appearance < 250,
                        `${mode}: appearance ${appearance}ms`,
                    );
                    assert.equal(
                        await bar.evaluate((el) => getComputedStyle(el).height),
                        '3px',
                    );
                    assert.equal(
                        await bar.evaluate(
                            (el) =>
                                el.parentElement.parentElement ===
                                document.body,
                        ),
                        true,
                    );

                    if (mode.includes('reduced')) {
                        assert.equal(
                            await bar
                                .locator('div')
                                .evaluate(
                                    (el) => getComputedStyle(el).animationName,
                                ),
                            'none',
                        );
                    }

                    await page.waitForTimeout(
                        Math.max(0, 650 - (Date.now() - at)),
                    );
                    assert.equal(await bar.isVisible(), true);
                    assert.equal(requests - before, 1);

                    if (outcome === 'cancel') {
                        await page.evaluate(() => window.cancelNavigation());
                    }

                    await bar.waitFor({ state: 'hidden' });
                    assert.equal(await target.getAttribute('aria-busy'), null);
                    const events = await page.evaluate(() => window.events);
                    assert.equal(
                        events.filter((e) => e.name === 'start').length,
                        1,
                    );
                    assert.equal(
                        events.filter((e) => e.name === 'finish').length,
                        1,
                    );

                    if (outcome === 'error') {
                        assert.ok(events.some((e) => e.name === 'error'));
                    }

                    if (outcome === 'network') {
                        assert.ok(events.some((e) => e.name === 'exception'));
                    }

                    console.log(
                        JSON.stringify({
                            mode,
                            outcome,
                            appearanceMs: appearance,
                            requestMs: Math.round(
                                events.at(-1).at - events[0].at,
                            ),
                            serviceWorker: true,
                        }),
                    );
                    await page.waitForTimeout(250);
                }

                await context.close();
            }
        } finally {
            await browser.close();
            await server.close();
        }
    },
);
