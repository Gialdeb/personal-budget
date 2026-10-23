import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test('transaction and recurring forms reuse the picker and preserve their selection adapters', () => {
    const source = readFileSync(
        'resources/js/components/transactions/TransactionFormSheet.vue',
        'utf8',
    );
    assert.match(source, /<ReferencePicker\s+v-if="!isMoveMode"/);
    assert.match(source, /value: `scope:\$\{scope.uuid/);
    assert.match(source, /value: `tracked_item:\$\{trackedItem.uuid/);
    assert.match(source, /isMoveMode[\s\S]*?lockedMoveValue/);
    assert.match(
        readFileSync(
            'resources/js/components/recurring/RecurringEntryFormSheet.vue',
            'utf8',
        ),
        /<ReferencePicker/,
    );
});
const realData = process.env.REFERENCE_PICKER_FIXTURE
    ? JSON.parse(readFileSync(process.env.REFERENCE_PICKER_FIXTURE, 'utf8'))
    : null;
const actualFixture = realData
    ? /* language=TEXT */ `<script setup>import {ref} from 'vue';import Form from '@/components/transactions/TransactionFormSheet.vue';const open=ref(true);const sheet=${JSON.stringify(realData.data)};</script><template><Form v-model:open="open" :year="2026" :month="10" :sheet="sheet" /></template>`
    : '';
const fixture = /* language=TEXT */ `<script setup>
import Quick from '@/components/transactions/MobileQuickChoices.vue';import Content from '@/components/transactions/SearchableSelectOptionContent.vue';import {ref} from 'vue';import Picker from '@/components/ReferencePicker.vue';import {Sheet,SheetContent,SheetTitle,SheetDescription} from '@/components/ui/sheet';
const selected=ref('scope:one'),parent=ref(true),busy=ref(false),context=ref('account:category');
const options=[...Array.from({length:20},(_,index)=>({value:'tracked_item:extra'+index,label:'Altro '+index})),...Array.from({length:6},(_,index)=>({value:'tracked_item:amazon'+index,label:'Amazon altro '+index,domain:'amazon.com'})),{value:'scope:one',label:'Progetto'},{value:'tracked_item:one',label:'Amazon',domain:'amazon.it',search_terms:['vecchio nome']},{value:'tracked_item:two',label:'Amazon · secondo'},{value:'tracked_item:locked',label:'Archiviato',is_selectable:false}];
async function prepare(draft){busy.value=true;await fetch('/draft',{method:'POST',body:JSON.stringify(draft)});options.push({value:'tracked_item:pending-reference:test',label:draft.name});selected.value='tracked_item:pending-reference:test';busy.value=false;return true;}
</script><template><Sheet v-model:open="parent"><SheetContent><SheetTitle>Transazione test</SheetTitle><SheetDescription>Form esterno</SheetDescription><div data-shared-reference><Content :option="{value:'homonym',label:'Acme · secret-slug',reference_name:'Acme',full_path:'Parent > Acme',badgeLabel:'https://acme.example',logo_url:'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'}"/></div><div data-reference-quick><Quick model-value="homonym" appearance="reference" :options="[{value:'homonym',label:'Acme · secret-slug',reference_name:'Acme'}]"/></div><div data-unchanged-category><Content :option="{value:'category',label:'Parent > Child',full_path:'Parent > Child'}"/></div><Picker v-model="selected" :options="options" :recent="options.slice(0,2)" :mobile-suggestions="options.filter(option=>option.value==='tracked_item:one')" :mobile-recent="[options.find(option=>option.value==='scope:one'),...options.slice(0,3).map(option=>({...option,label:'Riferimento recente molto lungo '+option.value}))]" :busy="busy" :prepare="prepare" :context-key="context"/><p data-selected>{{selected}}</p><button type="button" data-save>Salva transazione</button></SheetContent></Sheet></template>`;
test(
    'mobile picker supports aliases scope values provisional logos keyboard and nested focus',
    { skip: !process.env.BROWSER_MODULE_PATH, timeout: 90000 },
    async () => {
        const server = await createServer({
            configFile: false,
            cacheDir: '/tmp/reference-picker-vite',
            resolve: { alias: { '@': resolve('resources/js') } },
            plugins: [
                {
                    name: 'expose-transaction-fixture',
                    enforce: 'pre',
                    transform(code, id) {
                        if (
                            id.endsWith(
                                '/transactions/TransactionFormSheet.vue',
                            )
                        ) {
                            return code.replace(
                                '</script>',
                                "if(typeof window!=='undefined'){window.referenceFixtureForm=form;}\n</script>",
                            );
                        }
                    },
                },
                vue(),
                tailwind(),
                {
                    name: 'reference-fixture',
                    resolveId(id) {
                        if (
                            [
                                '/reference.vue',
                                '/reference.js',
                                '/actual.vue',
                                '/actual.js',
                            ].includes(id)
                        ) {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/actual.vue') {
                            return actualFixture;
                        }

                        if (id === '/actual.js') {
                            return `import {createApp,h} from 'vue';import {createInertiaApp} from '@inertiajs/vue3';import App from '/actual.vue';import {createAppI18n} from '/resources/js/i18n/index.ts';import '/resources/css/app.css';createInertiaApp({page:{component:'Actual',url:'/reference-test',version:null,props:{auth:{user:{id:1,format_locale:'it-IT'}}}},resolve:()=>App,progress:false,setup({el,App,props,plugin}){createApp({render:()=>h(App,props)}).use(plugin).use(createAppI18n({locale:{current:'it'}})).mount(el)}});`;
                        }

                        if (id === '/reference.vue') {
                            return fixture;
                        }

                        if (id === '/reference.js') {
                            return `import {createApp} from 'vue';import App from '/reference.vue';import {createAppI18n} from '/resources/js/i18n/index.ts';import '/resources/css/app.css';createApp(App).use(createAppI18n({locale:{current:'it'}})).mount('#app');`;
                        }
                    },
                    configureServer(s) {
                        s.middlewares.use('/reference-test', (_req, res) => {
                            res.setHeader('Content-Type', 'text/html');
                            res.end(
                                `<html lang="it"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app"></div><script type="module" src="${_req.url?.includes('actual') ? '/actual.js' : '/reference.js'}"></script></body></html>`,
                            );
                        });
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

            for (const width of [375]) {
                const page = await browser.newPage({
                    viewport: { width, height: width < 768 ? 676 : 900 },
                    isMobile: width < 768,
                    hasTouch: width < 768,
                });
                const errors = [],
                    drafts = [];
                page.on('pageerror', (e) => errors.push(e.message));
                await page.route(
                    '**/settings/tracked-items/logo-catalog*',
                    (route) =>
                        route.fulfill({
                            json: {
                                items: [
                                    {
                                        uuid: 'logo-uuid',
                                        domain: 'example.com',
                                        logo_url:
                                            'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
                                    },
                                ],
                                has_more: false,
                            },
                        }),
                );
                await page.route(
                    '**/settings/tracked-items/logo-preview',
                    (route) =>
                        route.fulfill({
                            json: {
                                logo_url:
                                    'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
                                status: 'ready',
                            },
                        }),
                );
                const brandSearches = [];
                const brandRows = [
                    {
                        uuid: 'brand-amazon',
                        name: 'Amazon',
                        domain: 'amazon.it',
                        aliases: [],
                        logo_url: null,
                    },
                    {
                        uuid: 'brand-homonym',
                        name: 'Amazon',
                        domain: 'amazon.com',
                        aliases: [],
                        logo_url: null,
                    },
                    {
                        uuid: 'brand-similar',
                        name: 'Amazonas',
                        domain: 'amazonas.example',
                        aliases: [],
                        logo_url: null,
                    },
                    {
                        uuid: 'brand-lookalike',
                        name: 'Not Amazon',
                        domain: 'notamazon.it',
                        aliases: [],
                        logo_url: null,
                    },
                ];
                await page.route('**/api/brands*', (route) => {
                    const params = new URL(route.request().url()).searchParams;
                    const search = params.get('search');
                    brandSearches.push(search);

                    if (search === 'amazon.it') {
                        const secondPage = params.get('page') === '2';

                        return route.fulfill({
                            json: {
                                data: secondPage
                                    ? [brandRows[0]]
                                    : [brandRows[3]],
                                links: {
                                    next: secondPage
                                        ? null
                                        : '/api/brands?page=2',
                                },
                            },
                        });
                    }

                    return route.fulfill({
                        json: {
                            data: search?.startsWith('amazon') ? brandRows : [],
                        },
                    });
                });

                await page.route('**/draft', (route) => {
                    drafts.push(route.request().postDataJSON());

                    return route.fulfill({ json: {} });
                });
                await page.goto(
                    `http://127.0.0.1:${server.httpServer.address().port}/reference-test`,
                );
                assert.equal(
                    await page.locator('[data-shared-reference]').innerText(),
                    'Acme',
                );
                assert.equal(
                    await page.locator('[data-reference-quick]').innerText(),
                    'Acme',
                );
                assert.equal(
                    await page.locator('[data-shared-reference] img').count(),
                    1,
                );
                assert.match(
                    await page.locator('[data-unchanged-category]').innerText(),
                    /Parent[\s\S]*Child/i,
                );
                const trigger = page
                    .locator(
                        '[data-reference-picker] button[aria-haspopup="dialog"]',
                    )
                    .first();

                if (width < 768) {
                    const shortcuts = page.locator(
                        '[data-mobile-reference-shortcuts]',
                    );
                    assert.match(
                        await shortcuts.innerText(),
                        /Suggeriti per questa categoria/,
                    );
                    assert.match(await shortcuts.innerText(), /Recenti/);
                    const recentRow = shortcuts
                        .locator('div.overflow-x-auto')
                        .last();
                    assert.ok(
                        await recentRow.evaluate(
                            (el) => el.scrollWidth > el.clientWidth,
                        ),
                    );
                    const boxes = await Promise.all(
                        (await recentRow.getByRole('button').all()).map(
                            (button) => button.boundingBox(),
                        ),
                    );
                    assert.ok(
                        boxes.every((box) => Math.abs(box.y - boxes[0].y) < 2),
                    );
                    await recentRow.evaluate((el) => {
                        el.scrollLeft = 100;
                    });
                    assert.ok(
                        await recentRow.evaluate((el) => el.scrollLeft > 0),
                    );
                    assert.equal(
                        await page.evaluate(
                            () =>
                                document.documentElement.scrollWidth >
                                innerWidth,
                        ),
                        false,
                    );

                    await shortcuts
                        .getByRole('button', { name: 'Amazon', exact: true })
                        .tap();
                    assert.equal(await page.getByRole('dialog').count(), 1);
                } else {
                    assert.equal(
                        await page
                            .locator('[data-mobile-reference-shortcuts]')
                            .count(),
                        0,
                    );
                }

                await trigger.click();
                const modal = page.getByRole('dialog').last();

                if (width < 768) {
                    const results = modal.locator(
                        '[data-mobile-reference-results]',
                    );
                    assert.ok((await results.getByRole('button').count()) <= 7);
                    const newButton = results.getByRole('button', {
                        name: 'Nuovo',
                        exact: true,
                    });
                    await page.waitForTimeout(250);
                    await page.screenshot({
                        path: `/tmp/reference-mobile-selection-${width}.png`,
                    });
                    const bounds = await newButton.boundingBox();
                    assert.ok(
                        bounds.y > 0 &&
                            bounds.y + bounds.height <
                                page.viewportSize().height,
                    );
                    assert.equal(
                        await results
                            .locator('input[type=search]')
                            .locator('..')
                            .locator('svg')
                            .count(),
                        1,
                    );
                } else {
                    assert.ok((await modal.getByRole('button').count()) > 20);
                }

                const search = modal.getByRole('searchbox', {
                    name: 'Cerca riferimento',
                });

                for (let tab = 0; tab < 10; tab++) {
                    await page.keyboard.press('Tab');
                    assert.equal(
                        await modal.evaluate((el) =>
                            el.contains(document.activeElement),
                        ),
                        true,
                    );
                }

                if (width < 768) {
                    for (const variant of [
                        'amazon',
                        'Amazon',
                        '  AMÁZON  ',
                        'amazon.it',
                        'www.amazon.it',
                        'https://www.amazon.it/gp/cart?ref=test',
                    ]) {
                        await search.fill(variant);
                        const domainSearch = variant.includes('.');
                        await modal
                            .locator('[data-reference-brand="brand-amazon"]')
                            .waitFor();
                        const rows = modal.locator(
                            '[data-mobile-reference-results] .max-h-60 > button',
                        );
                        assert.equal(
                            await rows.nth(1).getAttribute('aria-label'),
                            'Amazon',
                            variant,
                        );
                        assert.equal(
                            await modal
                                .locator('button[aria-label="Amazon"]')
                                .count(),
                            1,
                            variant,
                        );
                        assert.equal(
                            await modal
                                .locator(
                                    '[data-reference-brand="brand-homonym"]',
                                )
                                .count(),
                            domainSearch ? 0 : 1,
                            variant,
                        );
                        assert.equal(
                            await modal
                                .locator(
                                    '[data-reference-brand="brand-similar"]',
                                )
                                .count(),
                            domainSearch ? 0 : 1,
                            variant,
                        );

                        if (domainSearch) {
                            assert.equal(
                                await modal
                                    .locator(
                                        '[data-reference-brand="brand-lookalike"]',
                                    )
                                    .count(),
                                0,
                            );
                        }

                        assert.doesNotMatch(
                            await modal.innerText(),
                            /amazon\.(it|com)|amazonas\.example|notamazon\.it|Marchio/,
                        );
                        assert.equal(drafts.length, 0);
                    }

                    assert.equal(brandSearches.at(-1), 'amazon.it');
                    await modal.locator('button[aria-label="Amazon"]').tap();
                    assert.equal(
                        await page.locator('[data-selected]').innerText(),
                        'tracked_item:one',
                    );
                    assert.equal(drafts.length, 0);
                    await trigger.click();
                }

                await modal
                    .getByRole('searchbox', { name: 'Cerca riferimento' })
                    .fill('Progetto');
                await modal
                    .getByRole('button', { name: 'Progetto', exact: true })
                    .last()
                    .click();
                assert.equal(
                    await page.locator('[data-selected]').innerText(),
                    'scope:one',
                );
                await trigger.click();
                await search.fill('vecchio nome');
                assert.equal(
                    await modal
                        .getByRole('button', { name: 'Amazon', exact: true })
                        .count(),
                    1,
                );
                await modal
                    .getByRole('button', { name: 'Amazon', exact: true })
                    .click();
                assert.equal(
                    await page.locator('[data-selected]').innerText(),
                    'tracked_item:one',
                );
                await trigger.click();
                await search.fill(width < 768 ? 'Archiviato' : '');
                assert.equal(
                    await modal
                        .getByRole('button', {
                            name: 'Archiviato',
                            exact: true,
                        })
                        .isDisabled(),
                    true,
                );
                await page.keyboard.press('Escape');
                await trigger.waitFor();
                await page.waitForFunction(
                    () =>
                        document.querySelector(
                            '[data-reference-picker] button[aria-haspopup="dialog"]',
                        ) === document.activeElement,
                );
                assert.equal(
                    await trigger.evaluate(
                        (el) => el === document.activeElement,
                    ),
                    true,
                );
                assert.equal(await page.getByRole('dialog').count(), 1);
                await trigger.press('Enter');
                await modal
                    .getByRole('button', {
                        name: width < 768 ? 'Nuovo' : 'Nuovo riferimento',
                        exact: true,
                    })
                    .click();
                await modal.getByLabel('Nome riferimento').fill('Nuovo');
                await modal
                    .getByLabel('Sito web (facoltativo)')
                    .fill('https://different.example');
                await modal.getByAltText('Logo selezionato').waitFor();
                await modal
                    .getByLabel('Sito web (facoltativo)')
                    .fill('https://different.example/changed');
                await modal.getByAltText('Logo selezionato').waitFor();

                if (width < 768) {
                    const actionBounds = await modal
                        .getByRole('button', {
                            name: 'Crea e seleziona',
                            exact: true,
                        })
                        .boundingBox();
                    assert.ok(
                        actionBounds.y >= 0 &&
                            actionBounds.y + actionBounds.height <=
                                page.viewportSize().height,
                    );
                }

                await page.screenshot({
                    path: `/tmp/reference-picker-${width}.png`,
                });
                await page.waitForTimeout(250);
                assert.equal(
                    await modal.evaluate(
                        (el) => el.scrollWidth > el.clientWidth,
                    ),
                    false,
                );
                await modal
                    .getByRole('button', {
                        name: 'Crea e seleziona',
                        exact: true,
                    })
                    .click();
                await page.waitForFunction(
                    () =>
                        document.querySelector('[data-selected]')
                            ?.textContent ===
                        'tracked_item:pending-reference:test',
                );
                assert.equal(drafts.length, 1);
                assert.equal(
                    drafts[0].website_url,
                    'https://different.example/changed',
                );
                assert.equal(drafts[0].logo_source, 'website');
                assert.equal(
                    await page.locator('[data-selected]').innerText(),
                    'tracked_item:pending-reference:test',
                );
                assert.equal(await page.getByRole('dialog').count(), 1);
                await trigger.click();
                await modal
                    .getByRole('button', {
                        name: width < 768 ? 'Nuovo' : 'Nuovo riferimento',
                        exact: true,
                    })
                    .click();
                await modal.getByLabel('Nome riferimento').fill('Da sito');
                await modal
                    .getByLabel('Sito web (facoltativo)')
                    .fill('https://example.org');
                await modal.getByAltText('Logo selezionato').waitFor();
                await page.keyboard.press('Escape');
                assert.equal(drafts.length, 1);

                if (realData) {
                    const preparations = [];
                    await page.route(
                        '**/transactions/tracked-items',
                        async (route) => {
                            const body = route.request().postDataJSON();
                            preparations.push(body);
                            await route.fulfill({
                                json: {
                                    item: {
                                        value: 'pending-reference:fixture',
                                        uuid: 'pending-reference:fixture',
                                        label: body.name,
                                        available_account_uuids: [
                                            body.account_uuid,
                                        ],
                                        group_keys: ['expense'],
                                        category_uuids: [body.category_uuid],
                                    },
                                },
                            });
                        },
                    );
                    await page.route('**/exchange-preview', (route) =>
                        route.fulfill({ json: { should_preview: false } }),
                    );
                    await page.goto(
                        `http://127.0.0.1:${server.httpServer.address().port}/reference-test?actual=1`,
                    );
                    await page.locator('[data-reference-picker]').waitFor();
                    await page.evaluate(async (context) => {
                        const form = window.referenceFixtureForm;
                        form.type_key = 'expense';
                        await new Promise((resolve) => setTimeout(resolve, 0));
                        form.account_uuid = context.account_uuid;
                        await new Promise((resolve) => setTimeout(resolve, 0));
                        form.category_uuid = context.category_uuid;
                    }, realData.context);
                    const realTrigger = page
                        .locator(
                            '[data-reference-picker] button[aria-haspopup="dialog"]',
                        )
                        .first();
                    await realTrigger.click();
                    const realModal = page.getByRole('dialog').last();
                    await page.waitForTimeout(250);
                    await page.screenshot({
                        path: `/tmp/reference-transaction-mobile-selection-${width}.png`,
                    });
                    await realModal
                        .getByRole('button', {
                            name: 'Test reference',
                            exact: true,
                        })
                        .last()
                        .click();
                    assert.equal(
                        await page.evaluate(
                            () =>
                                window.referenceFixtureForm.tracked_item_uuid
                                    .length > 0,
                        ),
                        true,
                    );
                    await realTrigger.click();
                    await realModal
                        .getByRole('button', {
                            name: width < 768 ? 'Nuovo' : 'Nuovo riferimento',
                            exact: true,
                        })
                        .click();
                    await realModal
                        .getByLabel('Nome riferimento')
                        .fill('Prova nuova');
                    await realModal
                        .getByRole('button', {
                            name: 'Crea e seleziona',
                            exact: true,
                        })
                        .click();
                    await page.waitForFunction(
                        () =>
                            window.referenceFixtureForm.tracked_item_uuid ===
                            'pending-reference:fixture',
                    );
                    assert.equal(preparations.length, 1);
                    assert.equal(preparations[0].draft, true);
                    assert.equal(
                        preparations[0].category_uuid,
                        realData.context.category_uuid,
                    );
                    assert.equal(
                        await page.evaluate(
                            () => window.referenceFixtureForm.scope_uuid,
                        ),
                        '',
                    );
                    await page
                        .getByRole('dialog')
                        .last()
                        .waitFor({ state: 'visible' });
                    await page.waitForTimeout(250);
                    await page.screenshot({
                        path: `/tmp/reference-transaction-form-${width}.png`,
                    });
                }

                assert.deepEqual(errors, []);
                await page.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
