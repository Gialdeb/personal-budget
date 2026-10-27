import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';
import { compile } from '@vue/compiler-dom';
import {
    compileScript,
    compileTemplate,
    parse,
    registerTS,
} from '@vue/compiler-sfc';
import ts from 'typescript';
import * as Vue from 'vue';
import { renderToString } from 'vue/server-renderer';

import { recurringPlanHeading } from '../../resources/js/lib/transaction-presentation.js';

registerTS(() => ts);
const filename = new URL(
    '../../resources/js/pages/transactions/Show.vue',
    import.meta.url,
).pathname;
const source = readFileSync(filename, 'utf8');
const { descriptor } = parse(source, { filename });
const script = ts.createSourceFile(
    'Show.ts',
    descriptor.scriptSetup.content,
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TS,
);
function declaration(name) {
    return script.statements
        .find((statement) =>
            ts.isFunctionDeclaration(statement)
                ? statement.name?.text === name
                : ts.isVariableStatement(statement) &&
                  statement.declarationList.declarations.some(
                      (item) => item.name.getText(script) === name,
                  ),
        )
        .getText(script);
}

function planFixture(items = []) {
    const scope = Vue.effectScope();
    const sheet = Vue.ref({ deleted_recurring_plans: items });
    const selectedAccount = Vue.ref('all');
    const locale = Vue.ref('it');
    const names = [
        'deletedRecurringPlanTotal',
        'deletedPlanSearch',
        'deletedPlanPage',
        'deletedPlanPageSize',
        'deletedRecurringPlans',
        'deletedPlanPageCount',
        'paginatedDeletedPlans',
    ];
    const watchers = script.statements
        .filter(
            (statement) =>
                ts.isExpressionStatement(statement) &&
                ts.isCallExpression(statement.expression) &&
                statement.expression.expression.getText(script) === 'watch' &&
                (statement.expression.arguments[0].getText(script) ===
                    '[deletedPlanSearch, selectedAccount]' ||
                    statement.expression.arguments[0].getText(script) ===
                        'deletedPlanPageCount'),
        )
        .map((statement) => statement.getText(script));
    assert.equal(watchers.length, 2);
    const code = ts.transpile(
        [...names.map(declaration), ...watchers].join('\n'),
        { target: ts.ScriptTarget.ES2022 },
    );
    const state = scope.run(() =>
        new Function(
            'computed',
            'ref',
            'watch',
            'sheet',
            'selectedAccount',
            'locale',
            `${code}; return { ${names.join(', ')} };`,
        )(Vue.computed, Vue.ref, Vue.watch, sheet, selectedAccount, locale),
    );

    return { ...state, sheet, selectedAccount, scope };
}

test('plan search is independent from transaction search and preserves the total count', async () => {
    const state = planFixture([
        {
            uuid: 'one',
            account_uuid: 'a',
            title: 'Fibra',
            reference: 'Provider',
        },
        { uuid: 'two', account_uuid: 'b', title: 'Palestra', reference: null },
    ]);
    assert.equal(state.deletedRecurringPlanTotal.value, 2);
    state.deletedPlanSearch.value = ' PROVIDER ';
    await Vue.nextTick();
    assert.deepEqual(
        state.deletedRecurringPlans.value.map((plan) => plan.uuid),
        ['one'],
    );
    assert.equal(state.deletedRecurringPlanTotal.value, 2);
    state.selectedAccount.value = 'b';
    await Vue.nextTick();
    assert.equal(state.deletedRecurringPlans.value.length, 0);
    state.deletedPlanSearch.value = 'palestra';
    await Vue.nextTick();
    assert.deepEqual(
        state.deletedRecurringPlans.value.map((plan) => plan.uuid),
        ['two'],
    );
    assert.doesNotMatch(
        declaration('deletedRecurringPlans'),
        /normalizedTransactionSearch|transactionSearch/,
    );
    state.scope.stop();
});

test('plan pagination keeps all results reachable and resets on search and account changes', async () => {
    const state = planFixture(
        Array.from({ length: 25 }, (_, index) => ({
            uuid: String(index),
            account_uuid: index % 2 ? 'b' : 'a',
            title: `Plan ${index}`,
            reference: `Provider ${index}`,
        })),
    );
    assert.equal(state.deletedPlanPageCount.value, 3);
    const reached = [];

    for (const page of [1, 2, 3]) {
        state.deletedPlanPage.value = page;
        reached.push(
            ...state.paginatedDeletedPlans.value.map((plan) => plan.uuid),
        );
        assert.ok(state.paginatedDeletedPlans.value.length <= 10);
    }

    assert.deepEqual(
        reached,
        Array.from({ length: 25 }, (_, index) => String(index)),
    );
    state.deletedPlanSearch.value = 'Provider 12';
    await Vue.nextTick();
    assert.equal(state.deletedPlanPage.value, 1);
    assert.equal(state.deletedPlanPageCount.value, 1);
    assert.deepEqual(
        state.paginatedDeletedPlans.value.map((plan) => plan.uuid),
        ['12'],
    );
    state.deletedPlanSearch.value = '';
    await Vue.nextTick();
    state.deletedPlanPage.value = 3;
    state.selectedAccount.value = 'b';
    await Vue.nextTick();
    assert.equal(state.deletedPlanPage.value, 1);
    assert.equal(state.deletedRecurringPlanTotal.value, 25);
    state.scope.stop();
});

