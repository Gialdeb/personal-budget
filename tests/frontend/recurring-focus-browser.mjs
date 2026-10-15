import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(
    pathToFileURL(process.env.RECURRING_BROWSER_MODULE_PATH + '/index.mjs')
);
const origin = process.env.RECURRING_BROWSER_URL;
const fixtures = JSON.parse(process.env.RECURRING_BROWSER_FIXTURES);
const width = Number(process.env.RECURRING_BROWSER_WIDTH);
const standalone = process.env.RECURRING_BROWSER_STANDALONE === 'true';
const browser = await chromium.launch({ headless: true, channel: 'chrome' });
const context = await browser.newContext({
    viewport: { width, height: 844 },
    isMobile: width < 600,
    hasTouch: width < 600,
    reducedMotion: standalone ? 'reduce' : 'no-preference',
});
// The actual application worker stays active; external push SDKs are irrelevant to navigation.
await context.route('https://www.gstatic.com/firebasejs/**', (route) =>
    route.fulfill({
        contentType: 'text/javascript',
        body: 'self.firebase = {initializeApp(){}, messaging:()=>({onBackgroundMessage(){}})};',
    }),
);
await context.addInitScript(
    ({ standalone }) => {
        if (standalone) {
            const original = window.matchMedia;
            window.matchMedia = (query) => {
                const result = original(query);

                if (query === '(display-mode: standalone)') {
                    Object.defineProperty(result, 'matches', { value: true });
                }

                return result;
            };
        }

        if (!localStorage.getItem('transactions.visibilityFilter')) {
            localStorage.setItem('transactions.visibilityFilter', 'deleted');
        }

        localStorage.setItem(
            'soamco-budget-cookie-consent',
            JSON.stringify({
                version: 1,
                updatedAt: new Date().toISOString(),
                preferences: {
                    necessary: true,
                    preferences: false,
                    analytics: false,
                    marketing: false,
                },
            }),
        );
        window.focusScrolls = [];
        window.progressSeen = false;
        window.inputEvents = [];
        addEventListener('beforeunload', () =>
            sessionStorage.setItem(
                'focus-beforeunload',
                JSON.stringify({
                    events: window.inputEvents,
                    version: document.querySelector(
                        'meta[name="soamco-asset-version"]',
                    )?.content,
                    endpoint: document.querySelector(
                        'meta[name="soamco-asset-version-endpoint"]',
                    )?.content,
                }),
            ),
        );

        for (const name of ['touchstart', 'touchend', 'click']) {
            document.addEventListener(
                name,
                (event) =>
                    window.inputEvents.push({
                        name,
                        text: event.target.textContent?.trim().slice(0, 100),
                    }),
                true,
            );
        }

        document.addEventListener('inertia:start', () =>
            requestAnimationFrame(() => {
                const bar = document.querySelector(
                    '[data-navigation-progress]',
                );
                window.progressSeen ||=
                    !!bar && getComputedStyle(bar).display !== 'none';
            }),
        );
        const scroll = HTMLElement.prototype.scrollIntoView;
        HTMLElement.prototype.scrollIntoView = function (options) {
            const uuid = this.dataset.transactionRow;

            if (uuid) {
                window.focusScrolls.push({
                    uuid,
                    connected: this.isConnected,
                    visible: this.getClientRects().length > 0,
                    options,
                    beforeY: this.getBoundingClientRect().y,
                });
            }

            return scroll.call(this, options);
        };
    },
    { standalone },
);
const page = await context.newPage();
page.setDefaultTimeout(15000);
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
const input = async (locator) => {
    await locator.evaluate((el) =>
        el.scrollIntoView({ block: 'center', behavior: 'instant' }),
    );

    return width < 600 ? locator.tap() : locator.click();
};

