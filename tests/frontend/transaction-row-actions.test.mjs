import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

const source = readFileSync(
    new URL('../../resources/js/pages/transactions/Show.vue', import.meta.url),
    'utf8',
);
const script = source.slice(
    source.indexOf('const paymentConfirmationForm'),
    source.indexOf('function confirmPaymentProblem('),
);
function fixture() {
    const requests = [];
    const forms = [];
    const context = vm.createContext({
        ref: (value) => ({ value }),
        canEdit: { value: true },
        props: { year: 2026, month: 10 },
        confirmAutomaticPayment: { url: (id) => '/confirm/' + id },
        undoTransactionRefund: { url: () => '/undo' },
        useForm: () => {
            const form = {
                processing: false,
                clearErrors() {},
                post(url, options) {
                    requests.push({ url, options });
                    form.processing = true;
                },
            };
            forms.push(form);

            return form;
        },
    });
    vm.runInContext(
        ts.transpile(script, { target: ts.ScriptTarget.ES2022 }) +
            '; globalThis.selected=paymentConfirmationTransaction;globalThis.paymentForm=paymentConfirmationForm;',
        context,
    );

    return { context, requests, forms };
}

test('opening and cancelling verification never send a mutation', () => {
    const f = fixture();
    f.context.verifyAutomaticPayment({
        uuid: 'test',
        can_confirm_payment: true,
    });
    assert.equal(f.context.selected.value.uuid, 'test');
    assert.equal(f.requests.length, 0);
    f.context.closePaymentConfirmation();
    assert.equal(f.context.selected.value, null);
    f.context.confirmPaymentRegistration();
    assert.equal(f.requests.length, 0);
});
test('verification rejects unavailable actions and read-only contexts', () => {
    const f = fixture();
    f.context.verifyAutomaticPayment({
        uuid: 'test',
        can_confirm_payment: false,
    });
    assert.equal(f.context.selected.value, null);
    f.context.canEdit.value = false;
    f.context.verifyAutomaticPayment({
        uuid: 'test',
        can_confirm_payment: true,
    });
    assert.equal(f.context.selected.value, null);
});
test('explicit confirmation sends once, blocks cancellation while pending, and closes on success', () => {
    const f = fixture();
    f.context.verifyAutomaticPayment({
        uuid: 'test',
        can_confirm_payment: true,
    });
    f.context.confirmPaymentRegistration();
    f.context.confirmPaymentRegistration();
    assert.equal(f.requests.length, 1);
    assert.equal(f.requests[0].url, '/confirm/test');
    f.context.closePaymentConfirmation();
    assert.notEqual(f.context.selected.value, null);
    f.requests[0].options.onSuccess();
    assert.equal(f.context.selected.value, null);
});

const rowSource = readFileSync(
    new URL(
        '../../resources/js/components/transactions/TransactionRowActions.vue',
        import.meta.url,
    ),
    'utf8',
);
function rowActions(transaction, canEdit = true) {
    const context = vm.createContext({
        props: { transaction, canEdit, showAudit: false },
        computed: (callback) => callback(),
        t: (key) => key,
        Pencil: 'edit',
        ArrowUpRight: 'navigate',
        RotateCcw: 'undo',
        Trash2: 'delete',
        Check: 'confirm',
        TriangleAlert: 'problem',
        RefreshCcw: 'refund',
        Info: 'audit',
        UsersRound: 'shared',
    });
    vm.runInContext(
        ts.transpile(
            rowSource.slice(
                rowSource.indexOf('const actions = computed'),
                rowSource.indexOf('</script>'),
            ),
            { target: ts.ScriptTarget.ES2022 },
        ) + '; globalThis.result = actions;',
        context,
    );

    return Array.from(context.result);
}

test('canonical management replaces forbidden mutations and duplicate share controls', () => {
    const actions = rowActions({
        management_url: '/canonical',
        management_label: 'Manage',
        can_edit: true,
        shared_expense_counterparty_uuid: 'party',
    });
    assert.ok(actions.some((action) => action.name === 'management'));
    assert.ok(
        !actions.some((action) =>
            ['refund', 'undoRefund', 'delete', 'shared'].includes(action.name),
        ),
    );
    const recurring = rowActions({
        management_url: '/recurring',
        recurring_entry_show_url: '/recurring',
    });
    assert.deepEqual(
        recurring.map((action) => action.name),
        ['recurring'],
    );
});

test('read-only rows offer navigation while mutation controls are absent', () => {
    const actions = rowActions(
        {
            can_edit: true,
            can_refund: true,
            can_delete: true,
            can_confirm_payment: true,
            can_report_payment_problem: true,
            management_url: '/canonical',
        },
        false,
    );
    assert.deepEqual(
        actions.map((action) => action.name),
        ['management'],
    );
});
