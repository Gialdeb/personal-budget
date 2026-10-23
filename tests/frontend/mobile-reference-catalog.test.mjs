import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test(
    'Apple mobile flow in transaction and recurring forms: mixed search, automatic catalog and website logos, initials, cancellation and failed preparation',
    {
        skip:
            !process.env.BROWSER_MODULE_PATH ||
            !process.env.MOBILE_REFERENCE_FIXTURE,
        timeout: 180000,
    },
    async () => {
        const data = JSON.parse(
            readFileSync(process.env.MOBILE_REFERENCE_FIXTURE, 'utf8'),
        );
        const fixtures = Object.fromEntries(
            ['transactions', 'recurring'].map((flow) => [
                flow,
                /* language=TEXT */ `<script setup>import {ref} from 'vue';import Form from '@/components/${flow === 'transactions' ? 'transactions/TransactionFormSheet' : 'recurring/RecurringEntryFormSheet'}.vue';const open=ref(true);const data=${JSON.stringify(data[flow])};</script><template><Form v-model:open="open" ${flow === 'transactions' ? ':year="2026" :month="10" :sheet="data"' : ':form-options="data.formOptions" :date-options="data.dateOptions" default-start-date="2026-10-17"'} /></template>`,
            ]),
        );
        const server = await createServer({
            configFile: false,
            cacheDir: '/tmp/mobile-reference-catalog-vite',
            resolve: { alias: { '@': resolve('resources/js') } },
            plugins: [
                {
                    name: 'expose-mobile-reference-form',
                    enforce: 'pre',
                    transform(code, id) {
                        if (
                            id.endsWith(
                                '/transactions/TransactionFormSheet.vue',
                            ) ||
                            id.endsWith(
                                '/recurring/RecurringEntryFormSheet.vue',
                            )
                        ) {
                            return code.replace(
                                '</script>',
                                'window.mobileReferenceForm = form; window.mobileReferenceSubmit = submit;\n</script>',
                            );
                        }
                    },
                },
                vue(),
                tailwind(),
                {
                    name: 'mobile-reference-fixture',
                    resolveId(id) {
                        if (/^\/(transactions|recurring)\.(vue|js)$/.test(id)) {
                            return id;
                        }
                    },
                    load(id) {
                        const flow = id.slice(1).split('.')[0];

                        if (id === `/${flow}.vue`) {
                            return fixtures[flow];
                        }

                        if (id === `/${flow}.js` && fixtures[flow]) {
                            return `import {createApp,h} from 'vue';import {createInertiaApp} from '@inertiajs/vue3';import App from '/${flow}.vue';import {createAppI18n} from '/resources/js/i18n/index.ts';import '/resources/css/app.css';createInertiaApp({page:{component:'Test',url:'/mobile-reference-test',version:null,props:{auth:{user:{id:1,format_locale:'it-IT'}}}},resolve:()=>App,progress:false,setup({el,App,props,plugin}){createApp({render:()=>h(App,props)}).use(plugin).use(createAppI18n({locale:{current:'it'}})).mount(el)}});`;
                        }
                    },
                    configureServer(s) {
                        s.middlewares.use(
                            '/mobile-reference-test',
                            (req, res) => {
                                const flow = req.url.includes('recurring')
                                    ? 'recurring'
                                    : 'transactions';
                                res.setHeader('Content-Type', 'text/html');
                                res.end(
                                    `<html lang="it"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app"></div><script type="module" src="/${flow}.js"></script></body></html>`,
                                );
                            },
                        );
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 0, hmr: false },
            optimizeDeps: {
                include: [
                    'notivue',
                    'v-calendar',
                    'vue',
                    'vue-i18n',
                    'reka-ui',
                    'lucide-vue-next',
                    '@vueuse/core',
                    'class-variance-authority',
                    'clsx',
                    'tailwind-merge',
                    '@inertiajs/vue3',
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

            for (const flow of ['transactions', 'recurring']) {
                const page = await browser.newPage({
                    viewport: { width: 375, height: 740 },
                    isMobile: true,
                    hasTouch: true,
                });
                const errors = [],
                    mainSaves = [],
                    drafts = [],
                    discarded = [],
                    searches = [];
                let failPreparation = false;
                page.on('pageerror', (error) => errors.push(error.message));
                await page.route('**/api/brands*', (route) => {
                    searches.push(new URL(route.request().url()).searchParams);
                    const search =
                        searches.at(-1).get('search')?.toLowerCase() ?? '';

                    return route.fulfill({
                        json: {
                            data:
                                !search ||
                                ['apple', 'mela', 'apple.com'].some((term) =>
                                    term.includes(search),
                                )
                                    ? [
                                          data.brand,
                                          {
                                              ...data.brand,
                                              uuid: 'other-brand',
                                              domain: 'apple.other',
                                              category: 'Alimentari',
                                          },
                                      ]
                                    : [],
                        },
                    });
                });
                await page.route(
                    '**/settings/tracked-items/logo-preview',
                    (route) =>
                        route.fulfill({
                            json: {
                                logo_url: route
                                    .request()
                                    .postDataJSON()
                                    .website_url.includes('missing.test')
                                    ? null
                                    : data.brand.logo_url,
                            },
                        }),
                );
                await page.route(
                    '**/settings/tracked-items/logo-catalog*',
                    (route) =>
                        route.fulfill({ json: { items: [], has_more: false } }),
                );
                await page.route(
                    '**/settings/tracked-items/logo-upload',
                    (route) => {
                        if (route.request().method() === 'DELETE') {
                            discarded.push(
                                route.request().postDataJSON().token,
                            );

                            return route.fulfill({ json: { discarded: true } });
                        }

                        assert.match(
                            route.request().headers()['content-type'],
                            /multipart\/form-data/,
                        );

                        return route.fulfill({
                            json: {
                                token: 'temporary-logo-token',
                                logo_url: data.brand.logo_url,
                            },
                        });
                    },
                );
                await page.route('**/tracked-items', (route) => {
                    const body = route.request().postDataJSON();
                    drafts.push(body);

                    return route.fulfill(
                        failPreparation
                            ? {
                                  status: 422,
                                  json: {
                                      errors: {
                                          name: ['Preparazione non riuscita'],
                                      },
                                  },
                              }
                            : {
                                  json: {
                                      item: {
                                          value: 'pending-reference:mobile-test',
                                          uuid: 'pending-reference:mobile-test',
                                          label: body.name,
                                          name: body.name,
                                          owner_user_id:
                                              data.transactions.editor
                                                  .accounts[0].owner_user_id,
                                          logo_url: data.brand.logo_url,
                                          group_keys: ['expense'],
                                          category_uuids: [
                                              data.context.category_uuid,
                                          ],
                                      },
                                  },
                              },
                    );
                });
                await page.route(
                    flow === 'transactions'
                        ? '**/transactions/2026/10'
                        : '**/recurring-entries',
                    (route) => {
                        mainSaves.push(route.request().postDataJSON());

                        return route.fulfill({
                            headers: { 'X-Inertia': 'true' },
                            json: {
                                component: 'Test',
                                url: `/mobile-reference-test?flow=${flow}`,
                                version: null,
                                props: {
                                    auth: {
                                        user: { id: 1, format_locale: 'it-IT' },
                                    },
                                    errors: {
                                        amount: 'Salvataggio principale non riuscito',
                                    },
                                },
                            },
                        });
                    },
                );
                await page.route('**/exchange-preview', (route) =>
                    route.fulfill({ json: { should_preview: false } }),
                );
                await page.goto(
                    `http://127.0.0.1:${server.httpServer.address().port}/mobile-reference-test?flow=${flow}`,
                );
                await page.locator('[data-reference-picker]').waitFor();
                await page.evaluate(
                    async ({ context, flow }) => {
                        const form = window.mobileReferenceForm;

                        if (flow === 'transactions') {
                            form.type_key = 'expense';
                        } else {
                            form.direction = 'expense';
                        }

                        await new Promise((resolve) => setTimeout(resolve, 0));
                        form.account_uuid = context.account_uuid;
                        await new Promise((resolve) => setTimeout(resolve, 0));
                        form.category_uuid = context.category_uuid;
                    },
                    { context: data.context, flow },
                );
                const picker = page.locator('[data-reference-picker]');
                const trigger = picker
                    .locator('button[aria-haspopup="dialog"]')
                    .first();
                await picker
                    .locator('[data-mobile-reference-shortcuts]')
                    .getByRole('button', { name: /Apple.*Marchio/ })
                    .first()
                    .waitFor();
                assert.ok(
                    searches.some(
                        (params) => params.get('category') === 'Tecnologia',
                    ),
                );
                await trigger.tap();
                const modal = page.locator('[data-reference-dialog]');
                const search = modal.getByRole('searchbox', {
                    name: 'Cerca riferimento',
                });
                assert.equal(
                    await search.evaluate(
                        (el) => el === document.activeElement,
                    ),
                    false,
                );
                await search.fill('Apple');
                await modal
                    .getByRole('button', {
                        name: 'Apple personale',
                        exact: true,
                    })
                    .waitFor();
                await modal
                    .getByRole('button', { name: /Apple apple.com/ })
                    .waitFor();
                assert.ok(
                    (await modal
                        .locator('[data-mobile-reference-results] button')
                        .count()) <= 12,
                );
                await modal
                    .getByRole('button', {
                        name: 'Apple personale',
                        exact: true,
                    })
                    .tap();
                assert.equal(drafts.length, 0);
                assert.ok(
                    await page.evaluate(
                        () =>
                            window.mobileReferenceForm.tracked_item_uuid
                                .length > 0,
                    ),
                );
                await trigger.tap();
                await search.fill('Mela');
                await modal
                    .getByRole('button', { name: /Apple apple.com/ })
                    .tap();
                assert.equal(drafts.length, 0);
                assert.equal(
                    await modal.getByLabel('Nome riferimento').inputValue(),
                    'Apple',
                );
                await modal
                    .getByAltText('Logo selezionato', { exact: true })
                    .waitFor();
                await modal
                    .getByLabel('Nome riferimento')
                    .fill('Apple personalizzato');
                await page.waitForTimeout(350);
                assert.equal(
                    await modal.locator('[data-suggested-logo]').count(),
                    0,
                );
                await modal
                    .getByRole('button', {
                        name: 'Crea e seleziona',
                        exact: true,
                    })
                    .tap();
                await modal.waitFor({ state: 'hidden' });
                assert.equal(drafts.at(-1).logo_brand_uuid, data.brand.uuid);
                assert.equal(drafts.at(-1).draft, true);
                assert.equal(
                    drafts.at(-1).category_uuid,
                    data.context.category_uuid,
                );
                // Names use published catalog logos; unknown names keep initials.
                await picker
                    .getByRole('button', { name: 'Nuovo', exact: true })
                    .tap();
                await modal.getByLabel('Nome riferimento').fill('Apple');
                await modal
                    .getByAltText('Logo selezionato', { exact: true })
                    .waitFor();
                assert.equal(
                    await modal
                        .getByLabel('Sito web (facoltativo)')
                        .inputValue(),
                    '',
                );
                assert.equal(
                    await modal.locator('input[type=file]').count(),
                    0,
                );
                assert.equal(
                    await modal
                        .getByRole('button', {
                            name: 'Carica logo',
                            exact: true,
                        })
                        .count(),
                    0,
                );
                await modal
                    .getByLabel('Nome riferimento')
                    .fill('Apple senza logo');
                await page.waitForTimeout(350);
                assert.equal(
                    await modal.locator('[data-suggested-logo]').count(),
                    0,
                );
                await modal
                    .getByRole('button', {
                        name: 'Crea e seleziona',
                        exact: true,
                    })
                    .tap();
                await modal.waitFor({ state: 'hidden' });
                assert.equal(drafts.at(-1).logo_identity_uuid, null);
                assert.equal(drafts.at(-1).logo_brand_uuid, undefined);
                // Website lookup starts automatically and unavailable logos fall back to initials.
                await picker
                    .getByRole('button', { name: 'Nuovo', exact: true })
                    .tap();
                await modal.getByLabel('Nome riferimento').fill('Decò');
                await modal
                    .getByLabel('Sito web (facoltativo)')
                    .fill('https://example.test');
                await modal
                    .getByAltText('Logo selezionato', { exact: true })
                    .waitFor();
                assert.equal(
                    await modal
                        .getByRole('button', {
                            name: 'Dal catalogo',
                            exact: true,
                        })
                        .count(),
                    0,
                );
                assert.equal(
                    await modal
                        .getByRole('button', {
                            name: 'Recupera logo dal sito',
                            exact: true,
                        })
                        .count(),
                    0,
                );
                await modal
                    .getByLabel('Sito web (facoltativo)')
                    .fill('https://missing.test');
                await page.waitForTimeout(800);
                await modal.locator('[data-reference-initials]').waitFor();
                assert.equal(
                    (
                        await modal
                            .locator('[data-reference-initials]')
                            .innerText()
                    ).trim(),
                    'D',
                );
                await modal
                    .getByRole('button', {
                        name: 'Crea e seleziona',
                        exact: true,
                    })
                    .tap();
                await modal.waitFor({ state: 'hidden' });
                assert.equal(drafts.at(-1).logo_identity_uuid, null);
                // Cancellation and failed preparation keep the same automatic website preview.
                async function openWebsiteReference() {
                    await picker
                        .getByRole('button', { name: 'Nuovo', exact: true })
                        .tap();
                    await modal.getByLabel('Nome riferimento').fill('Decò');
                    await modal
                        .getByLabel('Sito web (facoltativo)')
                        .fill('https://example.test');
                    await modal
                        .getByAltText('Logo selezionato', { exact: true })
                        .waitFor();
                }
                await openWebsiteReference();
                await page.setViewportSize({ width: 375, height: 420 });
                await modal.getByLabel('Nome riferimento').focus();
                await page.waitForTimeout(500);
                const keyboardBounds = await modal.boundingBox();
                await page.screenshot({
                    path: `/tmp/mobile-reference-keyboard-${flow}.png`,
                });
                assert.ok(
                    keyboardBounds.y >= -1 &&
                        keyboardBounds.y + keyboardBounds.height <= 421,
                    `${flow}: ${JSON.stringify(keyboardBounds)}`,
                );
                await page.setViewportSize({ width: 375, height: 740 });
                await page.keyboard.press('Escape');
                await modal.waitFor({ state: 'hidden' });

                try {
                    await page.waitForFunction(
                        () =>
                            document.activeElement?.getAttribute(
                                'aria-haspopup',
                            ) === 'dialog',
                        null,
                        { timeout: 5000 },
                    );
                } catch {
                    throw new Error(
                        JSON.stringify({
                            flow,
                            active: await page.evaluate(
                                () => document.activeElement?.outerHTML,
                            ),
                            triggerCount: await trigger.count(),
                            errors,
                        }),
                    );
                }

                assert.equal(discarded.length, 0);
                // Preparation errors retain the automatic website logo for a retry.
                await openWebsiteReference();
                failPreparation = true;
                await modal
                    .getByRole('button', {
                        name: 'Crea e seleziona',
                        exact: true,
                    })
                    .tap();
                await modal
                    .getByText('Preparazione non riuscita', { exact: true })
                    .first()
                    .waitFor();
                assert.equal(discarded.length, 0);
                failPreparation = false;
                await modal
                    .getByRole('button', {
                        name: 'Crea e seleziona',
                        exact: true,
                    })
                    .tap();
                await modal.waitFor({ state: 'hidden' });
                assert.equal(drafts.at(-1).logo_source, 'website');
                assert.equal(
                    await trigger.locator('img').count(),
                    1,
                    await trigger.evaluate((el) => el.outerHTML),
                );
                assert.equal(
                    await trigger.locator('img').getAttribute('src'),
                    data.brand.logo_url,
                );
                assert.equal(discarded.length, 0);
                assert.equal(
                    await page.evaluate(
                        () => document.documentElement.scrollWidth > innerWidth,
                    ),
                    false,
                );
                await page.evaluate(async () => {
                    const form = window.mobileReferenceForm;
                    form.description = 'Apple test';
                    form.amount = '20';
                    form.expected_amount = '20';
                    form.transaction_date = '2026-10-17';
                    await new Promise((resolve) => setTimeout(resolve, 0));
                    window.mobileReferenceSubmit();
                });

                try {
                    await page.waitForFunction(
                        () =>
                            window.mobileReferenceForm.errors.amount ===
                            'Salvataggio principale non riuscito',
                        null,
                        { timeout: 5000 },
                    );
                } catch {
                    throw new Error(
                        JSON.stringify({
                            flow,
                            mainSaves,
                            errors,
                            form: await page.evaluate(() => ({
                                errors: window.mobileReferenceForm.errors,
                                data: window.mobileReferenceForm.data(),
                            })),
                        }),
                    );
                }

                assert.equal(mainSaves.length, 1);
                assert.equal(
                    mainSaves[0].tracked_item_uuid,
                    'pending-reference:mobile-test',
                );
                assert.equal(
                    await page.evaluate(
                        () => window.mobileReferenceForm.tracked_item_uuid,
                    ),
                    'pending-reference:mobile-test',
                );
                assert.equal(discarded.length, 0);
                assert.deepEqual(errors, []);
                await page.screenshot({
                    path: `/tmp/mobile-reference-apple-${flow}.png`,
                });
                await page.keyboard.press('Escape');
                await page.waitForFunction(
                    () => !document.querySelector('[data-slot=sheet-content]'),
                );
                await page.waitForTimeout(100);
                assert.equal(discarded.length, 0);
                await page.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
