import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import tailwind from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

test(
    'all calendar surfaces visibly mark today independently of selection on mobile desktop and both themes',
    {
        skip: !process.env.BROWSER_MODULE_PATH,
        timeout: 120000,
    },
    async () => {
        const entry = `import {createApp,h} from 'vue';import {createAppI18n} from '/resources/js/i18n/index.ts';import AppDatePicker from '/resources/js/components/AppDatePicker.vue';import TransactionDatePicker from '/resources/js/components/transactions/TransactionDatePicker.vue';import Grid from '/resources/js/components/recurring/RecurringCalendarGrid.vue';import '/resources/css/app.css';const cells=[{date:'2026-10-21',dayNumber:21,isCurrentMonth:true,isToday:false,summary:null},{date:'2026-10-23',dayNumber:23,isCurrentMonth:true,isToday:true,summary:null}];createApp({render:()=>h('div',{style:'padding:24px'},[h('div',{id:'global-picker'},h(AppDatePicker,{modelValue:'2026-10-21'})),h('div',{id:'transaction-picker'},h(TransactionDatePicker,{modelValue:'2026-10-21',year:2026,month:10})),h(Grid,{calendarWeeks:[cells],weekdayLabels:[],selectedDate:'2026-10-21',formatMoney:String,formatDayHeading:String,filteredOccurrencesCount:()=>0})])}).use(createAppI18n({locale:{current:'it'}})).mount('#app');`;
        const server = await createServer({
            configFile: false,
            cacheDir: '/tmp/calendar-today-vite',
            optimizeDeps: {
                noDiscovery: true,
                include: [
                    'vue',
                    'vue-i18n',
                    '@vueuse/core',
                    'lucide-vue-next',
                    'reka-ui',
                    'v-calendar',
                    'clsx',
                    'tailwind-merge',
                ],
            },
            plugins: [
                vue(),
                tailwind(),
                {
                    name: 'calendar-today-fixture',
                    resolveId(id) {
                        if (id === '/calendar-today-entry.js') {
                            return id;
                        }
                    },
                    load(id) {
                        if (id === '/calendar-today-entry.js') {
                            return entry;
                        }
                    },
                    configureServer(server) {
                        server.middlewares.use((req, res, next) => {
                            if (req.url === '/calendar-today') {
                                res.setHeader('Content-Type', 'text/html');
                                res.end(
                                    /* language=TEXT */ '<html lang="it"><body><div id="app"></div><script type="module" src="/calendar-today-entry.js"></script></body></html>',
                                );
                            } else {
                                next();
                            }
                        });
                    },
                },
            ],
            resolve: { alias: { '@': resolve('resources/js') } },
            server: { host: '127.0.0.1', port: 0 },
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

            for (const width of [375, 1440]) {
                for (const theme of ['light', 'dark']) {
                    const page = await browser.newPage({
                        viewport: { width, height: 1000 },
                    });
                    const errors = [];
                    page.on('pageerror', (error) => errors.push(error.message));
                    await page.clock.install({
                        time: new Date('2026-10-23T10:00:00'),
                    });
                    await page.goto(
                        `http://127.0.0.1:${server.httpServer.address().port}/calendar-today`,
                    );

                    if (theme === 'dark') {
                        await page
                            .locator('html')
                            .evaluate((el) => el.classList.add('dark'));
                    }

                    const gridDot = page
                        .locator('[aria-current="date"] [data-today-dot]')
                        .filter({ visible: true });

                    try {
                        await gridDot.waitFor({ timeout: 5000 });
                    } catch {
                        throw new Error(
                            JSON.stringify({
                                errors,
                                body: await page.locator('body').innerText(),
                            }),
                        );
                    }

                    assert.equal(
                        await gridDot.evaluate(
                            (el) => el.getBoundingClientRect().width,
                        ),
                        4,
                    );
                    await page
                        .locator('#global-picker input[type=text]')
                        .click();
                    const globalDay = page.locator(
                        '.app-date-calendar .vc-day.is-today .vc-day-content',
                    );
                    await globalDay.waitFor();
                    const globalDot = await globalDay.evaluate((el) => {
                        const dot = getComputedStyle(el, '::after');

                        return {
                            content: dot.content,
                            width: dot.width,
                            height: dot.height,
                            color: dot.backgroundColor,
                            text: getComputedStyle(el).color,
                        };
                    });
                    assert.equal(globalDot.content, '""');
                    assert.equal(globalDot.width, '3px');
                    assert.equal(globalDot.height, '3px');
                    assert.equal(globalDot.color, globalDot.text);
                    await page.keyboard.press('Escape');
                    await page.locator('#transaction-picker input').click();
                    const today = page.locator(
                        '[aria-label="23 ottobre 2026"][aria-current="date"]',
                    );
                    await today.waitFor();
                    assert.equal(
                        await today.getAttribute('aria-pressed'),
                        'false',
                    );
                    assert.equal(
                        await today
                            .locator('[data-today-dot]')
                            .evaluate((el) => el.getBoundingClientRect().width),
                        4,
                    );
                    await page.screenshot({
                        path: `/tmp/calendar-today-${width}-${theme}.png`,
                        clip:
                            width === 375
                                ? { x: 16, y: 140, width: 350, height: 340 }
                                : undefined,
                    });
                    await page.close();
                }
            }
        } finally {
            await browser?.close();
            await server.close();
        }
    },
);
