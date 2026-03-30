import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const composableSource = readFileSync(
    new URL(
        '../../resources/js/composables/useCookieConsent.ts',
        import.meta.url,
    ),
    'utf8',
);
const consentSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicCookieConsent.vue',
        import.meta.url,
    ),
    'utf8',
);
const footerSource = readFileSync(
    new URL(
        '../../resources/js/components/public/PublicSiteFooter.vue',
        import.meta.url,
    ),
    'utf8',
);
const welcomeSource = readFileSync(
    new URL('../../resources/js/pages/Welcome.vue', import.meta.url),
    'utf8',
);
const legalLayoutSource = readFileSync(
    new URL(
        '../../resources/js/layouts/public/PublicLegalLayout.vue',
        import.meta.url,
    ),
    'utf8',
);
const authShowcaseSource = readFileSync(
    new URL(
        '../../resources/js/layouts/auth/AuthShowcaseLayout.vue',
        import.meta.url,
    ),
    'utf8',
);
const authSimpleSource = readFileSync(
    new URL(
        '../../resources/js/layouts/auth/AuthSimpleLayout.vue',
        import.meta.url,
    ),
    'utf8',
);
const appMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/app.ts', import.meta.url),
    'utf8',
);

test('cookie consent persists preferences in localStorage and cookie', () => {
    assert.match(composableSource, /soamco-budget-cookie-consent/);
    assert.match(composableSource, /soamco_budget_cookie_consent/);
    assert.match(composableSource, /window\.localStorage\.setItem/);
    assert.match(composableSource, /document\.cookie =/);
    assert.match(composableSource, /window\.dispatchEvent\(new CustomEvent/);
});

test('cookie consent UI exposes banner, customization, and categories', () => {
    assert.match(consentSource, /data-test="cookie-consent-banner"/);
    assert.match(consentSource, /data-test="cookie-consent-preferences"/);
    assert.match(consentSource, /app\.cookieConsent\.actions\.accept/);
    assert.match(consentSource, /app\.cookieConsent\.actions\.reject/);
    assert.match(consentSource, /app\.cookieConsent\.actions\.customize/);
    assert.match(consentSource, /categories\.\$\{category}\.title/);
    assert.match(consentSource, /sm:w-full sm:max-w-xl sm:grid-cols-3/);
    assert.match(consentSource, /min-h-12 w-full items-center justify-center/);
    assert.match(consentSource, /text-center text-sm font-semibold/);
});

test('public footer can reopen cookie preferences', () => {
    assert.match(footerSource, /openCookieConsentPreferences/);
    assert.match(footerSource, /auth\.welcome\.footer\.legal\.cookies/);
});

test('cookie consent is mounted on public and public-auth layouts', () => {
    assert.match(welcomeSource, /PublicCookieConsent/);
    assert.match(legalLayoutSource, /PublicCookieConsent/);
    assert.match(authShowcaseSource, /PublicCookieConsent/);
    assert.match(authSimpleSource, /PublicCookieConsent/);
});

test('cookie consent copy is available in both locales', () => {
    assert.match(appMessagesSource, /Usiamo cookie essenziali/);
    assert.match(appMessagesSource, /We use essential cookies/);
    assert.match(appMessagesSource, /Cookie preferences/);
});
