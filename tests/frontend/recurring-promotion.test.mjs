import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const root = process.cwd();
test('promotion summaries distinguish incoming amounts, manual conditions and duplicate transitions', async () => {
    const server = await createServer({
        configFile: false,
        plugins: [vue()],
        resolve: { alias: { '@': resolve('resources/js') } },
        server: { middlewareMode: true, hmr: false, ws: false, watch: null },
        optimizeDeps: { noDiscovery: true, include: [] },
    });

    try {
        const { default: Price } = await server.ssrLoadModule(
            '/resources/js/components/recurring/RecurringPriceConditions.vue',
        );
        const { createAppI18n } = await server.ssrLoadModule(
            '/resources/js/i18n/index.ts',
        );
        const promotion = {
            amount: '5.00',
            normal_amount: '20.00',
            duration: 'payments',
            payments: 2,
            outcome: 'normal',
            starts_on: '2026-10-01',
            ends_before: '2026-12-01',
            origin: {},
        };

        for (const direction of ['expense', 'income']) {
            for (const outcome of ['normal', 'keep', 'unknown']) {
                const conditions = {
                    lock_mode: 'until',
                    locked_until: '2027-01-01',
                    change_scope: 'personal',
                    new_amount: 20,
                    effective_from: '2026-12-01',
                };
                const snapshot = JSON.stringify(conditions);
                const app = createSSRApp({
                    render: () =>
                        h(Price, {
                            recurringEntryUuid: 'plan',
                            conditions,
                            currentAmount: 5,
                            currency: 'EUR',
                            canEdit: false,
                            direction,
                            promotion: { ...promotion, outcome },
                        }),
                });
                app.use(createAppI18n({ locale: { current: 'it' } }));
                const html = await renderToString(app);
                assert.match(html, /data-price-promotion-card/);
                assert.match(html, /Dal form della ricorrenza/);
                assert.match(html, /Per modificare o rimuovere la promozione/);

                if (outcome === 'normal') {
                    assert.match(html, /già indicato nelle condizioni manuali/);
                    assert.doesNotMatch(html, /Poi (pagherai|riceverai)/);
                }

                if (outcome === 'unknown') {
                    assert.match(html, /provvisoriamente/);
                    assert.match(html, /Rinnovo da verificare/);
                }

                if (direction === 'income') {
                    assert.match(html, /Ricevi/);
                    assert.doesNotMatch(
                        html.replace(/<[^>]*>/g, ''),
                        /paghi|pagherai|fornitor|risparmio|prezzo del servizio/i,
                    );
                }

                assert.equal(JSON.stringify(conditions), snapshot);
            }
        }
    } finally {
        await server.close();
    }
});

