import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import test from 'node:test';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';
import ts from 'typescript';
import {
    getMoneySeparators,
    normalizeMoneyValue,
    toStandardMoneyString,
} from '../../resources/js/lib/money.js';

const root = new URL('../../resources/js/', import.meta.url);
const source = readFileSync(
    new URL('components/MobileAmountInput.vue', root),
    'utf8',
);
function vueFiles(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = new URL(
            entry.name + (entry.isDirectory() ? '/' : ''),
            directory,
        );

        return entry.isDirectory()
            ? vueFiles(path)
            : entry.name.endsWith('.vue')
              ? [path]
              : [];
    });
}

test('all monetary inputs use the shared keypad or an explicitly desktop-only branch', () => {
    let sharedFields = 0;

    for (const path of vueFiles(root)) {
        const text = readFileSync(path, 'utf8');
        sharedFields += [...text.matchAll(/<MobileAmountInput\b/g)].length;

        if (
            path.pathname.endsWith('/MoneyInput.vue') ||
            path.pathname.endsWith('/MobileAmountInput.vue')
        ) {
            continue;
        }

        for (const match of text.matchAll(
            /<(Input|input|MoneyInput)\b[^>]*?\/>/gs,
        )) {
            const tag = match[0];
            const model =
                tag.match(/(?:v-model|:model-value)="([^"]+)"/)?.[1] ?? '';

            if (
                !/amount|desired_balance|myShare|counterpartyShare|annual_gross_salary/i.test(
                    model,
                ) &&
                match[1] !== 'MoneyInput'
            ) {
                continue;
            }

            if (/currency/i.test(model)) {
                continue;
            }

            const desktopFallback =
                ['SharedExpenses.vue', 'ImportRowReviewDialog.vue'].some(
                    (name) => path.pathname.endsWith('/' + name),
                ) && /v-else/.test(tag);
            const desktopBudget = path.pathname.endsWith(
                '/BudgetCellInput.vue',
            );
            assert.ok(
                desktopFallback || desktopBudget,
                `Unprotected monetary field in ${path.pathname}: ${tag}`,
            );
        }
    }

    assert.ok(
        sharedFields >= 37,
        `Only ${sharedFields} shared monetary fields found`,
    );
});

test('counterparty payment, allocation and compensation preserve currency precision', () => {
    const text = readFileSync(
        new URL('pages/counterparties/Show.vue', root),
        'utf8',
    );

    for (const model of [
        'form.amount',
        'allocation.amount',
        'compensation.amount',
    ]) {
        const tag = [...text.matchAll(/<MobileAmountInput\b[^>]*?\/>/gs)].find(
            ([tag]) => tag.includes(`v-model="${model}"`),
        )?.[0];
        assert.ok(tag, model);
        assert.match(tag, /:currency-code="relation.currency"/);
        assert.match(tag, /:precision="relation.precision"/);
    }
});

test('mobile keypad arithmetic emits standard amounts across number formats and precision', () => {
    const functions = source.slice(
        source.indexOf('function serializeAmount'),
        source.indexOf('</script>'),
    );
    const js = ts.transpile(functions, { target: ts.ScriptTarget.ES2022 });

    for (const [locale, expression, precision, expected] of [
        ['it-IT', '10,50+2×3', 2, 16.5],
        ['en-US', '10.50+2×3', 2, 16.5],
        ['it-IT', '1,234+0,001', 3, 1.235],
        ['en-US', '100÷4-5', 0, 20],
        ['it-IT', '10÷0', 2, null],
        ['it-IT', '0-50,25', 2, -50.25],
    ]) {
        const evaluate = new Function(
            'resolvedFormatLocale',
            'props',
            'operatorTokens',
            'getMoneySeparators',
            'normalizeMoneyValue',
            'toStandardMoneyString',
            `${js}; return evaluateDraftExpression;`,
        )(
            { value: locale },
            { precision },
            new Set(['+', '-', '×', '÷']),
            getMoneySeparators,
            normalizeMoneyValue,
            toStandardMoneyString,
        );
        assert.equal(
            evaluate(expression),
            expected,
            `${locale}: ${expression}`,
        );
    }
});

test('shared monetary component and all consumers compile after migration', () => {
    for (const path of vueFiles(root)) {
        const text = readFileSync(path, 'utf8');

        if (
            !text.includes('<MobileAmountInput') &&
            !path.pathname.endsWith('/MobileAmountInput.vue')
        ) {
            continue;
        }

        const { descriptor } = parse(text);
        const bindings = path.pathname.endsWith('/MobileAmountInput.vue')
            ? compileScript(descriptor, { id: path.pathname }).bindings
            : {};
        const result = compileTemplate({
            source: descriptor.template.content,
            filename: path.pathname,
            id: path.pathname,
            compilerOptions: { bindingMetadata: bindings },
        });
        assert.deepEqual(result.errors, [], path.pathname);
    }

    assert.match(
        source,
        /props\.formatLocale \?\?\s*String\(page.props.auth\?\.user\?\.format_locale/,
    );
    assert.match(source, /<MoneyInput\s+v-bind="attrs"/);
});

test('mobile keypad enforces inclusive amount bounds before saving', () => {
    const functionSource = source.slice(
        source.indexOf('function isAmountAllowed'),
        source.indexOf('function saveValue'),
    );
    const js = ts.transpile(functionSource, { target: ts.ScriptTarget.ES2022 });
    const allowed = new Function(
        'attrs',
        'props',
        `${js}; return isAmountAllowed;`,
    )({ min: '0.01', max: '20' }, { allowNegative: false });
    assert.equal(allowed(0), false);
    assert.equal(allowed(0.01), true);
    assert.equal(allowed(20), true);
    assert.equal(allowed(20.01), false);
    assert.equal(allowed(null), false);
    assert.equal(allowed(-1), false);
    const unbounded = new Function(
        'attrs',
        'props',
        `${js}; return isAmountAllowed;`,
    )(
        {
            max: undefined,
        },
        { allowNegative: false },
    );
    assert.equal(unbounded(1000), true);
    assert.match(source, /return !props.required/);
});

test('salary currency codes remain text fields', () => {
    const text = readFileSync(
        new URL('components/recurring/SalarySetupFields.vue', root),
        'utf8',
    );
    assert.match(text, /<Input\s+v-model="model.annual_gross_salary_currency"/);
});

test('salary adjustments retain signed amounts on desktop and mobile', () => {
    const text = readFileSync(
        new URL('pages/transactions/salary/Show.vue', root),
        'utf8',
    );
    assert.match(text, /v-model="component.amount"\s+allow-negative/);
    const functionSource = source.slice(
        source.indexOf('function isAmountAllowed'),
        source.indexOf('function saveValue'),
    );
    const js = ts.transpile(functionSource, { target: ts.ScriptTarget.ES2022 });
    const allowed = new Function(
        'attrs',
        'props',
        `${js}; return isAmountAllowed;`,
    )({}, { allowNegative: true });
    assert.equal(allowed(-50.25), true);
    assert.match(
        source,
        /draftExpression.value = `0-\$\{draftExpression.value}/,
    );
    assert.match(source, /:model-value="absoluteValue"/);
    assert.match(source, /isNegative.value && value !== '' \? `-\$\{value}/);
});
