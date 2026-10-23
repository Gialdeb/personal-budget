import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import {
    parse,
    compileScript,
    compileTemplate,
    registerTS,
} from '@vue/compiler-sfc';
import ts from 'typescript';

registerTS(() => ts);

const source = (path) =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('payment verification remains distinct from amount confirmation in dashboard rendering', () => {
    const page = source('resources/js/pages/dashboard/Analysis.vue');
    assert.match(
        page,
        /item\.can_verify_payment\s*&&\s*item\.transaction_uuid/,
    );
    assert.match(page, /v-else-if="item\.can_confirm_manual"/);
    assert.match(page, /v-if="!item\.is_future"/);
    assert.doesNotMatch(
        page,
        /amountConfirmation|confirmAmount|editConfirmation|MobileAmountInput/,
    );
    assert.match(page, /managePayment\(item\)/);
    assert.doesNotMatch(page, /paymentProblem|window\.confirm/);
    assert.match(page, /verifyPayment\(item\)/);
    assert.match(page, /item\.plan_url/);
    assert.match(page, /:href="item\.action_url"/);
    assert.match(page, /AutomaticPaymentVerificationController/);
});

test('desktop and mobile transaction actions allow reporting problems beyond the confirmation window', () => {
    const page = source('resources/js/pages/transactions/Show.vue');
    const actions = source(
        'resources/js/components/transactions/TransactionRowActions.vue',
    );
    assert.match(page, /<TransactionRowActions/);
    assert.match(actions, /show: editable && row\.can_report_payment_problem/);
    assert.match(actions, /transactions\.sheet\.paymentProblem\.trigger/);
    assert.match(
        page,
        /paymentProblemForm\.post\(\s*paymentProblem\.url\(paymentProblemTransaction\.value\.uuid\)/,
    );
    const sheet = source(
        'app/Services/Dashboard/MonthlyTransactionSheetService.php',
    );
    const capability = sheet
        .split("'can_report_payment_problem' =>")[1]
        .split("'can_undo_refund'")[0];
    assert.doesNotMatch(
        capability,
        /payment_verified_at|payment_verification_due_at/,
    );
});

for (const path of [
    'resources/js/pages/dashboard/Analysis.vue',
    'resources/js/pages/transactions/Show.vue',
    'resources/js/pages/transactions/recurring/Show.vue',
]) {
    test(`payment lifecycle page compiles: ${path}`, () => {
        const filename = fileURLToPath(
            new URL(`../../${path}`, import.meta.url),
        );
        const { descriptor, errors } = parse(source(path), { filename });
        assert.deepEqual(errors, []);
        const script = compileScript(descriptor, {
            id: path,
            fs: {
                fileExists: existsSync,
                readFile: (file) => readFileSync(file, 'utf8'),
            },
        });
        const template = compileTemplate({
            source: descriptor.template.content,
            filename: path,
            id: path,
            compilerOptions: { bindingMetadata: script.bindings },
        });
        assert.deepEqual(template.errors, []);
    });
}

test('dashboard cards render only actions appropriate to due date and payment mode', async () => {
    const Vue = await import('vue');
    const { compile } = await import('@vue/compiler-dom');
    const { renderToString } = await import('vue/server-renderer');
    const page = source('resources/js/pages/dashboard/Analysis.vue');
    const start = page.indexOf(
        '                    <article\n',
        page.indexOf('dashboard.pending_actions.items.length'),
    );
    const end = page.indexOf('</article>', start) + '</article>'.length;
    assert.ok(start > 0 && end > start);
    const { code } = compile(`<div>${page.slice(start, end)}</div>`, {
        mode: 'function',
    });
    const render = new Function('Vue', code)(Vue);
    const element = (tag) => ({
        inheritAttrs: false,
        setup:
            (_, { slots, attrs }) =>
            () =>
                Vue.h(tag, attrs, slots.default?.()),
    });
    async function card(item) {
        const app = Vue.createSSRApp({
            components: {
                Link: element('a'),
                Button: element('button'),
                Badge: element('span'),
                SensitiveValue: {
                    props: ['value'],
                    setup: (props) => () => Vue.h('span', props.value),
                },
                WalletCards: element('svg'),
                Clock3: element('svg'),
                CircleAlert: element('svg'),
            },
            setup: () => ({
                dashboard: {
                    pending_actions: {
                        items: [
                            {
                                id: 'one',
                                title: 'Plan',
                                date: '2026-10-09',
                                amount_raw: 15,
                                action_url: '/plan',
                                ...item,
                            },
                        ],
                    },
                },
                ui: (value) => value,
                t: (value) => value,
                dateLabel: (value) => value,
                money: (value) => `€ ${value}`,
                formatCurrency: (value) => `€ ${value}`,
                currency: 'EUR',
                paymentStateLabel: (value) =>
                    value === 'to_verify' ? 'Pagamento da confermare' : value,
                paymentVerification: { processing: false, errors: {} },
                manualRegistration: { processing: false },
                verifyPayment() {},
                askManualPayment() {},
                managePayment() {},
            }),
            render,
        });

        return renderToString(app);
    }
    const automatic = await card({
        can_verify_payment: true,
        transaction_uuid: 'txn',
        can_manage: true,
        payment_management: { state: 'to_verify' },
        recurrence_mode: 'automatic',
    });
    assert.match(automatic, />Conferma<\/button>/);
    assert.match(automatic, /Gestisci pagamento/);
    assert.doesNotMatch(automatic, /Conferma pagamento|<input/);
    const manual = await card({
        can_confirm_manual: true,
        can_manage: true,
        payment_management: { state: 'pending' },
        recurrence_mode: 'manual',
    });
    assert.match(manual, />Conferma pagamento<\/button>/);
    assert.match(manual, /Pagamento ancora non avvenuto/);
    assert.match(manual, /border-primary\/20/);
    assert.doesNotMatch(manual, /<input/);
    const future = await card({
        is_future: true,
        can_verify_payment: true,
        transaction_uuid: 'txn',
        can_confirm_manual: true,
        can_manage: true,
        payment_management: { state: 'pending' },
        plan_url: '/plan',
        recurrence_mode: 'automatic',
    });
    assert.doesNotMatch(
        future,
        /<button|Gestisci pagamento|Pagamento ancora non avvenuto/,
    );
    assert.match(future, /Ricorrenza automatica/);
    assert.match(future, /Previsione/);
    assert.match(future, /Vai al piano/);
    const missing = await card({
        automatic_payment_missing: true,
        can_manage: true,
        payment_management: { state: 'pending' },
    });
    assert.match(missing, /Movimento automatico mancante/);
    assert.match(missing, /Gestisci pagamento/);
    assert.doesNotMatch(missing, />Conferma/);
});