test(
    'real recurring form previews and confirms promotional edits and removals on desktop and mobile',
    {
        skip:
            !process.env.PROMOTION_BROWSER_FIXTURE ||
            !process.env.BROWSER_MODULE_PATH,
        timeout: 120000,
    },
    async () => {
        const fixtures = JSON.parse(
            readFileSync(process.env.PROMOTION_BROWSER_FIXTURE, 'utf8'),
        );
        const { chromium } = await import(
            pathToFileURL(process.env.BROWSER_MODULE_PATH + '/index.mjs')
        );
        const fixture = /* language=TEXT */ `<script setup>
import {ref} from 'vue';import {useI18n} from 'vue-i18n';import Form from '@/components/recurring/RecurringEntryFormSheet.vue';
const incoming=new URLSearchParams(location.search).get('direction')==='income';const creating=new URLSearchParams(location.search).has('create');const data=${JSON.stringify(fixtures)}[incoming?'income':'expense'];const open=ref(true);const {t}=useI18n();
</script><template><Form v-model:open="open" :entry="creating?null:data.recurringEntry.entry" :form-options="data.formOptions" :date-options="data.dateOptions" show-start-month-selector default-start-date="2026-10-01"/></template>`;
        const server = await createServer({
            configFile: false,
            root,
            cacheDir: '/tmp/soamco-promotion-ui-vite',
            resolve: { alias: { '@': resolve('resources/js') } },
            optimizeDeps: {
                noDiscovery: true,
                include: [
                    'vue',
                    'vue-i18n',
                    '@inertiajs/vue3',
                    'reka-ui',
                    '@vueuse/core',
                    'lucide-vue-next',
                    'v-calendar',
                    'class-variance-authority',
                    'clsx',
                    'tailwind-merge',
                ],
            },
            plugins: [
                {
                    name: 'expose-test-form',
                    enforce: 'pre',
                    transform(code, id) {
                        if (
                            id.endsWith(
                                '/recurring/RecurringEntryFormSheet.vue',
                            )
                        ) {
                            return code.replace(
                                '</script>',
                                "if(typeof window!=='undefined'){window.promotionFixtureForm=form;}\n</script>",
                            );
                        }
                    },
                },
                vue(),
                tailwind(),
                {
                    name: 'promotion-fixture',
                    resolveId(id) {
                        if (
                            [
                                '/promotion-fixture.vue',
                                '/promotion-entry.js',
                            ].includes(id)
                        ) {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/promotion-fixture.vue') {
                            return fixture;
                        }

                        if (id === '/promotion-entry.js') {
                            return `import {createApp,h} from 'vue';import {createInertiaApp} from '@inertiajs/vue3';import {createAppI18n} from '/resources/js/i18n/index.ts';import Fixture from '/promotion-fixture.vue';import '/resources/css/app.css';createInertiaApp({page:{component:'Fixture',url:location.pathname+location.search,version:null,props:{auth:{user:{format_locale:'it-IT'}}}},resolve:()=>Fixture,progress:false,setup({el,App,props,plugin}){createApp({render:()=>h(App,props)}).use(plugin).use(createAppI18n({locale:{current:'it'}})).mount(el)}});`;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use(
                            '/promotion-test',
                            (_req, res) => {
                                res.setHeader('Content-Type', 'text/html');
                                res.end(
                                    /* language=TEXT */ '<html lang="it"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app"></div><script type="module" src="/promotion-entry.js"></script></body></html>',
                                );
                            },
                        );
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 0, hmr: false, watch: null },
        });
        let browser;

        try {
            await server.listen();
            browser = await chromium.launch({
                headless: true,
                executablePath: process.env.BROWSER_EXECUTABLE_PATH,
            });

            for (const width of [1440, 375]) {
                const income = width === 375;
                const context = await browser.newContext({
                    viewport: { width, height: width < 768 ? 676 : 900 },
                    isMobile: width < 768,
                    hasTouch: width < 768,
                    reducedMotion: 'reduce',
                });
                const page = await context.newPage();
                const errors = [];
                const saves = [];
                let previews = 0;
                page.on('pageerror', (e) => errors.push(e.message));
                page.setDefaultTimeout(15000);
                await page.clock.setFixedTime(new Date('2026-10-01T12:00:00Z'));
                await page.route(
                    '**/recurring-entries/tracked-items',
                    async (route) => {
                        const body = route.request().postDataJSON();
                        assert.equal(body.draft, true);
                        assert.equal(body.website_url, 'https://example.com');
                        await route.fulfill({
                            json: {
                                item: {
                                    value: 'pending-reference:browser',
                                    uuid: 'pending-reference:browser',
                                    label: body.name,
                                    owner_user_id: fixtures[
                                        income ? 'income' : 'expense'
                                    ].formOptions.accounts.find(
                                        (account) =>
                                            account.value === body.account_uuid,
                                    )?.owner_user_id,
                                    category_uuids: [body.category_uuid],
                                    group_keys: [body.direction],
                                },
                            },
                        });
                    },
                );
                await page.route(
                    '**/recurring-entries/promotion-preview',
                    async (route) => {
                        const body = route.request().postDataJSON();
                        previews++;

                        if (body.notes === 'reference-validation-test') {
                            await route.fulfill({
                                status: 422,
                                json: {
                                    errors: {
                                        tracked_item_uuid: [
                                            'Riferimento di test non valido',
                                        ],
                                    },
                                },
                            });

                            return;
                        }

                        const promotion = body.promotion
                            ? {
                                  ...body.promotion,
                                  starts_on: '2026-10-01',
                                  ends_before: '2026-12-01',
                                  origin: {},
                              }
                            : null;
                        const amount = Number(promotion?.amount ?? 20);
                        await route.fulfill({
                            json: {
                                promotion,
                                schedule: promotion
                                    ? [
                                          {
                                              date: '2026-11-01',
                                              amount,
                                              currency: 'EUR',
                                          },
                                      ]
                                    : [],
                                confirmation: 'a'.repeat(64),
                                changes: body.recurring_entry_uuid
                                    ? [
                                          {
                                              uuid: 'future',
                                              date: '2026-11-01',
                                              previous_date: '2026-11-01',
                                              previous_amount: 5,
                                              amount,
                                              currency: 'EUR',
                                          },
                                      ]
                                    : [],
                            },
                        });
                    },
                );
                await page.route(
                    '**/recurring-entries/exchange-preview',
                    (route) =>
                        route.fulfill({
                            json: {
                                amount_raw: 20,
                                converted_base_amount_raw: 20,
                                currency_code: 'EUR',
                                base_currency_code: 'EUR',
                                exchange_rate: '1',
                                should_preview: false,
                            },
                        }),
                );
                await page.route(
                    /\/recurring-entries(?:\/[a-f0-9-]+)?$/,
                    async (route) => {
                        if (
                            !['POST', 'PATCH'].includes(
                                route.request().method(),
                            )
                        ) {
                            return route.continue();
                        }

                        saves.push(route.request().postDataJSON());
                        await route.fulfill({
                            status: 200,
                            headers: {
                                'X-Inertia': 'true',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                component: 'Fixture',
                                url: '/promotion-test',
                                version: null,
                                props: {
                                    auth: { user: { format_locale: 'it-IT' } },
                                },
                            }),
                        });
                    },
                );
                await page.goto(
                    `http://127.0.0.1:${server.httpServer.address().port}/promotion-test?direction=${income ? 'income' : 'expense'}`,
                );
                const fields = page.locator('[data-promotion-fields]');
                await fields.waitFor();
                assert.equal(
                    await fields.getByRole('checkbox').isChecked(),
                    true,
                );
                assert.match(
                    await fields.innerText(),
                    income ? /Ricevi/ : /Paghi/,
                );
                const savedPicker = page.locator('[data-reference-picker]');
                await savedPicker
                    .locator('button[aria-haspopup="dialog"]')
                    .waitFor();
                assert.match(
                    await savedPicker.innerText(),
                    /Riferimento esistente/,
                );
                await savedPicker
                    .locator('button[aria-haspopup="dialog"]')
                    .click();
                const savedDialog = page.getByRole('dialog').last();
                await savedDialog
                    .getByRole('searchbox', { name: 'Cerca riferimento' })
                    .fill('esistente');
                await page.waitForTimeout(250);
                await page.screenshot({
                    path: `/tmp/recurring-reference-picker-${width}.png`,
                });
                await savedDialog
                    .getByRole('button', {
                        name: 'Riferimento esistente',
                        exact: true,
                    })
                    .last()
                    .click();
                assert.match(
                    await savedPicker.innerText(),
                    /Riferimento esistente/,
                );
                async function setAmount(value) {
                    const input = fields.locator('[id$="-amount"]').first();

                    if (width >= 768) {
                        await input.fill(value);
                        await input.blur();

                        return;
                    }

                    await input.click();
                    const dialog = page.getByRole('dialog').last();
                    await dialog
                        .getByRole('button', { name: 'Azzera', exact: true })
                        .first()
                        .click();

                    for (const digit of value) {
                        await dialog
                            .getByRole('button', { name: digit, exact: true })
                            .click();
                    }

                    await dialog
                        .getByRole('button', { name: 'Salva', exact: true })
                        .click();
                }
                await setAmount('4');
                await page.locator('[data-promotion-schedule]').waitFor();
                const sheet = page
                    .locator('[data-slot="sheet-content"]')
                    .first();
                await sheet
                    .getByRole('button', {
                        name: 'Salva modifiche',
                        exact: true,
                    })
                    .click();
                const confirm = page.getByRole('dialog', {
                    name: 'Conferma i nuovi importi futuri',
                    exact: true,
                });
                await confirm.waitFor();
                assert.equal(saves.length, 0);
                assert.match(await confirm.innerText(), /5,00/);
                assert.match(await confirm.innerText(), /4,00/);
                await confirm
                    .getByRole('button', { name: 'Indietro', exact: true })
                    .click();
                assert.equal(saves.length, 0);
                await fields.getByRole('checkbox').uncheck();
                await page
                    .locator('[data-promotion-fields] input[type="checkbox"]')
                    .waitFor();
                await sheet
                    .getByRole('button', {
                        name: 'Salva modifiche',
                        exact: true,
                    })
                    .click();
                await confirm.waitFor();
                await confirm
                    .getByRole('button', {
                        name: 'Conferma e salva',
                        exact: true,
                    })
                    .click();
                await page.waitForFunction(
                    () =>
                        document.querySelectorAll('[data-slot="sheet-content"]')
                            .length === 0,
                );
                assert.equal(saves.at(-1).promotion, null);
                assert.equal(
                    saves.at(-1).promotion_confirmation,
                    'a'.repeat(64),
                );
                assert.ok(previews > 0);
                await page.goto(
                    `http://127.0.0.1:${server.httpServer.address().port}/promotion-test?create=1&direction=${income ? 'income' : 'expense'}`,
                );
                await fields.waitFor();
                assert.equal(
                    await fields.getByRole('checkbox').isChecked(),
                    false,
                );
                const entry =
                    fixtures[income ? 'income' : 'expense'].recurringEntry
                        .entry;
                await page.evaluate(async (entry) => {
                    const form = window.promotionFixtureForm;
                    form.direction = entry.direction;
                    await Promise.resolve();
                    form.account_uuid = entry.account.uuid;
                    form.category_uuid = entry.category.uuid;
                    form.description = 'Nuova promozione';
                    form.title = 'Nuova promozione';
                    form.expected_amount = '20';
                }, entry);
                const referencePicker = page.locator('[data-reference-picker]');
                await referencePicker
                    .locator('button[aria-haspopup="dialog"]')
                    .click();
                const referenceDialog = page.getByRole('dialog').last();
                await referenceDialog
                    .getByRole('button', {
                        name: width < 768 ? 'Nuovo' : 'Nuovo riferimento',
                        exact: true,
                    })
                    .click();
                await referenceDialog
                    .getByLabel('Nome riferimento')
                    .fill('Riferimento provvisorio');
                await referenceDialog
                    .getByLabel('Sito web (facoltativo)')
                    .fill('https://example.com');
                await referenceDialog
                    .getByRole('button', {
                        name: 'Crea e seleziona',
                        exact: true,
                    })
                    .click();
                await page.waitForFunction(
                    () =>
                        window.promotionFixtureForm.tracked_item_uuid ===
                        'pending-reference:browser',
                );
                assert.match(
                    await referencePicker.innerText(),
                    /Riferimento provvisorio/,
                );
                const trialToggle = sheet.getByRole('checkbox', {
                    name: /Periodo di prova/,
                });
                const trialPanel = trialToggle.locator(
                    'xpath=ancestor::section[1]',
                );
                const promotionPanel = fields.locator('../..');

                for (const [trialOpen, promotionOpen] of [
                    [false, false],
                    [true, false],
                    [false, true],
                    [true, true],
                ]) {
                    await trialToggle.setChecked(trialOpen);
                    await fields
                        .getByRole('checkbox')
                        .setChecked(promotionOpen);
                    await page.waitForTimeout(200);
                    const trialBounds = await trialPanel.boundingBox();
                    const promotionBounds = await fields.boundingBox();
                    const dateBounds = await page
                        .locator('#recurring-start-date')
                        .boundingBox();
                    const monthBounds = await page
                        .locator('#recurring-start-month')
                        .boundingBox();
                    assert.ok(
                        Math.abs(trialBounds.width - promotionBounds.width) < 2,
                    );

                    if (width >= 768) {
                        assert.ok(
                            Math.abs(trialBounds.y - promotionBounds.y) < 2,
                        );
                        assert.ok(
                            promotionBounds.x >
                                trialBounds.x + trialBounds.width,
                        );
                        assert.ok(Math.abs(dateBounds.y - monthBounds.y) < 2);
                        assert.ok(
                            dateBounds.y >=
                                Math.max(
                                    trialBounds.y + trialBounds.height,
                                    promotionBounds.y + promotionBounds.height,
                                ),
                        );

                        if (!trialOpen && promotionOpen) {
                            assert.ok(trialBounds.height < 130);
                        }
                    } else {
                        assert.ok(
                            promotionBounds.y >=
                                trialBounds.y + trialBounds.height,
                        );
                        assert.ok(
                            dateBounds.y >=
                                promotionBounds.y + promotionBounds.height,
                        );
                        assert.ok(monthBounds.y > dateBounds.y);
                        assert.ok(promotionBounds.width > 300);
                    }

                    assert.equal(
                        await promotionPanel.evaluate(
                            (el) => el.scrollWidth > el.clientWidth,
                        ),
                        false,
                    );
                    await promotionPanel.screenshot({
                        path: `/tmp/recurring-layout-${width}-${Number(trialOpen)}${Number(promotionOpen)}.png`,
                    });
                }

                await trialToggle.uncheck();
                await fields.getByRole('checkbox').check();
                await setAmount('5');
                await page.evaluate(() => {
                    window.promotionFixtureForm.notes =
                        'reference-validation-test';
                });
                const referenceError = page.getByText(
                    'Riferimento di test non valido',
                    { exact: true },
                );
                await referenceError.first().waitFor();
                assert.equal(
                    await fields
                        .locator('..')
                        .getByText('Riferimento di test non valido')
                        .count(),
                    0,
                );
                assert.equal(
                    await page
                        .getByText('Riferimento', { exact: true })
                        .first()
                        .locator('..')
                        .getByText('Riferimento di test non valido')
                        .count(),
                    1,
                );
                await page.evaluate(() => {
                    window.promotionFixtureForm.notes = '';
                    window.promotionFixtureForm.clearErrors();
                });
                await page.locator('[data-promotion-schedule]').waitFor();
                assert.equal(
                    await page.evaluate(
                        () => window.promotionFixtureForm.tracked_item_uuid,
                    ),
                    'pending-reference:browser',
                );
                await fields
                    .getByRole('button', {
                        name: income
                            ? 'Numero di incassi'
                            : 'Numero di pagamenti',
                        exact: true,
                    })
                    .click();
                await fields.locator('input[type="number"]').fill('3');
                await fields
                    .getByRole('button', {
                        name: 'Fino a una data',
                        exact: true,
                    })
                    .click();
                assert.equal(
                    await fields.locator('input[type="number"]').count(),
                    0,
                );
                await fields.locator('[id$="-until"]').fill('15/12/2026');
                await fields.locator('[id$="-until"]').blur();
                await fields
                    .getByRole('button', {
                        name: income
                            ? 'Ritorno all’importo normale'
                            : 'Ritorno al prezzo normale',
                        exact: true,
                    })
                    .click();
                await sheet
                    .getByRole('checkbox', { name: /Periodo di prova/ })
                    .check();
                await page.locator('[data-promotion-schedule]').waitFor();
                assert.match(
                    await fields.innerText(),
                    income ? /Poi riceverai/ : /Poi pagherai/,
                );
                const bounds = await fields.boundingBox();
                assert.ok(
                    bounds.x >= 0 && bounds.x + bounds.width <= width + 1,
                );
                await fields.screenshot({
                    path: `/tmp/soamco-promotion-fields-${width}.png`,
                });
                await sheet
                    .getByRole('button', { name: 'Crea piano', exact: true })
                    .click();
                await page.waitForFunction(
                    () =>
                        document.querySelectorAll('[data-slot="sheet-content"]')
                            .length === 0,
                );
                assert.equal(saves.at(-1).promotion.amount, '5');
                assert.equal(saves.at(-1).promotion.duration, 'until_date');
                assert.equal(saves.at(-1).promotion.until_date, '2026-12-15');
                assert.equal(saves.at(-1).trial_duration, 7);

                assert.deepEqual(errors, []);
                await page.screenshot({
                    path: `/tmp/soamco-promotion-form-${width}.png`,
                    fullPage: true,
                });
                await context.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