test('removing restored plans clamps the last page and empty searches remain usable', async () => {
    const state = planFixture(
        Array.from({ length: 21 }, (_, index) => ({
            uuid: String(index),
            account_uuid: 'a',
            title: `Plan ${index}`,
            reference: null,
        })),
    );
    state.deletedPlanPage.value = 3;
    state.sheet.value.deleted_recurring_plans.pop();
    await Vue.nextTick();
    assert.equal(state.deletedPlanPage.value, 2);
    assert.equal(state.paginatedDeletedPlans.value.length, 10);
    state.deletedPlanSearch.value = 'no match';
    await Vue.nextTick();
    assert.equal(state.deletedPlanPage.value, 1);
    assert.equal(state.paginatedDeletedPlans.value.length, 0);
    assert.equal(state.deletedRecurringPlanTotal.value, 20);
    state.sheet.value = {};
    await Vue.nextTick();
    assert.equal(state.deletedRecurringPlanTotal.value, 0);
    assert.equal(state.deletedPlanPageCount.value, 1);
    state.scope.stop();
});

test('restore uses the dedicated route once and releases its busy state', () => {
    const calls = [];
    const code = ts.transpile(
        [declaration('restoringPlan'), declaration('restorePlan')].join('\n'),
        { target: ts.ScriptTarget.ES2022 },
    );
    const { restorePlan, restoringPlan } = new Function(
        'ref',
        'router',
        'restoreRecurringPlan',
        `${code}; return { restorePlan, restoringPlan };`,
    )(
        Vue.ref,
        { patch: (...args) => calls.push(args) },
        { url: (uuid) => `/recurring-entries/${uuid}/restore` },
    );
    restorePlan('one');
    restorePlan('two');
    assert.equal(calls.length, 1);
    assert.equal(calls[0][0], '/recurring-entries/one/restore');
    assert.deepEqual(calls[0][1], {});
    assert.equal(calls[0][2].preserveScroll, true);
    assert.equal(restoringPlan.value, 'one');
    calls[0][2].onFinish();
    assert.equal(restoringPlan.value, null);
});

test('the collapsed trash box follows shared advances and obeys the existing deleted filter', async () => {
    const marker = source.indexOf('data-testid="trash-recurring-plans"');
    const start = source.lastIndexOf('<section', marker);
    const end = source.indexOf('</section>', marker) + '</section>'.length;
    assert.ok(start > source.indexOf('<SharedLedgerMovements'));
    assert.equal(
        (source.match(/data-testid="trash-recurring-plans"/g) ?? []).length,
        1,
    );
    const snippet = source.slice(start, end);
    assert.doesNotMatch(snippet.match(/<details[^>]*>/)[0], /\bopen(?:=|\s|>)/);
    const { code } = compile(`<main>${snippet}</main>`, {
        mode: 'function',
        prefixIdentifiers: true,
    });
    const render = new Function('Vue', code)(Vue);
    const element = (tag) => ({
        setup:
            (_, { attrs, slots }) =>
            () =>
                Vue.h(tag, attrs, slots.default?.()),
    });
    const html = async (showDeleted, canRestore, total = 25, pages = 3) =>
        renderToString(
            Vue.createSSRApp({
                components: {
                    Button: element('button'),
                    Badge: element('span'),
                    Input: element('input'),
                    RotateCcw: element('svg'),
                    ChevronDown: element('svg'),
                    ChevronLeft: element('svg'),
                    ChevronRight: element('svg'),
                },
                setup: () => ({
                    recurringPlanHeading,
                    showDeleted,
                    deletedRecurringPlanTotal: total,
                    deletedPlanSearch: '',
                    deletedPlanPage: 1,
                    deletedPlanPageCount: pages,
                    deletedRecurringPlans: total ? [{}] : [],
                    paginatedDeletedPlans: total
                        ? [
                              {
                                  uuid: 'one',
                                  title: 'Fibra',
                                  reference: 'Provider',
                                  deleted_at: '2026-10-26T10:00:00Z',
                                  can_restore: canRestore,
                              },
                          ]
                        : [],
                    restoringPlan: null,
                    restorePlan() {},
                    ui: (value) => value,
                    formatDateShort: (value) => value,
                }),
                render,
            }),
        );
    assert.doesNotMatch(
        await html(false, true),
        /Piani nel cestino|Provider|<button/,
    );
    const visible = await html(true, true);
    assert.match(
        visible,
        /Piani nel cestino[\s\S]*Totale piani nel cestino[\s\S]*25/,
    );
    assert.match(visible, /type="search"/);
    assert.match(visible, /Ripristina piano/);
    assert.match(visible, /Paginazione piani/);
    assert.doesNotMatch(await html(true, false), /Ripristina piano/);
    assert.doesNotMatch(await html(true, true, 1, 1), /<nav/);
    const empty = await html(true, true, 0, 1);
    assert.match(empty, /Piani nel cestino[\s\S]*Nessun piano trovato/);
    assert.doesNotMatch(empty, /Ripristina piano|<nav/);
});

test('the transaction workspace compiles after extending the existing trash', () => {
    const compiled = compileScript(descriptor, {
        id: 'plan-trash',
        fs: {
            fileExists: existsSync,
            readFile: (file) => readFileSync(file, 'utf8'),
        },
    });
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: 'plan-trash',
        compilerOptions: { bindingMetadata: compiled.bindings },
    });
    assert.deepEqual(template.errors, []);
});