try {
    await page.goto(origin + '/login');
    await page.waitForFunction(
        () => performance.getEntriesByName('soamco:mounted').length > 0,
    );
    await page.waitForLoadState('networkidle');
    const cookies = page.getByRole('button', {
        name: /Solo necessari|Solo essenziali|Rifiuta/,
    });

    if (await cookies.count()) {
        await cookies.first().click();
    }

    await page.locator('#email').fill('recurring-focus@example.test');
    await page.locator('#password').fill('password');
    const authenticatedDocument = page.waitForEvent('load');
    await input(page.locator('form button[type=submit]'));
    await authenticatedDocument;
    await page.waitForURL((u) => !u.pathname.includes('/login'));
    // Authentication intentionally reloads the private shell for session isolation.
    await page.goto(origin + '/recurring-entries/' + fixtures[0].entry);
    await page.waitForFunction(
        () => performance.getEntriesByName('soamco:mounted').length > 0,
    );
    await page.waitForFunction(() => !!navigator.serviceWorker.controller);
    await page.waitForLoadState('networkidle');
    const initialUpdate = page.getByRole('button', {
        name: 'Aggiorna ora',
        exact: true,
    });

    if (await initialUpdate.isVisible()) {
        const activatedDocument = page.waitForEvent('load');
        await initialUpdate.click();
        await activatedDocument;
        await page.waitForFunction(() => !!navigator.serviceWorker.controller);
    }

    page.on('response', async (response) => {
        if (
            response.request().headers()['x-inertia'] &&
            response.headers()['x-inertia']
        ) {
            const data = await response.json().catch(() => null);

            if (data?.props?.errors && Object.keys(data.props.errors).length) {
                console.log('Validation:', JSON.stringify(data.props.errors));
            }
        }
    });
    let posts = 0;
    let gets = 0;
    let transactionUuid = null;
    page.on('request', (request) => {
        if (
            request.method() === 'POST' &&
            (request.url().includes('/convert') ||
                /\/transactions\/2026\/\d+$/.test(request.url()))
        ) {
            posts++;
        }

        if (
            request.method() === 'GET' &&
            /\/transactions\/2026\/\d/.test(request.url()) &&
            request.headers()['x-inertia']
        ) {
            gets++;
        }
    });

    for (const fixture of fixtures) {
        // Consecutive visits use the same Inertia shell, as in the installed PWA.
        await page.evaluate(() =>
            localStorage.setItem('transactions.visibilityFilter', 'deleted'),
        );
        await page.evaluate(
            (url) =>
                document
                    .querySelector('#app')
                    .__vue_app__.config.globalProperties.$inertia.visit(url),
            '/recurring-entries/' + fixture.entry,
        );
        await page.waitForURL(
            (url) => url.pathname === '/recurring-entries/' + fixture.entry,
        );
        await page.waitForFunction(
            () => performance.getEntriesByName('soamco:mounted').length > 0,
        );
        await page.waitForFunction(() => !!navigator.serviceWorker.controller);
        const action = page
            .getByRole('button', {
                name: fixture.payment
                    ? 'Registra pagamento'
                    : 'Converti in Transazione',
                exact: true,
            })
            .filter({ visible: true });
        await action.waitFor();
        await page.waitForLoadState('networkidle');
        const update = page.getByRole('button', {
            name: 'Aggiorna ora',
            exact: true,
        });

        if (await update.isVisible()) {
            const updatedDocument = page.waitForEvent('load');
            await update.click();
            await updatedDocument;
            await page.waitForFunction(
                () => performance.getEntriesByName('soamco:mounted').length > 0,
            );
            await action.waitFor();
        }

        await page.evaluate(() => {
            window.focusScrolls = [];
            window.progressSeen = false;
        });
        const beforePosts = posts;
        const beforeGets = gets;
        const response = page.waitForResponse(
            (r) =>
                r.request().method() === 'GET' &&
                r.url().includes('/transactions/2026/') &&
                r.request().headers()['x-inertia'],
        );
        const submission = fixture.payment
            ? page.getByRole('dialog').getByRole('button', {
                  name: 'Crea registrazione',
                  exact: true,
              })
            : action;

        if (fixture.payment) {
            await input(action);
            await submission.waitFor();
        }

        await submission.evaluate((el) =>
            el.addEventListener(
                'click',
                () => queueMicrotask(() => el.click()),
                { once: true },
            ),
        );
        const [completed] = await Promise.all([
            response,
            input(submission),
        ]).catch(async (error) => {
            console.log(
                'Input diagnostic',
                JSON.stringify(
                    await page.evaluate(() => ({
                        events: window.inputEvents,
                        previous: sessionStorage.getItem('focus-beforeunload'),
                        url: location.href,
                        errors: document.querySelector('[role=alert]')
                            ?.textContent,
                    })),
                ),
            );
            await page.screenshot({
                path: '/private/tmp/recurring-focus-failure.png',
            });

            throw error;
        });
        const payload = await completed.json();
        transactionUuid = payload.props.monthlySheet.transactions.find(
            (row) => row.description === fixture.title,
        ).uuid;
        assert.notEqual(transactionUuid, fixture.occurrence);
        const row = page.locator(
            `[data-transaction-row="${transactionUuid}"]:visible`,
        );
        await row.waitFor();

        if (fixture.payment) {
            await page.getByRole('dialog').waitFor({ state: 'hidden' });
        }

        await page.waitForFunction(
            (uuid) =>
                document.activeElement?.getAttribute('data-transaction-row') ===
                uuid,
            transactionUuid,
        );
        await page.waitForURL(
            (u) =>
                u.pathname === `/transactions/2026/${fixture.month}` &&
                !u.searchParams.has('highlight'),
        );
        const rect = await row.boundingBox();
        assert.ok(rect.y >= 0 && rect.y < 650, JSON.stringify(rect));
        assert.match(await row.getAttribute('class'), /bg-amber-50/);
        const scrolls = await page.evaluate(() => window.focusScrolls);
        assert.equal(scrolls.length, 1);
        assert.ok(scrolls[0].connected && scrolls[0].visible);
        assert.equal(await page.evaluate(() => window.progressSeen), true);
        assert.equal(posts - beforePosts, 1);
        assert.equal(gets - beforeGets, 1);
        assert.equal(
            await page.evaluate(() => !!navigator.serviceWorker.controller),
            true,
        );
        console.log(
            JSON.stringify({
                width,
                standalone,
                title: fixture.title,
                month: fixture.month,
                transactionUuid,
                scrolls: scrolls.length,
                posts: posts - beforePosts,
                gets: gets - beforeGets,
                y: Math.round(rect.y),
                beforeY: Math.round(scrolls[0].beforeY),
            }),
        );

        if (fixture.payment) {
            // An explicit new focus command while the row is already in the viewport.
            const requestsBeforeClientFocus = gets;
            await page.evaluate((uuid) => {
                document
                    .querySelector('#app')
                    .__vue_app__.config.globalProperties.$inertia.replace({
                        url: location.pathname + '?highlight=' + uuid,
                        preserveState: false,
                        preserveScroll: true,
                    });
            }, transactionUuid);
            await page.waitForFunction(() => window.focusScrolls.length === 2);
            await page.waitForURL((url) => !url.searchParams.has('highlight'));
            const visibleFocus = await page.evaluate(() =>
                window.focusScrolls.at(-1),
            );
            assert.ok(
                visibleFocus.beforeY >= 0 && visibleFocus.beforeY < 844,
                JSON.stringify(visibleFocus),
            );
            assert.equal(gets, requestsBeforeClientFocus);
        }

        await page.reload();
        await row.waitFor();
        assert.equal(
            (await page.evaluate(() => window.focusScrolls)).length,
            0,
        );
        assert.doesNotMatch(await row.getAttribute('class'), /bg-amber-50/);
    }

    let releaseIdentity;
    const identityGate = new Promise((resolve) => {
        releaseIdentity = resolve;
    });
    const identityPattern = '**/session/identity?*';
    await context.route(identityPattern, async (route) => {
        await identityGate;
        await route.continue();
    });
    await page.goto(
        origin + '/transactions/2026/10?highlight=' + transactionUuid,
    );
    await page.waitForFunction(
        () => performance.getEntriesByName('soamco:mounted').length > 0,
    );
    assert.equal(
        await page.evaluate(() =>
            document.documentElement.hasAttribute('data-session-unverified'),
        ),
        true,
    );
    assert.equal((await page.evaluate(() => window.focusScrolls)).length, 0);
    releaseIdentity();
    await page.waitForFunction(
        (uuid) =>
            document.activeElement?.getAttribute('data-transaction-row') ===
            uuid,
        transactionUuid,
    );
    await page.waitForURL((url) => !url.searchParams.has('highlight'));
    assert.equal((await page.evaluate(() => window.focusScrolls)).length, 1);
    await context.unroute(identityPattern);
    const requestsBeforeMissing = gets;
    await page.goto(
        origin +
            '/transactions/2026/10?highlight=00000000-0000-4000-8000-000000000000',
    );
    await page.locator('[data-transaction-focus-missing]').waitFor();
    await page.waitForURL((url) => !url.searchParams.has('highlight'));
    assert.equal(gets, requestsBeforeMissing);
    assert.equal((await page.evaluate(() => window.focusScrolls)).length, 0);
    assert.deepEqual(errors, []);
} finally {
    await browser.close();
}
