import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const loginSource = readFileSync(
    new URL('../../resources/js/pages/auth/Login.vue', import.meta.url),
    'utf8',
);
const registerSource = readFileSync(
    new URL('../../resources/js/pages/auth/Register.vue', import.meta.url),
    'utf8',
);
const forgotPasswordSource = readFileSync(
    new URL(
        '../../resources/js/pages/auth/ForgotPassword.vue',
        import.meta.url,
    ),
    'utf8',
);
const resetPasswordSource = readFileSync(
    new URL('../../resources/js/pages/auth/ResetPassword.vue', import.meta.url),
    'utf8',
);
const layoutSource = readFileSync(
    new URL(
        '../../resources/js/layouts/auth/AuthShowcaseLayout.vue',
        import.meta.url,
    ),
    'utf8',
);
const authMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/auth.ts', import.meta.url),
    'utf8',
);

test('login, register, forgot password and reset password use the dedicated auth showcase layout', () => {
    assert.match(loginSource, /AuthShowcaseLayout\.vue/);
    assert.match(registerSource, /AuthShowcaseLayout\.vue/);
    assert.match(forgotPasswordSource, /AuthShowcaseLayout\.vue/);
    assert.match(resetPasswordSource, /AuthShowcaseLayout\.vue/);
    assert.match(loginSource, /store\.form\(\)/);
    assert.match(registerSource, /store\.form\(\)/);
    assert.match(forgotPasswordSource, /email\.form\(\)/);
    assert.match(resetPasswordSource, /update\.form\(\)/);
    assert.match(forgotPasswordSource, /mode="forgot-password"/);
    assert.match(resetPasswordSource, /mode="reset-password"/);
});

test('auth showcase layout gives more space to the form and keeps the preview desktop only', () => {
    assert.match(layoutSource, /data-test="auth-showcase-panel"/);
    assert.ok(
        layoutSource.includes(
            'lg:grid-cols-[minmax(0,42rem)_minmax(24rem,36rem)]',
        ),
    );
    assert.doesNotMatch(layoutSource, /data-test="auth-mobile-preview"/);
    assert.match(layoutSource, /showcaseItems/);
});

test('auth showcase copy defines fake transaction preview content', () => {
    assert.match(authMessagesSource, /Stipendio marzo/);
    assert.match(authMessagesSource, /Affitto casa/);
    assert.match(authMessagesSource, /Weekly groceries/);
    assert.match(authMessagesSource, /Product preview/);
    assert.match(authMessagesSource, /Recupero accesso/);
    assert.match(layoutSource, /data-test="auth-recovery-visual"/);
});
