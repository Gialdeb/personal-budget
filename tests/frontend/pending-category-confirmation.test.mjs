import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

function fixture(confirm) {
    const submissions = [];
    const source = readFileSync(
        'resources/js/composables/useNotifiedForm.ts',
        'utf8',
    ).replace(/^import .*;$/gm, '');
    const context = {
        exports: {},
        AbortController,
        confirmPendingCategory: confirm,
        userErrorMessage: (value) => value,
        inertiaUseForm(data) {
            let transform = (value) => value;

            return {
                processing: false,
                data: () => data,
                transform(callback) {
                    transform = callback;

                    return this;
                },
                submit(...args) {
                    submissions.push({ args, data: transform(data) });
                },
                post(url, options) {
                    this.submit('post', url, options);
                },
                cancel() {},
                reset() {},
                setError() {},
            };
        },
    };
    vm.runInNewContext(
        ts.transpileModule(source, {
            compilerOptions: {
                module: ts.ModuleKind.CommonJS,
                target: ts.ScriptTarget.ES2022,
            },
        }).outputText,
        context,
    );

    return {
        form: context.exports.useForm({
            category_uuid: 'pending-category:example',
            amount: 10,
        }),
        submissions,
    };
}
const tick = () => new Promise((resolve) => setImmediate(resolve));

test('main submit waits for explicit confirmation and prevents double submit until completion', async () => {
    let resolve;
    let calls = 0;
    const { form, submissions } = fixture(() => {
        calls++;

        return new Promise((done) => {
            resolve = done;
        });
    });
    form.post('/save');
    form.post('/save');
    assert.equal(calls, 1);
    assert.equal(submissions.length, 0);
    assert.equal(form.processing, true);
    resolve('signed-effects');
    await tick();
    assert.equal(submissions.length, 1);
    assert.equal(
        submissions[0].data.inline_budget_confirmation,
        'signed-effects',
    );
    form.post('/save');
    assert.equal(submissions.length, 1);
    submissions[0].args[2].onFinish();
    assert.equal(form.processing, false);
});

test('declining or cancelling the main form never submits a pending category', async () => {
    const declined = fixture(async () => null);
    declined.form.post('/save');
    await tick();
    assert.equal(declined.submissions.length, 0);
    let resolve;
    const cancelled = fixture(
        () =>
            new Promise((done) => {
                resolve = done;
            }),
    );
    cancelled.form.post('/save');
    cancelled.form.cancel();
    resolve('confirmed');
    await tick();
    assert.equal(cancelled.submissions.length, 0);
    assert.equal(cancelled.form.processing, false);
});

test('ordinary forms submit immediately without preparatory requests', () => {
    const { form, submissions } = fixture(() => {
        throw new Error('Unexpected preflight');
    });
    form.data = () => ({ category_uuid: 'existing-category' });
    form.post('/save');
    assert.equal(submissions.length, 1);
});
