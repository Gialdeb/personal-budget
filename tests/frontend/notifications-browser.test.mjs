import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test(
    'global notifications cover forms, hidden errors, JSON saves and mobile gestures',
    { skip: !process.env.NOTIFICATIONS_BROWSER_MODULE_PATH, timeout: 120000 },
    async () => {
        const { chromium } = await import(
            pathToFileURL(
                process.env.NOTIFICATIONS_BROWSER_MODULE_PATH + '/index.mjs',
            )
        );
        const entry = `
        import { createApp,h } from 'vue';
        import { createInertiaApp,router,Link } from '@inertiajs/vue3';
        import { createNotivue,push } from 'notivue';
        import { notificationConfig } from '/resources/js/lib/notification-config.js';
        import { createAppI18n } from '/resources/js/i18n/index.ts';
        import { useForm } from '/resources/js/composables/useNotifiedForm.ts';
        import { fetchWithNotifications,notify } from '/resources/js/lib/notifications.ts';
        import AppNotifications from '/resources/js/components/AppNotifications.vue';
        import InputError from '/resources/js/components/InputError.vue';
        import 'notivue/notification.css';import 'notivue/notification-progress.css';import 'notivue/animations.css';
        import '/resources/css/app.css';
        const Fixture={components:{Link,InputError},setup(){
            const form=useForm({name:''});
            return {form,save:()=>form.post('/form-success'),error:()=>form.post('/form-error'),bad:()=>form.post('/form-failure'),offline:()=>form.post('/offline'),local:()=>form.setError('name','Controlla il campo nascosto'),json:()=>fetchWithNotifications('/api/save',{method:'POST'}),jsonError:()=>fetchWithNotifications('/api/error',{method:'POST'}),preview:()=>fetchWithNotifications('/api/preview',{method:'POST'}),slow:()=>fetchWithNotifications('/api/slow',{method:'POST'}),duplicate:()=>{push.success('Uguale');push.success('Uguale');},many:()=>{for(let i=0;i<5;i++) push.info('Informazione '+i)},clear:()=>push.destroyAll(),short:()=>push.info({message:'Pausa tocco',duration:700})};
        },template:'<main class="mx-auto max-w-3xl space-y-3 px-5 pt-64"><h1>Notifiche</h1><div class="flex flex-wrap gap-3"><button @click="save">Salva</button><button @click="error">Errore server</button><button @click="local">Errore nascosto</button><button @click="bad">Errore tecnico</button><button @click="offline">Offline</button><button @click="json">Salva JSON</button><button @click="jsonError">Errore JSON</button><button @click="preview">Anteprima</button><button @click="slow">Lento</button><button @click="duplicate">Duplicato</button><button @click="many">Molte</button><button @click="clear">Pulisci</button><button @click="short">Breve</button></div><InputError v-for="(message,field) in form.errors" :key="field" :message="message" /><form><label>Nome<input name="name" required class="border" /></label><button>Invia nativo</button></form><Link href="/other">Altra pagina</Link></main>'};
        createInertiaApp({resolve:()=>Fixture,progress:false,setup({el,App,props,plugin}){createApp({render:()=>h('div',[h(AppNotifications),h(App,props)])}).use(plugin).use(createNotivue(notificationConfig)).use(createAppI18n(props.initialPage.props)).mount(el)}});
    `;
        const data = (url, props = {}) => ({
            component: 'Fixture',
            url,
            version: 'fixture',
            props: {
                auth: { user: { uuid: 'test-user' } },
                locale: { current: 'it' },
                flash: {},
                errors: {},
                ...props,
            },
        });
        const server = await createServer({
            configFile: false,
            root: process.cwd(),
            cacheDir: '/tmp/soamco-notifications-vite',
            resolve: {
                alias: {
                    '@': resolve('resources/js'),
                    vue: resolve('node_modules/vue/dist/vue.esm-bundler.js'),
                },
            },
            optimizeDeps: {
                entries: [
                    'resources/js/components/AppNotifications.vue',
                    'resources/js/composables/useNotifiedForm.ts',
                ],
            },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'notification-fixture',
                    resolveId(id) {
                        if (id === '/notification-fixture.js') {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/notification-fixture.js') {
                            return entry;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use((req, res, next) => {
                            const url = new URL(req.url, 'http://local');

                            if (url.pathname.startsWith('/api/')) {
                                res.setHeader(
                                    'Content-Type',
                                    'application/json',
                                );

                                if (url.pathname === '/api/error') {
                                    res.statusCode = 422;
                                    res.end(
                                        JSON.stringify({
                                            errors: {
                                                hidden: [
                                                    'Campo obbligatorio JSON',
                                                ],
                                            },
                                        }),
                                    );

                                    return;
                                }

                                const respond = () =>
                                    res.end(
                                        JSON.stringify({
                                            message:
                                                url.pathname === '/api/preview'
                                                    ? 'Anteprima pronta'
                                                    : 'Budget salvato',
                                        }),
                                    );

                                if (url.pathname === '/api/slow') {
                                    setTimeout(respond, 1200);
                                } else {
                                    respond();
                                }

                                return;
                            }

                            if (
                                ![
                                    '/',
                                    '/other',
                                    '/form-success',
                                    '/form-error',
                                    '/form-failure',
                                    '/offline',
                                ].includes(url.pathname)
                            ) {
                                return next();
                            }

                            if (url.pathname === '/form-failure') {
                                res.statusCode = 500;
                                res.end('<h1>Private technical exception</h1>');

                                return;
                            }

                            const page = data(
                                req.url,
                                url.pathname === '/form-success'
                                    ? {
                                          flash: {
                                              success:
                                                  'Movimento salvato correttamente',
                                          },
                                      }
                                    : url.pathname === '/form-error'
                                      ? {
                                            errors: {
                                                'translations.en.title':
                                                    'Compila il titolo inglese',
                                                hidden_id: 'Seleziona un conto',
                                            },
                                        }
                                      : {},
                            );

                            if (req.headers['x-inertia']) {
                                res.setHeader(
                                    'Content-Type',
                                    'application/json',
                                );
                                res.setHeader('X-Inertia', 'true');
                                res.end(JSON.stringify(page));

                                return;
                            }

                            res.setHeader('Content-Type', 'text/html');
                            res.end(
                                '<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app" data-page="' +
                                    JSON.stringify(page)
                                        .replaceAll('&', '&amp;')
                                        .replaceAll('"', '&quot;') +
                                    '"></div><script type="module" src="/notification-fixture.js"></script></body></html>',
                            );
                        });
                    },
                },
            ],
            server: { host: '127.0.0.1', port: 5197, hmr: false, watch: null },
        });
        let browser;

        try {
            await server.listen();
            browser = await chromium.launch({
                headless: true,
                channel: 'chrome',
            });

            for (const width of [1440, 375]) {
                const context = await browser.newContext({
                    viewport: { width, height: 1000 },
                    hasTouch: true,
                    reducedMotion: 'reduce',
                });
                const page = await context.newPage();
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.route('**/offline', (route) => route.abort());
                await page.goto('http://127.0.0.1:5197');
                const items = page.locator('[data-notivue-item]');
                const toasts = page.locator('.Notivue__notification');
                const clear = async () => {
                    await page
                        .getByRole('button', { name: 'Pulisci', exact: true })
                        .evaluate((button) => button.click());
                    await page.waitForFunction(
                        () => !document.querySelector('[data-notivue-item]'),
                    );
                };
                await page
                    .getByRole('button', { name: 'Salva', exact: true })
                    .click();
                await page
                    .getByText('Movimento salvato correttamente', {
                        exact: true,
                    })
                    .waitFor();
                assert.equal(await items.count(), 1);
                await page.emulateMedia({ reducedMotion: 'no-preference' });
                const progress = toasts.first().locator('.Notivue__progress');
                await progress.waitFor({ state: 'visible' });
                const initialProgress = await progress.evaluate(
                    (el) => getComputedStyle(el).transform,
                );
                await page.waitForTimeout(150);
                assert.notEqual(
                    await progress.evaluate(
                        (el) => getComputedStyle(el).transform,
                    ),
                    initialProgress,
                );
                await page.emulateMedia({ reducedMotion: 'reduce' });
                assert.equal(await progress.isVisible(), false);
                const bounds = await toasts.first().boundingBox();
                assert.ok(bounds.y < 60);

                if (width === 1440) {
                    assert.ok(bounds.width > 470);
                    assert.ok(width - bounds.x - bounds.width < 25);
                } else {
                    assert.ok(bounds.width > 340);
                    assert.ok(
                        Math.abs(bounds.x - (width - bounds.width) / 2) < 5,
                    );
                }

                if (process.env.NOTIFICATIONS_SCREENSHOTS) {
                    await page.screenshot({
                        path: `${process.env.NOTIFICATIONS_SCREENSHOTS}/notifications-${width}.png`,
                    });
                }

                await page
                    .getByRole('link', { name: 'Altra pagina', exact: true })
                    .click();
                assert.equal(await items.count(), 1);
                await clear();
                await page
                    .getByRole('button', { name: 'Errore server', exact: true })
                    .click();
                await page
                    .getByText('Controlla i dati inseriti', { exact: true })
                    .waitFor();
                assert.match(
                    await toasts.innerText(),
                    /Compila il titolo inglese/,
                );
                assert.match(await toasts.innerText(), /Seleziona un conto/);
                assert.equal(await items.count(), 1);
                await clear();
                await page
                    .getByRole('button', {
                        name: 'Errore nascosto',
                        exact: true,
                    })
                    .click();
                await toasts.getByText(/Controlla il campo nascosto/).waitFor();
                await clear();
                await page
                    .getByRole('button', { name: 'Invia nativo', exact: true })
                    .click();
                await page
                    .getByText('Controlla i dati inseriti', { exact: true })
                    .waitFor();
                await clear();
                await page
                    .getByRole('button', { name: 'Salva JSON', exact: true })
                    .click();
                await page
                    .getByText('Budget salvato', { exact: true })
                    .waitFor();
                await clear();
                await page
                    .getByRole('button', { name: 'Errore JSON', exact: true })
                    .click();
                await page.getByText(/Campo obbligatorio JSON/).waitFor();
                await clear();
                await page
                    .getByRole('button', { name: 'Anteprima', exact: true })
                    .click();
                await page.waitForTimeout(300);
                assert.equal(await items.count(), 0);
                await page
                    .getByRole('button', {
                        name: 'Errore tecnico',
                        exact: true,
                    })
                    .click();
                await page
                    .getByText('Operazione non riuscita', { exact: true })
                    .waitFor();
                assert.equal(await page.locator('dialog').count(), 0);
                assert.doesNotMatch(
                    await toasts.innerText(),
                    /Private technical/,
                );
                await clear();
                await page
                    .getByRole('button', { name: 'Offline', exact: true })
                    .click();
                await page.getByText(/Connessione non disponibile/).waitFor();
                await clear();
                await page
                    .getByRole('button', { name: 'Lento', exact: true })
                    .click();
                await page
                    .getByText('Operazione in corso…', { exact: true })
                    .waitFor();
                await page
                    .getByText('Budget salvato', { exact: true })
                    .waitFor();
                await page.waitForFunction(
                    () =>
                        !document.body.textContent.includes(
                            'Operazione in corso…',
                        ),
                );
                await clear();
                await page
                    .getByRole('button', { name: 'Duplicato', exact: true })
                    .click();
                assert.equal(await items.count(), 1);
                await clear();
                await page
                    .getByRole('button', { name: 'Molte', exact: true })
                    .click();
                assert.equal(await items.count(), 3);
                await clear();
                await page
                    .getByRole('button', { name: 'Breve', exact: true })
                    .click();
                await toasts.first().dispatchEvent('pointerdown', {
                    pointerType: 'touch',
                    bubbles: true,
                });
                await page.waitForTimeout(900);
                assert.equal(await items.count(), 1);
                await clear();
                await page
                    .getByRole('button', { name: 'Duplicato', exact: true })
                    .click();
                const swipe = await toasts.first().boundingBox();
                await page.mouse.move(swipe.x + 45, swipe.y + swipe.height / 2);
                await page.mouse.down();
                await page.mouse.move(
                    swipe.x + swipe.width + 80,
                    swipe.y + swipe.height / 2,
                    { steps: 12 },
                );
                await page.mouse.up();
                await page.waitForFunction(
                    () => !document.querySelector('[data-notivue-item]'),
                );
                assert.ok(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <= innerWidth,
                    ),
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
