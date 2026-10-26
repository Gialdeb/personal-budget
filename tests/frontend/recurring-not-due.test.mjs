import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';
import {
    compileScript,
    compileTemplate,
    parse,
    registerTS,
} from '@vue/compiler-sfc';
import ts from 'typescript';

registerTS(() => ts);
const filename = 'resources/js/pages/transactions/recurring/Show.vue';
const source = readFileSync(filename, 'utf8');
const { descriptor } = parse(source, { filename });
const ast = ts.createSourceFile(
    'Show.ts',
    descriptor.scriptSetup.content,
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TS,
);
function declaration(name) {
    return ast.statements
        .find((statement) =>
            ts.isFunctionDeclaration(statement)
                ? statement.name?.text === name
                : ts.isVariableStatement(statement) &&
                  statement.declarationList.declarations.some(
                      (item) => item.name.getText(ast) === name,
                  ),
        )
        .getText(ast);
}
function execute(names, args, values, returned) {
    return new Function(
        ...args,
        `${ts.transpile(names.map(declaration).join('\n'), { target: ts.ScriptTarget.ES2022 })}; return { ${returned.join(', ')} };`,
    )(...values);
}
function heading(entry, compound) {
    const result = execute(
        ['pageTitle', 'headingDescription'],
        ['props', 'computed'],
        [
            { recurringEntry: { entry }, compound },
            (callback) => ({
                get value() {
                    return callback();
                },
            }),
        ],
        ['pageTitle', 'headingDescription'],
    );

    return [result.pageTitle.value, result.headingDescription.value];
}

test('detail heading uses reference name and description without duplicated labels', () => {
    assert.deepEqual(
        heading({
            title: 'Abbonamento fibra',
            description: 'Internet casa',
            tracked_item: { name: 'Provider' },
        }),
        ['Provider', 'Internet casa'],
    );
    assert.deepEqual(
        heading({
            title: 'Provider',
            description: ' provider ',
            tracked_item: { name: 'Provider' },
        }),
        ['Provider', null],
    );
    assert.deepEqual(
        heading({
            title: 'Fibra',
            description: null,
            tracked_item: { name: 'Provider' },
        }),
        ['Provider', 'Fibra'],
    );
    assert.deepEqual(
        heading({ title: 'Fibra', description: null, tracked_item: null }),
        ['Fibra', null],
    );
    assert.deepEqual(
        heading({ title: 'Componente', description: null }, { name: 'Piano' }),
        ['Piano', 'Componente'],
    );
    assert.match(
        source,
        /<ReferenceAvatar\s+:name="pageTitle"\s+:logo-url="entry.tracked_item\?\.logo_url"/,
    );
    assert.match(source, /v-if="headingDescription"/);
});

test('not due requires confirmation before posting through the existing route and restoration is separate', () => {
    const requests = [];
    const form = {
        not_due: true,
        confirmed: true,
        reason: '',
        reset() {
            this.reason = '';
        },
        clearErrors() {},
        patch(url, options) {
            requests.push({
                url,
                data: {
                    not_due: this.not_due,
                    confirmed: this.confirmed,
                    reason: this.reason,
                },
                options,
            });
        },
    };
    const functions = execute(
        [
            'notDueOpen',
            'notDueOccurrence',
            'notDueForm',
            'toggleSkippedOccurrence',
            'markNotDue',
        ],
        [
            'ref',
            'useForm',
            'router',
            'restoreOccurrence',
            'skipOccurrence',
            'occurrenceOwner',
        ],
        [
            (value) => ({ value }),
            (data) => Object.assign(form, data),
            { patch: (url) => requests.push({ url }) },
            { url: (params) => `/restore/${params.join('/')}` },
            { url: (params) => `/skip/${params.join('/')}` },
            () => 'plan',
        ],
        ['notDueOpen', 'notDueForm', 'toggleSkippedOccurrence', 'markNotDue'],
    );
    functions.toggleSkippedOccurrence({ uuid: 'due', can_mark_not_due: false });
    assert.equal(functions.notDueOpen.value, false);
    functions.toggleSkippedOccurrence({ uuid: 'due', can_mark_not_due: true });
    assert.equal(functions.notDueOpen.value, true);
    assert.equal(requests.length, 0);
    form.reason = 'Servizio non erogato';
    functions.markNotDue();
    assert.equal(requests[0].url, '/skip/plan/due');
    assert.deepEqual(requests[0].data, {
        not_due: true,
        confirmed: true,
        reason: 'Servizio non erogato',
    });
    requests[0].options.onSuccess();
    assert.equal(functions.notDueOpen.value, false);
    functions.toggleSkippedOccurrence({ uuid: 'due', can_restore: true });
    assert.equal(requests[1].url, '/restore/plan/due');
    assert.match(source, /v-model:open="notDueOpen"/);
    assert.match(source, /@submit.prevent="markNotDue"/);
    assert.match(source, /v-model="notDueForm.reason"/);
    assert.equal(
        (source.match(/occurrence.can_mark_not_due\s*\|\|/g) ?? []).length,
        2,
    );
});

test('not due has a distinctive historical label ahead of settlement labels', () => {
    const { occurrenceState } = execute(
        ['occurrenceState'],
        ['ui', 't'],
        [(value) => value, (value) => value],
        ['occurrenceState'],
    );
    assert.equal(
        occurrenceState({
            not_due: { previous_status: 'pending', reason: null },
            status: 'skipped',
            settlement_status: 'settled',
        }).label,
        'Non dovuta',
    );
    assert.notEqual(occurrenceState({ status: 'skipped' }).label, 'Non dovuta');
});

test('recurring detail compiles with the new confirmation and reference heading', () => {
    const script = compileScript(descriptor, {
        id: 'not-due',
        fs: {
            fileExists: existsSync,
            readFile: (file) => readFileSync(file, 'utf8'),
        },
    });
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: 'not-due',
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);
});
