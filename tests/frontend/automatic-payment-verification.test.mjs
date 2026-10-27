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

const pageScript = parse(source('resources/js/pages/dashboard/Analysis.vue'))
    .descriptor.scriptSetup.content;
const syntaxTree = ts.createSourceFile(
    'Analysis.ts',
    pageScript,
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TS,
);
const statusFunctions = syntaxTree.statements
    .filter(
        (node) =>
            ts.isFunctionDeclaration(node) &&
            ['pendingEventStatus', 'pendingEventStatusClass'].includes(
                node.name?.text,
            ),
    )
    .map((node) => node.getText(syntaxTree))
    .join('\n');
const { pendingEventStatus, pendingEventStatusClass } = new Function(
    `${ts.transpile(statusFunctions, { target: ts.ScriptTarget.ES2022 })}; return { pendingEventStatus, pendingEventStatusClass };`,
)();

test('event labels reflect payment facts without treating verification or a zero estimate as payment', () => {
    for (const [item, label] of [
        [{ is_future: true }, 'Previsto'],
        [{ status_key: 'upcoming' }, 'Previsto'],
        [
            { status_key: 'today', payment_management: { state: 'pending' } },
            'In attesa',
        ],
        [
            { status_key: 'overdue', payment_management: { state: 'pending' } },
            'Scaduto',
        ],
        [{ status_key: 'overdue', automatic_payment_missing: true }, 'Scaduto'],
        [
            {
                status_key: 'overdue',
                payment_management: { state: 'to_verify' },
            },
            'In attesa',
        ],
        [
            {
                requires_payment_confirmation: true,
                payment_management: { state: 'paid' },
            },
            'In attesa',
        ],
        [{ payment_management: { state: 'processing' } }, 'In attesa'],
        [
            {
                payment_management: {
                    state: 'paid',
                    remaining_amount: 0,
                    paid_amount: 15,
                },
            },
            'Pagato',
        ],
        [
            { payment_management: { state: 'corrected', remaining_amount: 0 } },
            'Pagato',
        ],
        [
            {
                status_key: 'overdue',
                payment_management: {
                    state: 'partial',
                    paid_amount: 10,
                    remaining_amount: 5,
                },
            },
            'Parzialmente pagato',
        ],
        [
            {
                payment_management: {
                    state: 'paid',
                    paid_amount: 10,
                    remaining_amount: 5,
                },
            },
            'Parzialmente pagato',
        ],
        [
            {
                payment_management: {
                    state: 'pending',
                    paid_amount: 0,
                    remaining_amount: 0,
                },
            },
            'In attesa',
        ],
        [{ payment_management: { state: 'review' } }, 'Da verificare'],
        [{ payment_management: { state: 'cancelled' } }, 'Annullato'],
    ]) {
        const before = JSON.stringify(item);
        assert.equal(pendingEventStatus(item), label);
        assert.ok(pendingEventStatusClass(item));
        assert.equal(JSON.stringify(item), before);
    }
});

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
    const { compile, baseParse } = await import('@vue/compiler-dom');
    const { renderToString } = await import('vue/server-renderer');
    const page = source('resources/js/pages/dashboard/Analysis.vue');
    const start = page.indexOf(
        '                    <article\n',
        page.indexOf('dashboard.pending_actions.items.length'),
    );
    const end = page.indexOf('</article>', start) + '</article>'.length;
    assert.ok(start > 0 && end > start);
    const { code } = compile(
        `<div>${page.slice(start, end).replace('<article', '<article v-for="item in dashboard.pending_actions.items"')}</div>`,
        {
            mode: 'function',
            prefixIdentifiers: true,
        },
    );
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
                CircleCheck: element('svg'),
                CalendarDays: element('svg'),
                ExternalLink: element('svg'),
                Settings2: element('svg'),
                ReferenceAvatar: element('span'),
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
                pendingEventStatus,
                pendingEventStatusClass,
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
    const article = baseParse(automatic).children[0].children.find(
        (node) => node.type === 1 && node.tag === 'article',
    );
    const rows = article.children.filter((node) => node.type === 1);
    assert.deepEqual(
        rows.map((node) => node.tag),
        ['div', 'div', 'div'],
    );
    assert.match(
        automatic.slice(rows[0].loc.start.offset, rows[0].loc.end.offset),
        /Plan[\s\S]*€ 15/,
    );
    assert.match(
        automatic.slice(rows[1].loc.start.offset, rows[1].loc.end.offset),
        /2026-10-09[\s\S]*Ricorrenza automatica/,
    );
    assert.match(
        automatic.slice(rows[2].loc.start.offset, rows[2].loc.end.offset),
        /Gestisci pagamento[\s\S]*Conferma/,
    );
    assert.match(automatic, />Conferma<\/button>/);
    assert.match(automatic, /Gestisci pagamento/);
    assert.ok(automatic.includes('grid-cols-[minmax(0,1fr)_auto]'));
    assert.match(automatic, /size-9/);
    assert.ok(automatic.includes('mt-auto flex min-w-0 flex-wrap'));
    assert.match(automatic, /rounded-xl/);
    assert.doesNotMatch(automatic, /Conferma pagamento|<input/);
    const manual = await card({
        can_confirm_manual: true,
        can_manage: true,
        payment_management: { state: 'pending' },
        recurrence_mode: 'manual',
    });
    assert.match(manual, />Conferma pagamento<\/button>/);
    assert.match(manual, /In attesa/);
    assert.doesNotMatch(
        manual,
        /Pagamento ancora non avvenuto|Pagamento registrato/,
    );
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
    assert.match(future, /Previsto/);
    assert.match(future, /text-blue-700/);
    assert.match(future, /Vai al piano/);
    const missing = await card({
        automatic_payment_missing: true,
        can_manage: true,
        payment_management: { state: 'pending' },
    });
    assert.match(missing, /Movimento automatico mancante/);
    assert.match(missing, /Gestisci pagamento/);
    assert.doesNotMatch(missing, />Conferma/);
    const partial = await card({
        status_key: 'overdue',
        can_manage: true,
        plan_url: '/plan/partial',
        payment_management: {
            state: 'partial',
            paid_amount: 10,
            remaining_amount: 5,
        },
    });
    assert.match(partial, /Parzialmente pagato/);
    assert.match(partial, /Gestisci pagamento/);
    assert.match(partial, /href="\/plan\/partial"/);
    assert.match(partial, /Vai al piano/);
    const findActionGrid = (node) => {
        if (
            node.type === 1 &&
            node.props.some(
                (prop) =>
                    prop.type === 6 &&
                    prop.name === 'class' &&
                    prop.value?.content.includes('empty:hidden'),
            )
        ) {
            return node;
        }

        return (node.children ?? []).map(findActionGrid).find(Boolean);
    };
    const primaryActions = findActionGrid(baseParse(partial)).children.filter(
        (node) => node.type === 1,
    );
    assert.deepEqual(
        primaryActions.map((node) => node.tag),
        ['button', 'a'],
    );

    for (const action of primaryActions) {
        const classes = action.props
            .find((prop) => prop.type === 6 && prop.name === 'class')
            .value.content.split(/\s+/);
        assert.ok(classes.includes('w-auto'));
        assert.ok(!classes.includes('w-full'));
        assert.ok(classes.includes('h-8'));
        assert.ok(!classes.includes('col-span-full'));
        assert.ok(!classes.includes('ml-auto'));
    }

    const withConfirmation = await card({
        can_verify_payment: true,
        transaction_uuid: 'txn',
        can_manage: true,
        plan_url: '/plan',
        payment_management: { state: 'to_verify' },
    });
    const allActions = findActionGrid(
        baseParse(withConfirmation),
    ).children.filter((node) => node.type === 1);
    assert.deepEqual(
        allActions.map((node) => node.tag),
        ['button', 'a', 'button'],
    );
    assert.ok(
        allActions[2].props.some(
            (prop) =>
                prop.type === 6 &&
                prop.name === 'class' &&
                prop.value.content.includes('h-8') &&
                !prop.value.content.includes('w-full'),
        ),
    );

    assert.ok(
        partial.indexOf('Gestisci pagamento') < partial.indexOf('Vai al piano'),
    );
    const paid = await card({
        payment_management: { state: 'paid', remaining_amount: 0 },
        can_manage: false,
    });
    assert.match(paid, /Pagato/);
    assert.doesNotMatch(paid, /Gestisci pagamento|>Conferma/);
    const overdue = await card({
        status_key: 'overdue',
        payment_management: { state: 'pending' },
    });
    assert.match(overdue, /Scaduto/);
});

