import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test(
    'recurring lifecycle history and restart drawer are usable on desktop and mobile',
    {
        skip:
            !process.env.RECURRING_PERIOD_FIXTURE ||
            !process.env.COUNTERPARTY_BROWSER_MODULE_PATH,
        timeout: 90000,
    },
    async () => {
        const { chromium } = await import(
            pathToFileURL(
                process.env.COUNTERPARTY_BROWSER_MODULE_PATH + '/index.mjs',
            )
        );
        const html = readFileSync(
            process.env.RECURRING_PERIOD_FIXTURE + '/detail.html',
            'utf8',
        );
        const data = html.match(/data-page="([^"]+)"/)[1];
        const candidatesData = readFileSync(
            process.env.RECURRING_PERIOD_FIXTURE + '/candidates.html',
            'utf8',
        ).match(/data-page="([^"]+)"/)[1];
        const options = readFileSync(
            process.env.RECURRING_PERIOD_FIXTURE + '/options.json',
            'utf8',
        );
        const output = '/tmp/soamco-recurring-period-shots';
        mkdirSync(output, { recursive: true });
        const server = await createServer({
            configFile: false,
            root: process.cwd(),
            cacheDir: '/tmp/soamco-recurring-period-vite',
            resolve: { alias: { '@': resolve('resources/js') } },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'recurring-period-fixture',
                    resolveId(id) {
                        if (
                            id === '/period-fixture.js' ||
                            id === '/candidates-fixture.js'
                        ) {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/candidates-fixture.js') {
                            return `
                              import {createApp,h,reactive} from 'vue';
                              import {createInertiaApp} from '@inertiajs/vue3';
                              import {createAppI18n} from '/resources/js/i18n/index.ts';
                              import Form from '/resources/js/components/recurring/RecurringEntryFormSheet.vue';
                              import '/resources/css/app.css';
                              const fixtureOptions = reactive(${options});
                              window.previewFixtureOptions=fixtureOptions;
                              for(const candidate of fixtureOptions.existing_plan_candidates) {
                                  for(const key of ['url','reactivation_url']) {
                                      const parsed = new URL(candidate[key]);
                                      candidate[key] = parsed.pathname + parsed.search;
                                  }
                              }
                              if(new URLSearchParams(location.search).has('single')) fixtureOptions.existing_plan_candidates = fixtureOptions.existing_plan_candidates.slice(-1);
                              createInertiaApp({resolve:()=>({render:()=>h(Form,{open:true,formOptions:fixtureOptions,defaultStartDate:'2026-01-01'})}),progress:false,setup({el,App,props,plugin}){createApp({render:()=>h(App,props)}).use(plugin).use(createAppI18n(props.initialPage.props)).mount(el)}});
                            `;
                        }

                        if (id === '/period-fixture.js') {
                            return `
                import {createApp,h} from 'vue';
                import {createInertiaApp} from '@inertiajs/vue3';
                import {createAppI18n} from '/resources/js/i18n/index.ts';
                import Show from '/resources/js/pages/transactions/recurring/Show.vue';
                import '/resources/css/app.css';
                createInertiaApp({resolve:()=>Show,progress:false,setup({el,App,props,plugin}){createApp({render:()=>h(App,props)}).use(plugin).use(createAppI18n(props.initialPage.props)).mount(el)}});
            `;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use((req, res, next) => {
                            if (!req.url.startsWith('/recurring-entries/')) {
                                return next();
                            }

                            res.setHeader('Content-Type', 'text/html');
                            res.end(
                                `<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app" data-page="${req.url.includes('candidates') ? candidatesData : data}"></div><script type="module" src="${req.url.includes('candidates') ? '/candidates-fixture.js' : '/period-fixture.js'}"></script></body></html>`,
                            );
                        });
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 5194, hmr: false, watch: null },
        });
        let browser;

        try {
            await server.listen();
            browser = await chromium.launch({
                executablePath: process.env.BROWSER_EXECUTABLE_PATH,
                headless: true,
            });

            for (const width of [1440, 375]) {
                const context = await browser.newContext({
                    viewport: { width, height: 1000 },
                    reducedMotion: 'reduce',
                    isMobile: width < 768,
                    hasTouch: width < 768,
                });
                const page = await context.newPage();
                const errors = [];
                page.on('pageerror', (e) => errors.push(e.message));
                page.setDefaultTimeout(10000);
                await page.clock.setFixedTime(new Date('2026-08-01T00:00:00Z'));
                await page.goto(
                    'http://127.0.0.1:5194/recurring-entries/fixture',
                );
                await page
                    .getByRole('heading', {
                        name: 'Cronologia del piano',
                        exact: true,
                    })
                    .waitFor();
                assert.equal(
                    await page
                        .getByText('Piano riattivato', { exact: true })
                        .count(),
                    2,
                );
                assert.ok(
                    await page.getByText(/1 mag 2026 → 1 ago 2026/).isVisible(),
                );
                await page
                    .getByRole('button', { name: /Vedi storico importi/ })
                    .click();
                assert.ok(
                    await page
                        .getByText(
                            'Periodicità diverse: percentuale non confrontabile',
                            { exact: false },
                        )
                        .isVisible(),
                );
                assert.ok(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                );
                await page
                    .locator('#plan-lifecycle-title')
                    .scrollIntoViewIfNeeded();
                await page.screenshot({
                    path: `${output}/history-${width}.png`,
                });
                await page
                    .getByRole('button', {
                        name: 'Riattiva dalla data scelta',
                        exact: true,
                    })
                    .click();
                const drawer = page
                    .locator('[data-slot="sheet-content"]')
                    .first();
                await drawer.waitFor();
                assert.ok(
                    await drawer
                        .getByText(/Scegli la data di ripartenza/)
                        .isVisible(),
                );
                assert.ok(
                    await drawer.locator('#recurring-start-date').isEnabled(),
                );
                assert.equal(
                    await drawer.locator('#recurring-start-date').inputValue(),
                    '',
                );
                assert.ok(
                    await drawer
                        .getByRole('button', {
                            name: 'Ogni mese',
                            exact: false,
                        })
                        .isEnabled(),
                );
                assert.ok(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                );
                await drawer
                    .locator('#recurring-start-date')
                    .scrollIntoViewIfNeeded();
                await page.screenshot({
                    path: `${output}/restart-${width}.png`,
                });
                assert.deepEqual(errors, []);
                await page.clock.setFixedTime(new Date('2026-01-01T00:00:00Z'));
                await page.goto(
                    'http://127.0.0.1:5194/recurring-entries/candidates',
                );
                await page
                    .locator('[data-slot="sheet-content"]')
                    .first()
                    .waitFor();

                if (
                    !(await page
                        .getByRole('button', {
                            name: 'Subscription',
                            exact: true,
                        })
                        .count())
                ) {
                    await page
                        .getByRole('button', {
                            name: 'Seleziona categoria',
                            exact: true,
                        })
                        .click();
                }

                await page
                    .getByRole('button', { name: 'Subscription', exact: true })
                    .first()
                    .click();

                if (width < 768) {
                    await page
                        .getByRole('button', { name: /Provider/ })
                        .first()
                        .click();
                } else {
                    await page
                        .getByRole('button', { name: 'Nessuno', exact: true })
                        .first()
                        .click();
                    await page
                        .getByRole('button', { name: /Provider/ })
                        .first()
                        .click();
                }

                const suggestions = page.locator(
                    'section[aria-label="Piani esistenti"]',
                );
                await suggestions.waitFor();
                const notice = page.getByRole('status').filter({
                    hasText: 'Abbiamo trovato 3 piani associati a Provider',
                });
                await notice.scrollIntoViewIfNeeded();
                assert.ok(await notice.isVisible());
                await notice
                    .getByRole('button', {
                        name: 'Visualizza piani',
                        exact: true,
                    })
                    .click();
                await page.waitForFunction(
                    (el) => el.dataset.revealed === 'true',
                    await suggestions.elementHandle(),
                );
                await page.waitForFunction(
                    (el) => {
                        const root = el
                            .closest('.overflow-y-auto')
                            .getBoundingClientRect();
                        const box = el.getBoundingClientRect();

                        return box.top >= root.top && box.top < root.bottom;
                    },
                    await suggestions.elementHandle(),
                );
                assert.equal(
                    await suggestions.evaluate(
                        (el) => getComputedStyle(el).transitionProperty,
                    ),
                    'none',
                );
                const carousel = suggestions.locator('[tabindex="0"]');
                assert.equal(await suggestions.locator('article').count(), 3);
                assert.equal(
                    await suggestions
                        .getByRole('link', {
                            name: 'Visualizza dettagli',
                            exact: true,
                        })
                        .count(),
                    1,
                );
                assert.ok(
                    await suggestions
                        .getByText('1 di 3', { exact: true })
                        .isVisible(),
                );

                if (width >= 768) {
                    await suggestions
                        .getByRole('button', {
                            name: 'Piano successivo',
                            exact: true,
                        })
                        .click();
                } else {
                    const client = await context.newCDPSession(page);
                    await carousel.scrollIntoViewIfNeeded();
                    await carousel.evaluate((el) => {
                        el.dataset.swiping = 'true';
                        el.addEventListener(
                            'scrollend',
                            () => {
                                el.dataset.swiping = 'false';
                            },
                            { once: true },
                        );
                    });
                    const box = await carousel.boundingBox();
                    const touchY = Math.max(
                        150,
                        Math.min(800, box.y + box.height / 2),
                    );
                    await client.send('Input.dispatchTouchEvent', {
                        type: 'touchStart',
                        touchPoints: [{ x: box.x + box.width - 30, y: touchY }],
                    });

                    for (
                        let x = box.x + box.width - 60;
                        x > box.x + 10;
                        x -= 30
                    ) {
                        await client.send('Input.dispatchTouchEvent', {
                            type: 'touchMove',
                            touchPoints: [{ x, y: touchY }],
                        });
                    }

                    await client.send('Input.dispatchTouchEvent', {
                        type: 'touchEnd',
                        touchPoints: [],
                    });
                    await client.detach();
                    await page.waitForFunction(
                        (el) => el.dataset.swiping === 'false',
                        await carousel.elementHandle(),
                    );
                }

                await suggestions
                    .getByText(width < 768 ? /^[23] di 3$/ : '2 di 3', {
                        exact: true,
                    })
                    .waitFor();
                await carousel.focus();
                await page.keyboard.press('End');
                await suggestions
                    .getByText('3 di 3', { exact: true })
                    .waitFor();
                await page.keyboard.press('Home');
                await suggestions
                    .getByText('1 di 3', { exact: true })
                    .waitFor();
                await page
                    .getByRole('button', { name: 'Crea piano', exact: true })
                    .click();
                const validation = page
                    .getByText(
                        'Inserisci una descrizione per il movimento programmato.',
                        { exact: true },
                    )
                    .first();
                await validation.waitFor();
                const amount = page.locator('#recurring-primary-amount');

                if (width < 768) {
                    await amount.click();
                    const keypad = page
                        .locator('[data-slot="sheet-content"]')
                        .filter({
                            has: page.getByRole('button', {
                                name: 'Salva',
                                exact: true,
                            }),
                        });
                    await keypad
                        .getByRole('button', { name: '2', exact: true })
                        .click();
                    await keypad
                        .getByRole('button', { name: '7', exact: true })
                        .click();
                    await keypad
                        .getByRole('button', { name: 'Salva', exact: true })
                        .click();
                    await keypad.waitFor({ state: 'hidden' });
                } else {
                    await amount.fill('27');
                }

                const readAmount = () =>
                    width < 768 ? amount.innerText() : amount.inputValue();
                const initialValue = await readAmount();
                await suggestions.scrollIntoViewIfNeeded();
                await suggestions
                    .getByRole('link', {
                        name: 'Visualizza dettagli',
                        exact: true,
                    })
                    .scrollIntoViewIfNeeded();
                const originalScroll = await page
                    .locator('[data-slot="sheet-content"]')
                    .first()
                    .locator('.overflow-y-auto')
                    .evaluate((el) => el.scrollTop);

                if (width >= 768) {
                    const popupPromise = context.waitForEvent('page');
                    await suggestions
                        .getByRole('link', {
                            name: 'Visualizza dettagli',
                            exact: true,
                        })
                        .click();
                    const popup = await popupPromise;
                    await popup
                        .getByRole('heading', {
                            name: 'Cronologia del piano',
                            exact: true,
                        })
                        .waitFor();
                    await popup.close();
                } else {
                    await suggestions
                        .getByRole('link', {
                            name: 'Visualizza dettagli',
                            exact: true,
                        })
                        .click();
                    const overlay = page.locator(
                        '[data-recurring-plan-detail]',
                    );
                    await page
                        .frameLocator('iframe')
                        .getByRole('heading', {
                            name: 'Cronologia del piano',
                            exact: true,
                        })
                        .waitFor();
                    await page.waitForFunction(
                        (el) => Math.abs(el.getBoundingClientRect().top) < 1,
                        await overlay.elementHandle(),
                    );
                    assert.ok((await overlay.boundingBox()).height >= 999);
                    await page.screenshot({
                        path: `${output}/existing-plan-detail-${width}.png`,
                    });
                    await overlay
                        .getByRole('button', { name: 'Chiudi', exact: true })
                        .click();
                    await page.locator('iframe').waitFor({ state: 'detached' });
                    await overlay.waitFor({ state: 'hidden' });
                }

                await page.waitForFunction(
                    ({ scroll }) =>
                        document.querySelector(
                            '[data-slot="sheet-content"] .overflow-y-auto',
                        )?.scrollTop === scroll,
                    { scroll: originalScroll },
                );
                assert.equal(await readAmount(), initialValue);
                assert.ok(await validation.isVisible());
                assert.ok(
                    await suggestions
                        .getByText('1 di 3', { exact: true })
                        .isVisible(),
                );
                assert.equal(
                    await page
                        .locator('[data-slot="sheet-content"]')
                        .first()
                        .locator('.overflow-y-auto')
                        .evaluate((el) => el.scrollTop),
                    originalScroll,
                );
                assert.ok(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                );
                await page.screenshot({
                    path: `${output}/existing-plans-${width}.png`,
                });
                await page.emulateMedia({ reducedMotion: 'no-preference' });
                await page.goto(
                    'http://127.0.0.1:5194/recurring-entries/candidates?single=1',
                );
                await page
                    .getByRole('button', {
                        name: /Rateale Piano a durata finita/,
                    })
                    .click();
                const category = page.getByRole('button', {
                    name: 'Subscription',
                    exact: true,
                });

                if (!(await category.count())) {
                    await page
                        .getByRole('button', {
                            name: 'Seleziona categoria',
                            exact: true,
                        })
                        .click();
                }

                await category.first().click();

                if (width >= 768) {
                    await page
                        .getByRole('button', { name: 'Nessuno', exact: true })
                        .first()
                        .click();
                }

                await page
                    .getByRole('button', { name: /Provider/ })
                    .last()
                    .click();
                await suggestions.waitFor();
                const singleNotice = page.getByRole('status').filter({
                    hasText: 'Abbiamo trovato 1 piano associato a Provider',
                });
                await singleNotice.scrollIntoViewIfNeeded();
                await singleNotice.evaluate((el) =>
                    el.scrollIntoView({ block: 'start' }),
                );
                const offscreen = await suggestions.evaluate((el) => {
                    const root = el
                        .closest('.overflow-y-auto')
                        .getBoundingClientRect();
                    const box = el.getBoundingClientRect();

                    return box.bottom <= root.top || box.top >= root.bottom;
                });
                assert.ok(offscreen);
                await page.screenshot({
                    path: `${output}/existing-plans-notice-${width}.png`,
                });
                const height = await suggestions.evaluate(
                    (el) => el.getBoundingClientRect().height,
                );
                await singleNotice
                    .getByRole('button', {
                        name: 'Visualizza piani',
                        exact: true,
                    })
                    .click();
                await page.waitForFunction(
                    (el) =>
                        el.dataset.revealed === 'true' &&
                        getComputedStyle(el).opacity === '1',
                    await suggestions.elementHandle(),
                );
                assert.equal(
                    await suggestions.evaluate(
                        (el) => getComputedStyle(el).transitionDuration,
                    ),
                    '0.6s',
                );
                assert.equal(
                    await suggestions.evaluate(
                        (el) => el.getBoundingClientRect().height,
                    ),
                    height,
                );
                assert.equal(await suggestions.locator('article').count(), 1);
                assert.ok(
                    await suggestions
                        .getByText(/Importo totale:.*199,99/)
                        .isVisible(),
                );
                assert.ok(
                    await suggestions
                        .getByText(/Prossima rata:.*39,99/)
                        .isVisible(),
                );
                assert.equal(
                    await suggestions
                        .getByRole('button', {
                            name: 'Piano successivo',
                            exact: true,
                        })
                        .count(),
                    0,
                );
                assert.ok(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
                );
                await suggestions.scrollIntoViewIfNeeded();
                await page.screenshot({
                    path: `${output}/existing-plan-single-${width}.png`,
                });
                await page.evaluate(() => {
                    for (const plan of window.previewFixtureOptions
                        .existing_plan_candidates) {
                        delete plan.installment_amount;
                    }
                });
                await suggestions
                    .getByText('Importo rata non disponibile', { exact: true })
                    .waitFor();
                assert.equal(
                    await suggestions.getByText(/Prossima rata:/).count(),
                    0,
                );
                await page.evaluate(() => {
                    for (const plan of window.previewFixtureOptions
                        .existing_plan_candidates) {
                        plan.installment_amount = 0;
                    }
                });
                assert.ok(
                    await suggestions
                        .getByText('Importo rata non disponibile', {
                            exact: true,
                        })
                        .isVisible(),
                );
                assert.equal(
                    await suggestions.getByText(/Prossima rata:/).count(),
                    0,
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
