import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import { runInNewContext } from 'node:vm';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { parse } from '@vue/compiler-sfc';
import { transformSync } from 'esbuild';
import { createServer } from 'vite';
import { ref, computed } from 'vue';

test('brand administration uses its own routes and contains no personal reference picker', () => {
    const source = readFileSync(
        'resources/js/pages/admin/Brands/Index.vue',
        'utf8',
    );
    assert.match(source, /Admin\/BrandController/);
    assert.match(source, /ABCDEFGHIJKLMNOPQRSTUVWXYZ/);
    assert.match(source, /fetch_logo/);
    assert.match(source, /forceFormData: true/);
    assert.doesNotMatch(source, /ReferencePicker|tracked_item_uuid/);
});

test(
    'brand admin supports search alphabetical filters editing publication logo modes and removal on desktop and mobile',
    {
        skip:
            !process.env.BRAND_BROWSER_FIXTURE ||
            !process.env.BROWSER_MODULE_PATH,
        timeout: 90000,
    },
    async () => {
        const props = JSON.parse(
            readFileSync(process.env.BRAND_BROWSER_FIXTURE, 'utf8'),
        );

        for (let index = 1; index <= 60; index++) {
            props.category_management.push({
                uuid: `category-test-${index}`,
                name: `Categoria ${String(index).padStart(3, '0')}`,
                brands_count: 0,
            });
        }

        props.categories = props.category_management.map(
            (category) => category.name,
        );
        const server = await createServer({
            configFile: false,
            cacheDir: '/tmp/brand-catalog-vite',
            resolve: { alias: { '@': resolve('resources/js') } },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'brand-browser-fixture',
                    resolveId(id) {
                        if (id === '/brand-entry.js') {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/brand-entry.js') {
                            return `import {createApp,h} from 'vue';import {createInertiaApp} from '@inertiajs/vue3';import Page from '/resources/js/pages/admin/Brands/Index.vue';import {createAppI18n} from '/resources/js/i18n/index.ts';import '/resources/css/app.css';createInertiaApp({page:{component:'admin/Brands/Index',url:'/brand-test',version:null,props:${JSON.stringify(props)}},resolve:()=>Page,progress:false,setup({el,App,props,plugin}){createApp({render:()=>h(App,props)}).use(plugin).use(createAppI18n({locale:{current:'it'}})).mount(el)}});`;
                        }
                    },
                    configureServer(s) {
                        s.middlewares.use('/brand-test', (_req, res) => {
                            res.setHeader('Content-Type', 'text/html');
                            res.end(
                                /* language=TEXT */ '<html lang="it"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app"></div><script type="module" src="/brand-entry.js"></script></body></html>',
                            );
                        });
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 0, hmr: false },
            optimizeDeps: {
                noDiscovery: true,
                include: [
                    'vue',
                    'vue-i18n',
                    '@inertiajs/vue3',
                    '@vueuse/core',
                    'reka-ui',
                    'lucide-vue-next',
                    'notivue',
                    'pusher-js',
                    'laravel-echo',
                    'class-variance-authority',
                    'clsx',
                    'tailwind-merge',
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

            for (const width of [1440, 375]) {
                const page = await browser.newPage({
                    viewport: { width, height: 900 },
                });
                const state = structuredClone(props);
                const errors = [],
                    requests = [];
                page.on('pageerror', (e) => errors.push(e.message));
                const logoData =
                    'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfZkAAAAASUVORK5CYII=';
                await page.route(
                    '**/settings/tracked-items/logo-preview',
                    async (route) => {
                        const domain = route
                            .request()
                            .postDataJSON().website_url;
                        await route.fulfill({
                            json: {
                                logo_url: domain.includes('missing')
                                    ? null
                                    : logoData + '#' + domain,
                            },
                        });
                    },
                );

                await page.route('**/admin/brands**', async (route) => {
                    const req = route.request();

                    if (
                        !new URL(req.url()).pathname.startsWith('/admin/brands')
                    ) {
                        await route.continue();

                        return;
                    }

                    requests.push({
                        method: req.method(),
                        url: req.url(),
                        body: req.postData(),
                    });
                    const path = new URL(req.url()).pathname;
                    state.errors = {};

                    if (path.includes('/categories')) {
                        const data = req.postDataJSON();
                        const uuid = path.split('/').at(-1);

                        if (req.method() === 'POST') {
                            state.category_management.push({
                                uuid: 'category-' + width,
                                name: data.name,
                                brands_count: 0,
                            });
                        } else if (req.method() === 'PATCH') {
                            const category = state.category_management.find(
                                (category) => category.uuid === uuid,
                            );

                            if (data.name === 'Riprova') {
                                state.errors = {
                                    name: 'Nome categoria non disponibile.',
                                };
                            }

                            for (const brand of state.brands.data) {
                                if (
                                    !state.errors.name &&
                                    brand.category === category.name
                                ) {
                                    brand.category = data.name;
                                }
                            }

                            if (!state.errors.name) {
                                category.name = data.name;
                            }
                        } else if (req.method() === 'DELETE') {
                            state.category_management =
                                state.category_management.filter(
                                    (category) => category.uuid !== uuid,
                                );
                        }

                        state.categories = state.category_management.map(
                            (category) => category.name,
                        );
                    }

                    if (path.endsWith('/move')) {
                        const data = req.postDataJSON();
                        await new Promise((resolve) =>
                            setTimeout(resolve, 350),
                        );
                        const target = state.category_management.find(
                            (category) => category.uuid === data.category_uuid,
                        );
                        const source = state.category_management.find(
                            (category) =>
                                category.uuid === data.source_category_uuid,
                        );

                        for (const brand of state.brands.data) {
                            if (
                                source
                                    ? brand.category === source.name
                                    : data.brand_uuids.includes(brand.uuid)
                            ) {
                                brand.category = target.name;
                            }
                        }

                        for (const category of state.category_management) {
                            category.brands_count = state.brands.data.filter(
                                (brand) => brand.category === category.name,
                            ).length;
                        }

                        if (data.delete_source) {
                            state.category_management =
                                state.category_management.filter(
                                    (category) => category.uuid !== source.uuid,
                                );
                            state.categories = state.category_management.map(
                                (category) => category.name,
                            );
                        }
                    }

                    const query = new URL(req.url()).searchParams;
                    await route.fulfill({
                        headers: { 'X-Inertia': 'true' },
                        json: {
                            component: 'admin/Brands/Index',
                            url: '/brand-test',
                            version: null,
                            props: {
                                ...state,
                                filters: {
                                    search: query.get('search') ?? '',
                                    letter: query.get('letter') ?? '',
                                    sort: query.get('sort') ?? 'name_asc',
                                },
                            },
                        },
                    });
                });
                await page.goto(
                    `http://127.0.0.1:${server.httpServer.address().port}/brand-test`,
                );

                try {
                    await page
                        .getByRole('button', {
                            name: 'Nuovo marchio',
                            exact: true,
                        })
                        .waitFor({ timeout: 10000 });
                } catch (error) {
                    throw new Error(
                        JSON.stringify({
                            errors,
                            body: await page.locator('body').innerText(),
                        }) + error.message,
                    );
                }

                await page
                    .getByRole('searchbox', { name: 'Cerca marchi' })
                    .fill('Apple');
                await page.waitForRequest(
                    (req) =>
                        req.url().includes('/admin/brands?') &&
                        req.url().includes('search=Apple'),
                );
                await page
                    .getByRole('button', { name: 'A', exact: true })
                    .click();
                await page.waitForRequest((req) =>
                    req.url().includes('letter=A'),
                );
                await page.getByLabel('Ordinamento').selectOption('name_desc');
                await page.waitForRequest((req) =>
                    req.url().includes('sort=name_desc'),
                );
                await page
                    .getByRole('button', { name: 'Nuovo marchio', exact: true })
                    .click();
                const dialog = page.getByRole('dialog').last();
                await dialog
                    .getByLabel('Nome marchio', { exact: true })
                    .fill('Apple Music');
                await dialog
                    .getByLabel('Alias, uno per riga')
                    .fill('Music\niTunes');
                await dialog
                    .getByLabel('Dominio', { exact: true })
                    .fill('music.apple.com');
                await dialog
                    .getByRole('button', {
                        name: 'Aggiungi dominio',
                        exact: true,
                    })
                    .click();
                await dialog
                    .getByLabel('Dominio alternativo 1', { exact: true })
                    .fill('apple.it');
                await dialog
                    .getByRole('button', {
                        name: 'Aggiungi dominio',
                        exact: true,
                    })
                    .click();
                await dialog
                    .getByLabel('Dominio alternativo 2', { exact: true })
                    .fill('apple.de');
                await dialog
                    .getByLabel('Dominio alternativo 1', { exact: true })
                    .fill('apple.fr');
                await dialog
                    .getByRole('button', { name: 'Rimuovi', exact: true })
                    .last()
                    .click();
                assert.equal(
                    await dialog
                        .getByLabel('Dominio alternativo 1', { exact: true })
                        .inputValue(),
                    'apple.fr',
                );
                assert.equal(
                    await dialog
                        .getByLabel('Dominio alternativo 2', { exact: true })
                        .count(),
                    0,
                );
                const preview = dialog.locator('[data-brand-preview]');
                await preview
                    .getByRole('img', { name: 'Anteprima logo' })
                    .waitFor();
                await preview.getByRole('img').dispatchEvent('error');
                await preview.locator('[data-brand-initials]').waitFor();
                assert.equal(
                    await preview
                        .getByText('Apple Music', { exact: true })
                        .count(),
                    1,
                );
                await dialog
                    .getByLabel('Dominio', { exact: true })
                    .fill('missing.example');
                await preview.locator('[data-brand-initials]').waitFor();
                assert.equal(
                    await preview.locator('[data-brand-initials]').innerText(),
                    'AM',
                );
                await dialog
                    .getByLabel('Nome marchio', { exact: true })
                    .fill('Decò');
                assert.equal(
                    await preview.locator('[data-brand-initials]').innerText(),
                    'D',
                );
                await dialog
                    .getByLabel('Nome marchio', { exact: true })
                    .fill('Apple Music');
                await dialog
                    .getByLabel('Dominio', { exact: true })
                    .fill('music.apple.com');
                await preview
                    .getByRole('img', { name: 'Anteprima logo' })
                    .waitFor();
                await dialog
                    .getByLabel('Categoria del marchio (facoltativa)')
                    .selectOption('Tecnologia');
                await dialog
                    .getByLabel('Logo', { exact: true })
                    .selectOption('website');
                assert.equal(
                    await dialog.locator('input[type=file]').count(),
                    0,
                );
                await dialog
                    .getByLabel('Logo', { exact: true })
                    .selectOption('remove');
                await preview.locator('[data-brand-initials]').waitFor();
                await dialog
                    .getByLabel('Logo', { exact: true })
                    .selectOption('upload');
                await dialog
                    .getByLabel('Carica logo', { exact: true })
                    .setInputFiles({
                        name: 'apple.png',
                        mimeType: 'image/png',
                        buffer: Buffer.from(
                            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jfZkAAAAASUVORK5CYII=',
                            'base64',
                        ),
                    });
                await preview
                    .getByRole('img', { name: 'Anteprima logo' })
                    .waitFor();
                await dialog.getByLabel('Pubblicato', { exact: true }).check();
                await page.waitForTimeout(250);
                await page.screenshot({
                    path: `/tmp/brand-admin-${width}.png`,
                });
                assert.equal(
                    await dialog.evaluate(
                        (el) => el.scrollWidth > el.clientWidth,
                    ),
                    false,
                );
                await dialog
                    .getByRole('button', { name: 'Salva', exact: true })
                    .click();
                await dialog.waitFor({ state: 'hidden' });
                const create = requests.find((req) => req.method === 'POST');
                assert.ok(create.body.includes('Apple Music'));
                assert.ok(create.body.includes('apple.png'));
                await page
                    .getByRole('button', { name: 'Modifica', exact: true })
                    .click();
                await dialog
                    .getByLabel('Nome marchio', { exact: true })
                    .waitFor();
                assert.equal(
                    await dialog
                        .getByLabel('Nome marchio', { exact: true })
                        .inputValue(),
                    'Apple',
                );
                await dialog
                    .getByLabel('Dominio', { exact: true })
                    .fill('updated.apple.com');
                await preview
                    .getByRole('img', { name: 'Anteprima logo' })
                    .waitFor();
                assert.ok(
                    (
                        await preview.getByRole('img').getAttribute('src')
                    ).includes('updated.apple.com'),
                );
                await dialog
                    .getByLabel('Nome marchio', { exact: true })
                    .fill('Apple Updated');
                await preview
                    .getByText('Apple Updated', { exact: true })
                    .waitFor();
                await dialog
                    .getByRole('button', { name: 'Annulla', exact: true })
                    .click();
                await page
                    .getByRole('button', { name: 'Ritira', exact: true })
                    .click();
                await page.waitForTimeout(200);
                assert.ok(
                    requests.some((req) => req.body?.includes('is_published')),
                );
                await page
                    .getByRole('button', { name: 'Rimuovi', exact: true })
                    .click();
                await dialog
                    .getByRole('button', { name: 'Rimuovi', exact: true })
                    .click();
                await dialog.waitFor({ state: 'hidden' });
                assert.ok(requests.some((req) => req.method === 'DELETE'));
                await page
                    .getByRole('button', {
                        name: 'Gestisci categorie',
                        exact: true,
                    })
                    .click();
                const table = dialog.getByRole('table', {
                    name: 'Categorie del catalogo',
                });
                assert.equal(await table.locator('tbody tr').count(), 10);
                await dialog.getByLabel('Righe per pagina').selectOption('25');
                assert.equal(await table.locator('tbody tr').count(), 25);
                await dialog.getByLabel('Righe per pagina').selectOption('50');
                assert.equal(await table.locator('tbody tr').count(), 50);
                await dialog
                    .getByRole('button', {
                        name: 'Pagina successiva',
                        exact: true,
                    })
                    .click();
                assert.equal(await table.locator('tbody tr').count(), 11);
                await dialog
                    .getByLabel('Ordinamento categorie')
                    .selectOption('desc');
                assert.match(
                    await table.locator('tbody tr').first().innerText(),
                    /Tecnologia/,
                );
                await dialog
                    .getByRole('searchbox', { name: 'Cerca categorie' })
                    .fill('inesistente');
                await dialog
                    .getByText('Nessuna categoria corrisponde alla ricerca.', {
                        exact: true,
                    })
                    .waitFor();
                assert.equal(
                    await dialog
                        .getByRole('button', {
                            name: 'Nuova categoria',
                            exact: true,
                        })
                        .isVisible(),
                    true,
                );
                await dialog
                    .getByRole('searchbox', { name: 'Cerca categorie' })
                    .fill('tecnologia');
                assert.equal(await table.locator('tbody tr').count(), 1);
                const viewRequest = page.waitForRequest((req) =>
                    req.url().includes('category=Tecnologia'),
                );
                await dialog
                    .getByRole('button', {
                        name: 'Visualizza 1 marchi della categoria Tecnologia',
                        exact: true,
                    })
                    .click();
                await viewRequest;
                await dialog.waitFor({ state: 'hidden' });
                await page
                    .getByRole('button', {
                        name: 'Gestisci categorie',
                        exact: true,
                    })
                    .click();
                await dialog
                    .getByRole('button', {
                        name: 'Nuova categoria',
                        exact: true,
                    })
                    .click();
                await dialog.getByLabel('Nome categoria').fill('Servizi');
                await dialog
                    .getByRole('button', { name: 'Conferma', exact: true })
                    .click();
                await dialog
                    .getByRole('searchbox', { name: 'Cerca categorie' })
                    .fill('Servizi');
                const serviceRow = table
                    .getByRole('row')
                    .filter({ hasText: 'Servizi' });
                await serviceRow
                    .getByRole('button', {
                        name: 'Rinomina categoria',
                        exact: true,
                    })
                    .click();
                await dialog.getByLabel('Nome categoria').fill('Riprova');
                await dialog
                    .getByRole('button', { name: 'Conferma', exact: true })
                    .click();
                await dialog
                    .getByRole('alert')
                    .filter({ hasText: 'Impossibile completare' })
                    .waitFor();
                await dialog
                    .getByText('Nome categoria non disponibile.', {
                        exact: true,
                    })
                    .waitFor();
                await dialog.getByLabel('Nome categoria').fill('Elettronica');
                await dialog
                    .getByRole('button', { name: 'Conferma', exact: true })
                    .click();
                await dialog
                    .getByRole('searchbox', { name: 'Cerca categorie' })
                    .fill('Elettronica');
                await table
                    .getByRole('row')
                    .filter({ hasText: 'Elettronica' })
                    .waitFor();
                await page.keyboard.press('Escape');
                await page
                    .getByRole('checkbox', {
                        name: 'Seleziona marchio Apple',
                        exact: true,
                    })
                    .check();
                await page
                    .getByRole('button', {
                        name: 'Sposta selezionati (1)',
                        exact: true,
                    })
                    .click();
                await dialog
                    .getByLabel('Categoria di destinazione')
                    .selectOption({ label: 'Elettronica' });
                await dialog
                    .getByRole('button', { name: 'Conferma', exact: true })
                    .click();
                await dialog
                    .getByRole('status')
                    .filter({ hasText: 'Operazione in corso' })
                    .waitFor();
                const electronics = table
                    .getByRole('row')
                    .filter({ hasText: 'Elettronica' });
                await electronics
                    .getByRole('button', {
                        name: 'Azioni categoria Elettronica',
                        exact: true,
                    })
                    .click();
                await page
                    .getByRole('menuitem', {
                        name: 'Elimina categoria',
                        exact: true,
                    })
                    .click();
                const transfer = dialog.getByRole('button', {
                    name: 'Trasferisci ed elimina',
                    exact: true,
                });
                assert.equal(await transfer.isDisabled(), true);
                await dialog
                    .getByLabel('Categoria di destinazione')
                    .selectOption({ label: 'Tecnologia' });
                assert.equal(await transfer.isDisabled(), true);
                await dialog
                    .getByRole('checkbox', {
                        name: 'Confermo il trasferimento di tutti i marchi e l’eliminazione della categoria.',
                        exact: true,
                    })
                    .check();
                await transfer.click();
                await dialog
                    .getByRole('heading', {
                        name: 'Categorie del catalogo',
                        exact: true,
                    })
                    .waitFor();
                assert.equal(
                    state.category_management.some(
                        (category) => category.name === 'Elettronica',
                    ),
                    false,
                );
                await dialog
                    .getByRole('searchbox', { name: 'Cerca categorie' })
                    .fill('Elettronica');
                await dialog
                    .getByText('Nessuna categoria corrisponde alla ricerca.', {
                        exact: true,
                    })
                    .waitFor();
                await dialog
                    .getByRole('searchbox', { name: 'Cerca categorie' })
                    .fill('');
                await dialog.getByLabel('Righe per pagina').selectOption('10');
                await dialog
                    .getByLabel('Ordinamento categorie')
                    .selectOption('asc');
                assert.equal(
                    await dialog.evaluate(
                        (element) => element.scrollWidth > element.clientWidth,
                    ),
                    false,
                );
                await page.screenshot({
                    path: `/tmp/brand-category-table-${width}.png`,
                });
                assert.ok(
                    requests.some(
                        (req) =>
                            req.url.endsWith('/admin/brands/move') &&
                            JSON.parse(req.body).delete_source === true,
                    ),
                );
                assert.deepEqual(errors, []);
                await page.close();
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);

function brandFormHarness() {
    const source = readFileSync(
        'resources/js/pages/admin/Brands/Index.vue',
        'utf8',
    );
    const script = parse(source).descriptor.scriptSetup.content.replace(
        /^import[\s\S]*?from ['"][^'"]+['"];$/gm,
        '',
    );
    const calls = [];
    let callback;
    const context = {
        ref,
        computed,
        watch: () => {},
        onBeforeUnmount: () => {},
        nextTick: (fn) => fn(),
        defineProps: () => ({
            categories: ['Tecnologia'],
            filters: {
                search: '',
                letter: '',
                sort: 'name_asc',
                category: '',
                without_category: false,
            },
        }),
        useUiCopy: () => (text) => text,
        useForm: (defaults) => {
            return {
                ...defaults,
                processing: false,
                errors: {},
                reset() {
                    Object.assign(this, defaults);
                },
                clearErrors() {
                    this.errors = {};
                },
                transform(fn) {
                    callback = fn;

                    return this;
                },
                post(url, options) {
                    calls.push({ url, data: callback({ ...this }), options });
                },
            };
        },
        index: { url: () => '/admin/brands' },
        store: { url: () => '/admin/brands' },
        update: { url: (uuid) => `/admin/brands/${uuid}` },
        router: {},
        document: { getElementById: () => ({ focus() {} }) },
    };
    runInNewContext(
        transformSync(
            script +
                '\nglobalThis.result = {edit,save,form,open,mode,categoryOptions,chooseImport,importSource};',
            { loader: 'ts' },
        ).code,
        context,
    );

    return { ...context.result, calls };
}

test('new brands default to published and website logos while editing preserves saved choices', () => {
    const h = brandFormHarness();
    h.edit(null);
    assert.equal(h.form.is_published, true);
    assert.equal(h.mode.value, 'website');
    h.edit({
        uuid: 'existing',
        name: 'Brand',
        domain: 'brand.example',
        category: 'Tecnologia',
        aliases: [],
        is_published: false,
    });
    assert.equal(h.form.is_published, false);
    assert.equal(h.mode.value, 'keep');
});

test('brand forms use managed categories and save-and-new resets only after success', () => {
    const h = brandFormHarness();
    h.edit(null);
    assert.deepEqual([...h.categoryOptions.value], ['Tecnologia']);
    h.form.category = 'Tecnologia';
    h.form.name = 'Nuovo marchio';
    h.form.domain = 'brand.example';
    h.save(true);
    assert.equal(h.calls[0].data.fetch_logo, true);
    assert.equal(h.calls[0].data.is_published, true);
    assert.equal(h.form.name, 'Nuovo marchio');
    h.calls[0].options.onSuccess();
    assert.equal(h.open.value, true);
    assert.equal(h.form.name, '');
    assert.equal(h.form.domain, '');
    assert.equal(h.form.category, '');
    assert.equal(h.form.is_published, true);
    assert.equal(h.mode.value, 'website');
    h.save();
    h.calls[1].options.onSuccess();
    assert.equal(h.open.value, false);
});

test('brand navigation follows users and category filters and creation controls are present', () => {
    for (const path of [
        'resources/js/layouts/admin/Layout.vue',
        'resources/js/pages/admin/Index.vue',
    ]) {
        const source = readFileSync(path, 'utf8');
        assert.ok(
            source.indexOf("title: ui('Catalogo marchi')") >
                source.indexOf('icon: Users'),
        );
    }

    const source = readFileSync(
        'resources/js/pages/admin/Brands/Index.vue',
        'utf8',
    );
    assert.match(source, /v-model="categoryFilter"/);
    assert.match(source, /Nessuna categoria/);
    assert.match(source, /Salva e crea nuovo/);
    assert.ok(
        source.indexOf('<option value="website">') <
            source.indexOf('<option value="keep">'),
    );
});

test('choosing an import reuses the existing form and submits only the source identifier', () => {
    const h = brandFormHarness();
    h.chooseImport({
        uuid: 'source-logo',
        domain: 'example.com',
        logo_url: '/existing-logo',
    });
    assert.equal(h.open.value, true);
    assert.equal(h.form.name, 'example.com');
    assert.equal(h.form.domain, 'example.com');
    assert.equal(h.mode.value, 'keep');
    h.form.name = 'Example';
    h.form.category = 'Tecnologia';
    h.save(true);
    assert.equal(h.calls[0].data.source_logo_uuid, 'source-logo');
    assert.equal(h.calls[0].data.fetch_logo, false);
    assert.equal(h.calls[0].data.category, 'Tecnologia');
    assert.equal(h.calls[0].data.logo_path, undefined);
    assert.equal(h.importSource.value.uuid, 'source-logo');
    h.calls[0].options.onSuccess();
    assert.equal(h.form.source_logo_uuid, null);
    assert.equal(h.importSource.value, null);
    assert.equal(h.mode.value, 'website');
});

test('brand form edits and submits alternative domains separately from aliases without mutating saved props', () => {
    const h = brandFormHarness();
    const domains = ['apple.it', 'apple.de'];
    h.edit({
        uuid: 'apple',
        name: 'Apple',
        domain: 'apple.com',
        alternative_domains: domains,
        aliases: ['Mela'],
        is_published: true,
    });
    h.form.alternative_domains.splice(0, 1, 'apple.fr');
    h.save();
    assert.deepEqual(
        [...h.calls[0].data.alternative_domains],
        ['apple.fr', 'apple.de'],
    );
    assert.deepEqual([...h.calls[0].data.aliases], ['Mela']);
    assert.deepEqual(domains, ['apple.it', 'apple.de']);
    h.edit(null);
    assert.deepEqual([...h.form.alternative_domains], []);
});
