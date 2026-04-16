import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const routesSource = readFileSync(
    new URL('../../routes/web.php', import.meta.url),
    'utf8',
);
const loginSource = readFileSync(
    new URL('../../resources/js/pages/auth/Login.vue', import.meta.url),
    'utf8',
);
const registerSource = readFileSync(
    new URL('../../resources/js/pages/auth/Register.vue', import.meta.url),
    'utf8',
);
const termsSource = readFileSync(
    new URL(
        '../../resources/js/pages/legal/TermsOfService.vue',
        import.meta.url,
    ),
    'utf8',
);
const privacySource = readFileSync(
    new URL('../../resources/js/pages/legal/Privacy.vue', import.meta.url),
    'utf8',
);
const legalMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/legal.ts', import.meta.url),
    'utf8',
);
const legalContentSource = readFileSync(
    new URL('../../resources/js/i18n/legal-content.ts', import.meta.url),
    'utf8',
);

test('public legal routes are registered', () => {
    assert.match(
        routesSource,
        /Route::inertia\('\/terms-of-service', 'legal\/TermsOfService'\)/,
    );
    assert.match(
        routesSource,
        /Route::inertia\('\/privacy', 'legal\/Privacy'\)/,
    );
});

test('login and register include legal consent links', () => {
    assert.match(loginSource, /href="\/terms-of-service"/);
    assert.match(loginSource, /href="\/privacy"/);
    assert.match(registerSource, /href="\/terms-of-service"/);
    assert.match(registerSource, /href="\/privacy"/);
    assert.match(loginSource, /target="_blank"/);
    assert.match(registerSource, /target="_blank"/);
    assert.match(loginSource, /font-semibold text-\[#d55239]/);
    assert.match(registerSource, /font-semibold text-\[#d55239]/);
    assert.match(loginSource, /auth\.login\.legal\.prefix/);
    assert.match(registerSource, /auth\.register\.legal\.prefix/);
});

test('legal pages render multilingual legal sections', () => {
    assert.match(termsSource, /legalContent\.it\.terms/);
    assert.match(privacySource, /legalContent\.it\.privacy/);
    assert.match(legalContentSource, /Soamco Budget Terms of Service/);
    assert.match(legalContentSource, /Soamco Budget Privacy Notice/);
    assert.match(legalMessagesSource, /30 marzo 2026/);
    assert.match(legalMessagesSource, /March 30, 2026/);
});

test('legal pages keep only a professional support contact in the final box', () => {
    assert.doesNotMatch(termsSource, /content\.sourceNote/);
    assert.doesNotMatch(privacySource, /content\.sourceNote/);
    assert.match(termsSource, /t\('legal\.common\.contact'\)/);
    assert.match(privacySource, /t\('legal\.common\.contact'\)/);
    assert.match(termsSource, /border-\[#ece4dc] bg-white p-6 sm:p-8/);
    assert.match(privacySource, /border-\[#ece4dc] bg-white p-6 sm:p-8/);
});
