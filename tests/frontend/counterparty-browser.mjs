// Invoked by the isolated Laravel browser test, never against the operational database.
import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(
    pathToFileURL(process.env.COUNTERPARTY_BROWSER_MODULE_PATH + '/index.mjs')
);
const url = process.env.COUNTERPARTY_BROWSER_URL;
const width = Number(process.env.COUNTERPARTY_BROWSER_WIDTH ?? 1440);
const browser = await chromium.launch({
    headless: true,
    ...(process.env.COUNTERPARTY_BROWSER_CHANNEL
        ? { channel: process.env.COUNTERPARTY_BROWSER_CHANNEL }
        : {}),
});
const context = await browser.newContext({
    viewport: { width, height: width < 600 ? 844 : 1000 },
});
const page = await context.newPage();
page.setDefaultTimeout(10000);
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
const claim = (title) =>
    page
        .locator('article')
        .filter({ has: page.getByText(title, { exact: true }) });

try {
    for (let i = 0; i < 40; i++) {
        try {
            await page.goto(url + '/login');
            break;
        } catch (error) {
            if (i === 39) {
                throw error;
            }

            await new Promise((resolve) => setTimeout(resolve, 100));
        }
    }

    const essentialCookies = page.getByRole('button', {
        name: /Solo necessari|Solo essenziali|Rifiuta/,
    });

    if (await essentialCookies.count()) {
        await essentialCookies.first().click();
    }

    await page.locator('#email').fill('counterparty-browser@example.test');
    await page.locator('#password').fill('password');
    await page.locator('form button[type=submit]').click();
    await page.waitForURL((u) => !u.pathname.includes('/login'));
    await page.waitForLoadState('networkidle');
    await page.goto(url + '/credits-debts');
    await page.getByText('Credito iniziale', { exact: true }).first().click();

    if (width < 600) {
        await page
            .getByRole('dialog')
            .getByRole('button', { name: 'Registra acconto', exact: true })
            .click();
    } else {
        await page
            .getByRole('link', {
                name: 'Emanuela · EUR · Saldo e pagamento comune',
                exact: true,
            })
            .click();
    }

    await page
        .getByText('Emanuela ti deve 250,00 EUR', { exact: true })
        .waitFor();
    await page.locator('#common-amount').fill('100,00');
    await page.getByText('Saldo dopo: 150,00 EUR', { exact: true }).waitFor();
    await page
        .getByRole('button', { name: 'Conferma pagamento', exact: true })
        .click();
    await page
        .getByText('Emanuela ti deve 150,00 EUR', { exact: true })
        .waitFor();
    await claim('Credito iniziale')
        .getByText('Residuo: 100,00 EUR', { exact: true })
        .waitFor();
    await claim('Cena')
        .getByText('Residuo: 50,00 EUR', { exact: true })
        .waitFor();
    await page
        .getByRole('button', { name: 'Gestisci allocazione', exact: true })
        .last()
        .click();
    await page.getByLabel('Credito iniziale', { exact: true }).fill('50.00');
    await page.getByLabel('Cena', { exact: true }).fill('50.00');
    await page
        .getByRole('button', { name: 'Conferma allocazione', exact: true })
        .click();
    await page
        .getByRole('heading', { name: 'Registra pagamento', exact: true })
        .waitFor();
    await page
        .getByText('Emanuela ti deve 150,00 EUR', { exact: true })
        .waitFor();
    await claim('Credito iniziale')
        .getByText('Residuo: 150,00 EUR', { exact: true })
        .waitFor();
    await claim('Cena')
        .getByText('Residuo: 0,00 EUR', { exact: true })
        .waitFor();
    await page.goto(url + '/shared-expenses');
    await page
        .getByRole('link', {
            name: 'Emanuela · EUR · Saldo e pagamento comune',
            exact: true,
        })
        .click();
    assert.equal(await page.locator('#common-scope').inputValue(), 'shared');
    assert.ok(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
        'No horizontal overflow',
    );
    await page
        .getByRole('button', {
            name: 'Annullamento amministrativo',
            exact: true,
        })
        .click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Conferma', exact: true })
        .click();
    await page
        .getByText('Emanuela ti deve 250,00 EUR', { exact: true })
        .waitFor();
    await claim('Credito iniziale')
        .getByText('Residuo: 200,00 EUR', { exact: true })
        .waitFor();
    await claim('Cena')
        .getByText('Residuo: 50,00 EUR', { exact: true })
        .waitFor();
    await page
        .getByText('Annullato · documento originale preservato', {
            exact: true,
        })
        .waitFor();
    await page.goto(
        url +
            '/counterparties/' +
            process.env.COUNTERPARTY_BROWSER_COMPENSATION,
    );
    await page
        .getByText('Emanuela compensazione ti deve 150,00 EUR', { exact: true })
        .waitFor();
    await page
        .getByText('Compensazione senza movimento bancario', { exact: true })
        .click();
    const positive = await page
        .locator('#comp-positive option')
        .last()
        .getAttribute('value');
    const negative = await page
        .locator('#comp-negative option')
        .last()
        .getAttribute('value');
    await page.locator('#comp-positive').selectOption(positive);
    await page.locator('#comp-negative').selectOption(negative);
    await page.locator('#comp-amount').fill('50,00');
    await page
        .getByRole('button', { name: 'Conferma compensazione', exact: true })
        .click();
    await claim('Credito da compensare')
        .getByText('Residuo: 150,00 EUR', { exact: true })
        .waitFor();
    await claim('Debito condiviso')
        .getByText('Residuo: 0,00 EUR', { exact: true })
        .waitFor();
    await page
        .getByText('Emanuela compensazione ti deve 150,00 EUR', { exact: true })
        .waitFor();
    assert.equal(errors.length, 0, errors.join('\n'));
    console.log(
        `${width}px: 250→150 receipt, custom allocation changes residuals only, cancellation→250 and preserved history, 200/-50 compensation→150 and residuals150/0.`,
    );
} finally {
    await browser.close();
}