test('dashboard lists share equal desktop columns and independent pagers', () => {
    const page = source('resources/js/pages/dashboard/Analysis.vue');
    assert.match(
        page,
        /grid min-w-0 grid-cols-1 items-start gap-4 lg:grid-cols-2/,
    );
    assert.match(page, /:items="dashboard.credits_debts.arrears"/);
    assert.match(page, /:items="dashboard.pending_actions.items"/);
    assert.equal((page.match(/<DashboardPager/g) ?? []).length, 2);
    assert.match(
        page,
        /class="flex min-w-0 flex-col gap-2 rounded-xl border bg-muted\/25 p-3"/,
    );
});

test('arrears desktop height follows the exact natural upcoming widget measurement', () => {
    const page = source('resources/js/pages/dashboard/Analysis.vue');
    const lifecycle = page.slice(
        page.indexOf('const upcomingWidget ='),
        page.indexOf('const manualPayment ='),
    );
    let mounted;
    let cleanup;
    let observer;
    const { upcomingWidget, upcomingWidgetHeight } = new Function(
        'ref',
        'onMounted',
        'onBeforeUnmount',
        'ResizeObserver',
        `${ts.transpile(lifecycle, { target: ts.ScriptTarget.ES2022 })}; return { upcomingWidget, upcomingWidgetHeight };`,
    )(
        (value) => ({ value }),
        (callback) => {
            mounted = callback;
        },
        (callback) => {
            cleanup = callback;
        },
        class {
            constructor(callback) {
                observer = { callback };
            }
            observe(element) {
                observer.element = element;
            }
            disconnect() {
                observer.disconnected = true;
            }
        },
    );
    let height = 347.5;
    const widget = { getBoundingClientRect: () => ({ height }) };
    upcomingWidget.value = widget;
    mounted();
    assert.equal(observer.element, widget);
    observer.callback();
    assert.equal(upcomingWidgetHeight.value, '347.5px');
    height = 516.25;
    observer.callback();
    assert.equal(upcomingWidgetHeight.value, '516.25px');
    assert.match(page, /'--dashboard-upcoming-height': upcomingWidgetHeight/);
    assert.match(page, /lg:h-\[var\(--dashboard-upcoming-height\)\]/);
    assert.match(page, /:items="dashboard.credits_debts.arrears"\s+fill/);
    cleanup();
    assert.equal(observer.disconnected, true);
});
