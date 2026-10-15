import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(
    pathToFileURL(process.env.ECONOMIC_BROWSER_MODULE_PATH + '/index.mjs')
);
const origin = process.env.ECONOMIC_BROWSER_URL;
const fixtures = JSON.parse(process.env.ECONOMIC_BROWSER_FIXTURES);
const width = Number(process.env.ECONOMIC_BROWSER_WIDTH);
const standalone = process.env.ECONOMIC_BROWSER_STANDALONE === 'true';
const browser = await chromium.launch({ headless: true, channel: 'chrome' });
let activePage;

try {
    for (const name of Object.keys(fixtures)) {
        const context = await browser.newContext({
            viewport: { width, height: 900 },
            isMobile: width < 600,
            hasTouch: width < 600,
            reducedMotion: standalone ? 'reduce' : 'no-preference',
        });
        await context.addInitScript(
            ({ standalone }) => {
                if (standalone) {
                    const original = window.matchMedia.bind(window);
                    window.matchMedia = (query) => {
                        const match = original(query);

                        if (query === '(display-mode: standalone)') {
                            Object.defineProperty(match, 'matches', {
                                value: true,
                            });
                        }

                        return match;
                    };
                }
            },
            { standalone },
        );
        await context.route('https://www.gstatic.com/firebasejs/**', (route) =>
            route.fulfill({
                contentType: 'text/javascript',
                body: 'self.firebase={initializeApp(){}, messaging:()=>({onBackgroundMessage(){}})};',
            }),
        );
        const page = await context.newPage();
        activePage = page;
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        page.setDefaultTimeout(15000);

        for (let attempt = 0; attempt < 40; attempt++) {
            try {
                await page.goto(origin + '/login', {
                    waitUntil: 'domcontentloaded',
                });
                break;
            } catch (error) {
                if (attempt === 39) {
                    throw error;
                }

                await new Promise((resolve) => setTimeout(resolve, 100));
            }
        }

        const consent = page.getByRole('button', {
            name: /Solo necessari|Solo essenziali|Rifiuta/,
        });

        await consent.first().waitFor();
        await consent.first().click();

        await page.locator('#email').fill(fixtures[name].email);
        await page.locator('#password').fill('password');
        await page.locator('form button[type=submit]').click();
        await page.waitForURL((url) => !url.pathname.includes('/login'), {
            waitUntil: 'domcontentloaded',
        });
        await page
            .getByRole('button', { name: /Apri menu utente|Open user menu/ })
            .waitFor();
        await page.goto(origin + '/shared-expenses', {
            waitUntil: 'domcontentloaded',
        });
        await page
            .getByRole('heading', { name: 'Spese condivise', exact: true })
            .last()
            .waitFor();
        assert.equal(
            await page
                .getByRole('button', { name: 'Da sistemare', exact: true })
                .getAttribute('aria-pressed'),
            'true',
        );
        assert.equal(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
            true,
            `${name}: no shared overflow`,
        );

        if (name === 'P') {
            await page
                .getByRole('button', {
                    name: 'Collega i rimborsi esistenti',
                    exact: true,
                })
                .click();
            await page.waitForURL((url) =>
                url.pathname.startsWith('/counterparties/'),
            );
            const allocatePayment = page
                .getByRole('button', {
                    name: 'Ripartisci pagamento',
                    exact: true,
                })
                .last();
            await allocatePayment.evaluate((button) =>
                button.scrollIntoView({ block: 'center', behavior: 'instant' }),
            );
            await allocatePayment.click();
            await page.locator('input[id^=allocation-]').fill('100');
            await page
                .getByRole('button', {
                    name: 'Conferma ripartizione',
                    exact: true,
                })
                .click();
            await page
                .getByRole('button', {
                    name: 'Conferma pagamento',
                    exact: true,
                })
                .waitFor();
            await page.goto(origin + '/shared-expenses', {
                waitUntil: 'domcontentloaded',
            });
            await page
                .getByRole('heading', { name: 'Spese condivise', exact: true })
                .last()
                .waitFor();
            assert.equal(
                await page
                    .getByRole('heading', {
                        name: 'Verifica necessaria',
                        exact: true,
                    })
                    .count(),
                0,
            );
            await page
                .getByRole('button', { name: 'Regolate', exact: true })
                .click();
            await page
                .getByText('Quota da collegare', { exact: true })
                .waitFor();
            await page.evaluate(async () => {
                await navigator.serviceWorker.ready;
            });
            assert.equal(
                await page.evaluate(
                    () => navigator.serviceWorker.controller !== null,
                ),
                true,
            );
            assert.deepEqual(errors, []);
            console.log(
                `Shared existing repayment allocation OK: ${width}, standalone=${standalone}`,
            );
            await context.close();
            continue;
        }

        if (name === 'S') {
            const exactLink = page.getByRole('link', {
                name: 'Apri e verifica la transazione',
                exact: true,
            });
            const href = await exactLink.getAttribute('href');
            assert.equal(
                new URL(href, origin).searchParams.get('highlight'),
                fixtures[name].transaction,
            );
            assert.equal(
                new URL(href, origin).searchParams.get('edit'),
                fixtures[name].transaction,
            );
            await exactLink.click();
            const editor = page.getByRole('dialog');
            await editor.waitFor();
            await page.waitForFunction(() => {
                const dialog = document.querySelector('[role=dialog]');

                return (
                    dialog &&
                    (dialog.textContent.includes('Transazione da verificare') ||
                        [...dialog.querySelectorAll('input,textarea')].some(
                            (field) =>
                                field.value === 'Transazione da verificare',
                        ))
                );
            });
            await editor
                .getByRole('button', { name: /Conferma e salva/ })
                .last()
                .click();
            await page.waitForURL((url) => url.pathname === '/shared-expenses');
            await page
                .getByRole('button', {
                    name: 'Conferma riferimento',
                    exact: true,
                })
                .click();
            const confirmation = page.getByRole('dialog');
            await confirmation.waitFor();
            await confirmation
                .getByRole('button', {
                    name: 'Scegli il riferimento',
                    exact: true,
                })
                .click();
            await page
                .getByRole('button', { name: 'Mario Rossi', exact: true })
                .click();
            await confirmation
                .getByRole('button', {
                    name: 'Conferma riferimento',
                    exact: true,
                })
                .click();
            await confirmation.waitFor({ state: 'hidden' });
            assert.equal(
                await page
                    .getByRole('heading', {
                        name: 'Verifica necessaria',
                        exact: true,
                    })
                    .count(),
                0,
            );
            assert.equal(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
                true,
            );
            await page.evaluate(async () => {
                await navigator.serviceWorker.ready;
            });
            assert.equal(
                await page.evaluate(
                    () => navigator.serviceWorker.controller !== null,
                ),
                true,
            );
            assert.deepEqual(errors, []);
            console.log(
                `Shared review transaction/editor/return/external reference OK: ${width}, standalone=${standalone}`,
            );
            await page.screenshot({
                path: `/private/tmp/shared-review-${width}-${standalone}.png`,
                fullPage: true,
            });
            await page
                .locator(`a[href*="highlight=${fixtures[name].transaction}"]`)
                .first()
                .click();
            await page.getByRole('dialog').waitFor();
            await page.goBack();
            await page
                .getByRole('button', { name: 'Da sistemare', exact: true })
                .waitFor();
            assert.equal(
                await page
                    .getByRole('button', { name: 'Da sistemare', exact: true })
                    .getAttribute('aria-pressed'),
                'true',
            );
            assert.equal(await page.getByRole('dialog').count(), 0);
            await context.close();
            continue;
        }

        if (name === 'R') {
            assert.equal(
                await page
                    .getByRole('heading', {
                        name: 'Roberta Delta',
                        exact: true,
                    })
                    .count(),
                0,
            );
            await page.goto(origin + '/credits-debts', {
                waitUntil: 'domcontentloaded',
            });
            await page
                .getByRole('button')
                .filter({ hasText: 'Roberta Delta' })
                .filter({ hasText: 'Conferma riferimento' })
                .click();
            const dialog = page.getByRole('dialog');
            assert.equal(
                await dialog
                    .getByText(
                        'Questo è un credito/debito precedente. Conferma che Roberta Delta è il riferimento corretto.',
                        { exact: true },
                    )
                    .count(),
                1,
            );
            await dialog
                .getByRole('button', {
                    name: 'Conferma Roberta Delta',
                    exact: true,
                })
                .click();
            await dialog
                .getByText(
                    'Riferimento confermato. Salva per completare la modifica.',
                    { exact: true },
                )
                .waitFor();
            const saved = page.waitForResponse(
                (response) =>
                    response.request().method() === 'PUT' &&
                    response.url().includes(fixtures.R.item),
            );
            await dialog
                .getByRole('button', { name: 'Aggiorna', exact: true })
                .click();
            assert.ok((await saved).status() < 400);
            await dialog.waitFor({ state: 'hidden' });
            await page.goto(origin + '/shared-expenses', {
                waitUntil: 'domcontentloaded',
            });
            const heading = page.getByRole('heading', {
                name: 'Roberta Delta',
                exact: true,
            });
            await heading.waitFor();
            assert.equal(await heading.count(), 1);
            const relationship = heading.locator('..');
            const copy = (await relationship.innerText()).replace(/\s/g, '');
            assert.ok(copy.includes('350,00'), copy);
            assert.ok(copy.includes('Creditomanuale'), copy);
            assert.ok(copy.includes('Nessunaspesacondivisaregistrata'), copy);
            assert.equal(
                await page
                    .getByRole('heading', {
                        name: 'Da confermare',
                        exact: true,
                    })
                    .count(),
                0,
            );
            assert.equal(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
                true,
            );
            await relationship.screenshot({
                path:
                    '/private/tmp/roberta-relationship-' +
                    width +
                    '-' +
                    standalone +
                    '.png',
            });
            await relationship
                .getByRole('link', {
                    name: 'Vedi situazione completa',
                    exact: true,
                })
                .click();
            await page
                .getByRole('heading', {
                    name: 'Situazione con Roberta Delta',
                    exact: true,
                })
                .waitFor();
            await page.waitForFunction(
                () => navigator.serviceWorker.controller !== null,
            );
            assert.equal(errors.length, 0, errors.join('\n'));
            console.log(
                JSON.stringify({
                    case: 'Roberta',
                    width,
                    standalone,
                    confirmed: true,
                    balance: '350.00',
                    manualOnly: true,
                    serviceWorker: true,
                    errors: 0,
                }),
            );
            await context.close();
            continue;
        }

        const card = page
            .getByRole('heading', { name: 'Mario Rossi', exact: true })
            .locator('..');

        if (name !== 'D') {
            const copy = (await card.innerText()).replace(/\s/g, '');
            assert.ok(
                copy.includes(
                    name === 'A' ? '61,00' : name === 'B' ? '341,00' : '120,00',
                ),
                copy,
            );
            assert.ok(
                copy.includes('Spesecondivise') &&
                    copy.includes('Crediti/debitimanuali'),
                copy,
            );

            if (name === 'C') {
                assert.ok(copy.includes('DeviaMarioRossi'), copy);
            }

            assert.equal(
                await page
                    .getByRole('heading', { name: 'Pino Mauro', exact: true })
                    .count(),
                name === 'A' ? 1 : 0,
            );
            await card
                .getByRole('link', {
                    name: 'Vedi situazione completa',
                    exact: true,
                })
                .click();
            await page
                .getByRole('heading', {
                    name: 'Situazione con Mario Rossi',
                    exact: true,
                })
                .waitFor();
            assert.equal(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
                true,
                `${name}: no complete situation overflow`,
            );
        } else {
            await page
                .getByRole('heading', { name: 'Parziale', exact: true })
                .waitFor();
            assert.equal(
                await page
                    .getByRole('heading', { name: 'Regolata', exact: true })
                    .count(),
                0,
            );
            const partial = page.locator('article').filter({
                has: page.getByRole('heading', {
                    name: 'Parziale',
                    exact: true,
                }),
            });
            assert.match(await partial.innerText(), /Parzialmente regolata/);

            for (const amount of ['50,00', '20,00', '30,00']) {
                assert.ok((await partial.innerText()).includes(amount));
            }

            await page
                .getByRole('button', { name: 'Regolate', exact: true })
                .click();
            await page
                .getByRole('heading', { name: 'Regolata', exact: true })
                .waitFor();
            assert.equal(
                await page
                    .getByRole('heading', { name: 'Parziale', exact: true })
                    .count(),
                0,
            );
            await page
                .getByRole('button', { name: 'Tutte', exact: true })
                .click();
            await page
                .getByRole('heading', { name: 'Parziale', exact: true })
                .waitFor();
            await page
                .getByRole('heading', { name: 'Regolata', exact: true })
                .waitFor();
        }

        await page.goto(origin + '/credits-debts', {
            waitUntil: 'domcontentloaded',
        });

        if (name === 'A') {
            await page
                .getByText(
                    'Completa il riferimento per modificare questo credito/debito.',
                    { exact: true },
                )
                .first()
                .waitFor();
        }

        if (name === 'A') {
            await page
                .getByRole('button', {
                    name: 'Nuovo credito/debito',
                    exact: true,
                })
                .click();
            const dialog = page.getByRole('dialog');
            assert.doesNotMatch(
                await dialog.innerText(),
                /Controparte|counterparty|economic.subject/i,
            );
            assert.equal(
                await dialog.getByText('Riferimento', { exact: true }).count(),
                1,
            );
            await dialog
                .getByRole('button', { name: 'Annulla', exact: true })
                .click();
        }

        await page.waitForFunction(
            () => navigator.serviceWorker.controller !== null,
            null,
            { timeout: 15000 },
        );
        assert.equal(errors.length, 0, errors.join('\n'));
        console.log(
            JSON.stringify({
                case: name,
                width,
                standalone,
                serviceWorker: true,
                errors: 0,
            }),
        );
        await context.close();
    }
} catch (error) {
    console.error('Browser failure URL:', activePage?.url());
    console.error(await activePage?.locator('body').innerText());
    await activePage?.screenshot({
        path:
            '/private/tmp/economic-browser-failure-' +
            width +
            '-' +
            standalone +
            '.png',
    });

    throw error;
} finally {
    await browser.close();
}
