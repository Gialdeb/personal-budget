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
    assert.match(page, /v-if="item\.requires_payment_confirmation"/);
    assert.match(page, /v-else-if="item\.requires_amount_confirmation"/);
    assert.match(page, /Pagamento da confermare/);
    assert.match(page, /verifyPayment\(item, true\)/);
    assert.match(page, /verifyPayment\(item\)/);
    assert.match(page, /item\.plan_title/);
    assert.match(page, /:href="item\.action_url"/);
    assert.match(page, /AutomaticPaymentVerificationController/);
});

test('desktop and mobile transaction actions allow reporting problems beyond the confirmation window', () => {
    const page = source('resources/js/pages/transactions/Show.vue');
    assert.equal(
        (
            page.match(
                /v-if="\s*transaction\.can_report_payment_problem\s*"/g,
            ) ?? []
        ).length,
        2,
    );
    assert.equal((page.match(/Segnala problema\s+pagamento/g) ?? []).length, 2);
    assert.match(
        page,
        /paymentProblemForm\.post\(paymentProblem\.url\(transaction\.uuid\)/,
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
