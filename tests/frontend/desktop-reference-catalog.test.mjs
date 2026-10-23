import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test(
    'desktop reference select reuses dropdown and provisional creation in both forms',
    {
        skip:
            !process.env.BROWSER_MODULE_PATH ||
            !process.env.MOBILE_REFERENCE_FIXTURE,
        timeout: 120000,
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
            cacheDir: '/tmp/desktop-reference-catalog-vite',
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
                    viewport: { width: 1440, height: 1000 },
                });
                const errors = [],
                    drafts = [],
                    discards = [],
                    searches = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.route('**/api/brands*', (route) => {
                    const params = new URL(route.request().url()).searchParams;
                    searches.push(params);
                    const search = params.get('search') ?? '';

                    return route.fulfill({
                        json: {
                            data:
                                !search ||
                                ['apple', 'mela', 'apple.com', 'apple.it'].some(
                                    (term) => term.includes(search),
                                )
                                    ? [
                                          {
                                              ...data.brand,
                                              alternative_domains: ['apple.it'],
                                          },
                                          {
                                              ...data.brand,
                                              uuid: 'second-brand',
                                              domain: 'apple.other',
                                              logo_url: null,
                                          },
                                      ]
                                    : [],
                        },
                    });
                });
                await page.route(
                    '**/settings/tracked-items/logo-catalog*',
                    (route) =>
                        route.fulfill({ json: { items: [], has_more: false } }),
                );
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
                await page.route('**/exchange-preview', (route) =>
                    route.fulfill({ json: { should_preview: false } }),
                );
                await page.route(
                    '**/settings/tracked-items/logo-upload',
                    (route) => {
                        if (route.request().method() === 'DELETE') {
                            discards.push(route.request().postDataJSON().token);

                            return route.fulfill({ json: { discarded: true } });
                        }

                        return route.fulfill({
                            json: {
                                token: 'desktop-upload-token',
                                logo_url: data.brand.logo_url,
                            },
                        });
                    },
                );
                await page.route('**/tracked-items', (route) => {
                    const body = route.request().postDataJSON();
                    drafts.push(body);

                    return route.fulfill({
                        json: {
                            item: {
                                value: 'pending-reference:desktop-test',
                                uuid: 'pending-reference:desktop-test',
                                name: body.name,
                                owner_user_id:
                                    data.transactions.editor.accounts[0]
                                        .owner_user_id,
                                label: body.name,
                                logo_url:
                                    body.logo_identity_uuid === null
                                        ? null
                                        : data.brand.logo_url,
                                group_keys: ['expense'],
                                category_uuids: [data.context.category_uuid],
                            },
                        },
                    });
                });
                await page.goto(
                    `http://127.0.0.1:${server.httpServer.address().port}/mobile-reference-test?flow=${flow}`,
                );
                await page.locator('[data-desktop-reference-picker]').waitFor();
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
                const picker = page.locator('[data-desktop-reference-picker]');
                const trigger = picker.locator('button').first();
                const modal = page.locator('[data-reference-dialog]');
                assert.equal(
                    await page
                        .locator('[data-mobile-reference-shortcuts]')
                        .count(),
                    0,
                );
                await trigger.click();
                assert.equal(
                    await modal.count(),
                    0,
                    'select must stay a dropdown',
                );
                const search = picker.getByRole('searchbox');
                assert.equal(
                    await picker
                        .getByRole('button', {
                            name: 'Nuovo riferimento',
                            exact: true,
                        })
                        .count(),
                    0,
                );
                await search.fill('  APPLÉ  ');
                assert.equal(
                    await picker
                        .getByRole('button', {
                            name: 'Nuovo riferimento',
                            exact: true,
                        })
                        .count(),
                    0,
                );
                await picker
                    .getByRole('button', { name: 'Apple', exact: true })
                    .first()
                    .waitFor();
                assert.equal(searches.at(-1).get('search'), 'apple');
                assert.ok(
                    searches.some(
                        (params) => params.get('category') === 'Tecnologia',
                    ),
                );
                const personal = picker.getByRole('button', {
                    name: 'Apple personale',
                    exact: true,
                });
                assert.equal(
                    await picker
                        .getByRole('button', {
                            name: 'Nuovo riferimento',
                            exact: true,
                        })
                        .count(),
                    0,
                );
                assert.equal(
                    await picker
                        .getByRole('button', { name: /Nuovo riferimento "/ })
                        .count(),
                    0,
                );
                const personalBox = await personal.boundingBox();
                const brandBox = await picker
                    .getByRole('button', { name: 'Apple', exact: true })
                    .first()
                    .boundingBox();
                assert.ok(personalBox.y < brandBox.y);
                assert.ok(
                    brandBox.y > 90 && brandBox.y + brandBox.height < 930,
                );
                await page.screenshot({
                    path: `/tmp/desktop-reference-select-${flow}.png`,
                });
                assert.match(
                    await personal.locator('img').getAttribute('src'),
                    /^data:image\/svg\+xml,/,
                );
                await personal.click();
                assert.equal(drafts.length, 0);
                assert.ok(
                    await page.evaluate(
                        () =>
                            window.mobileReferenceForm.tracked_item_uuid
                                .length > 0,
                    ),
                );
                await trigger.click();
                await search.fill('https://www.apple.it/store');
                await picker
                    .getByRole('button', { name: 'Apple', exact: true })
                    .first()
                    .waitFor();
                assert.equal(searches.at(-1).get('search'), 'apple.it');
                assert.doesNotMatch(
                    await picker.innerText(),
                    /apple\.(com|it|other)|Marchio|Riferimenti esistenti/u,
                );
                assert.equal(
                    await picker
                        .getByRole('button', { name: 'Apple', exact: true })
                        .count(),
                    1,
                );
                await picker
                    .getByRole('button', { name: 'Apple', exact: true })
                    .click();
                await modal.getByLabel('Nome riferimento').waitFor();
                assert.equal(
                    await modal.getByLabel('Nome riferimento').inputValue(),
                    'Apple',
                );
                assert.equal(
                    await modal
                        .getByLabel('Sito web (facoltativo)')
                        .inputValue(),
                    'https://apple.com',
                );
                assert.equal(drafts.length, 0);
                assert.ok((await modal.boundingBox()).width <= 450);
                await page.waitForTimeout(250);
                assert.equal(
                    await modal.evaluate((el) => {
                        const bounds = el.getBoundingClientRect();

                        return el.contains(
                            document.elementFromPoint(
                                bounds.right - 20,
                                bounds.top + 20,
                            ),
                        );
                    }),
                    true,
                );
                await page.screenshot({
                    path: `/tmp/desktop-reference-dialog-${flow}.png`,
                });
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
                        .getByRole('button', { name: 'Dal sito', exact: true })
                        .count(),
                    0,
                );
                await modal
                    .getByRole('button', {
                        name: 'Crea e seleziona',
                        exact: true,
                    })
                    .click();
                await modal.waitFor({ state: 'hidden' });
                assert.equal(drafts.at(-1).logo_brand_uuid, data.brand.uuid);
                assert.equal(drafts.at(-1).draft, true);
                assert.equal(
                    await page.evaluate(
                        () => window.mobileReferenceForm.tracked_item_uuid,
                    ),
                    'pending-reference:desktop-test',
                );
                // Creation is offered only after both searches return no matches.
                await trigger.click();
                await search.fill('Apple nuovo');
                await picker
                    .getByRole('button', {
                        name: 'Nuovo riferimento',
                        exact: true,
                    })
                    .click();
                assert.equal(
                    await modal.getByLabel('Nome riferimento').inputValue(),
                    'Apple nuovo',
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
                await modal.locator('[data-reference-initials]').waitFor();
                await modal
                    .getByRole('button', { name: 'Indietro', exact: true })
                    .click();
                await modal.waitFor({ state: 'hidden' });
                await page.waitForTimeout(100);
                assert.equal(discards.length, 0);
                await trigger.click();
                await search.fill('Mai visto');
                await picker
                    .getByRole('button', {
                        name: 'Nuovo riferimento "Mai visto"',
                        exact: true,
                    })
                    .click();
                assert.equal(
                    await modal.getByLabel('Nome riferimento').inputValue(),
                    'Mai visto',
                );
                await modal
                    .getByRole('button', { name: 'Indietro', exact: true })
                    .click();
                await modal.waitFor({ state: 'hidden' });
                await trigger.click();
                await search.fill('Nuovo sconosciuto');
                await picker
                    .getByRole('button', {
                        name: 'Nuovo riferimento',
                        exact: true,
                    })
                    .click();
                await modal
                    .getByLabel('Nome riferimento')
                    .fill('Nuovo senza logo');
                await modal.locator('[data-reference-initials]').waitFor();
                await modal
                    .getByLabel('Sito web (facoltativo)')
                    .fill('https://example.test');
                await modal
                    .getByAltText('Logo selezionato', { exact: true })
                    .waitFor();
                await modal
                    .getByLabel('Sito web (facoltativo)')
                    .fill('https://missing.test');
                await modal.locator('[data-reference-initials]').waitFor();
                await page.waitForTimeout(700);
                assert.equal(
                    await modal
                        .getByAltText('Logo selezionato', { exact: true })
                        .count(),
                    0,
                );
                await modal
                    .getByRole('button', {
                        name: 'Crea e seleziona',
                        exact: true,
                    })
                    .click();
                await modal.waitFor({ state: 'hidden' });
                assert.equal(drafts.at(-1).logo_identity_uuid, null);
                await trigger.click();
                await search.fill('Marchio da verificare');
                await picker
                    .getByRole('button', {
                        name: 'Nuovo riferimento',
                        exact: true,
                    })
                    .click();
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
                await modal
                    .getByLabel('Sito web (facoltativo)')
                    .fill('https://example.test');
                await modal
                    .getByAltText('Logo selezionato', { exact: true })
                    .waitFor();
                await modal
                    .getByRole('button', {
                        name: 'Crea e seleziona',
                        exact: true,
                    })
                    .click();
                await modal.waitFor({ state: 'hidden' });
                assert.equal(drafts.at(-1).logo_source, 'website');
                assert.equal(drafts.at(-1).logo_upload_token, undefined);
                assert.equal(
                    await trigger.locator('img').count(),
                    1,
                    await trigger.evaluate((el) => el.outerHTML),
                );
                assert.equal(
                    await trigger.locator('img').getAttribute('src'),
                    data.brand.logo_url,
                );
                assert.equal(discards.length, 0);
                await page.screenshot({
                    path: `/tmp/desktop-reference-${flow}.png`,
                });
                assert.deepEqual(errors, []);
                await page.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
