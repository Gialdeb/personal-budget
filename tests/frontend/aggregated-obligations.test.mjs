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
const filename = fileURLToPath(
    new URL(
        '../../resources/js/pages/credits-debts/Index.vue',
        import.meta.url,
    ),
);
const source = readFileSync(filename, 'utf8');

test('reimbursement mobile form reuses shared quick choices and compiles', () => {
    const path = fileURLToPath(
        new URL('../../resources/js/pages/SharedExpenses.vue', import.meta.url),
    );
    const content = readFileSync(path, 'utf8');
    const { descriptor, errors } = parse(content, { filename: path });
    assert.deepEqual(errors, []);
    const script = compileScript(descriptor, { id: path });
    assert.deepEqual(
        compileTemplate({
            source: descriptor.template.content,
            filename: path,
            id: path,
            compilerOptions: { bindingMetadata: script.bindings },
        }).errors,
        [],
    );
    assert.match(content, /:options="quickReferences"/);
    assert.match(content, /:options="categoryChildren"/);
    assert.match(content, /MobileAmountInput/);
    assert.match(content, /website_url: newReferenceWebsite/);
    assert.match(content, /grid grid-cols-2 gap-3 py-2/);
    assert.match(content, /text-2xl font-bold tracking-tight tabular-nums/);
    assert.match(content, /ui\('Devi a \{name\}'/);
    assert.match(
        content,
        /min-h-11 items-center text-xs font-medium text-primary/,
    );
});

test('capacity simulation counts a forecast obligation once when both switches are enabled', () => {
    const card = readFileSync(
        new URL(
            '../../resources/js/components/dashboard/SpendingCapacityCard.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const expression = card
        .match(/const projection = computed\(([\s\S]*?)\n\);/)[1]
        .trim()
        .replace(/,$/, '');
    const evaluate = new Function(
        'props',
        'includeForecast',
        'includeDebts',
        'includeCredits',
        `return (${expression})();`,
    );
    const props = {
        capacity: {
            remaining_budget_raw: 2000,
            simulation_impacts: {
                forecast_expenses_raw: -500,
                open_debts_raw: -1000,
                open_credits_raw: 350,
                forecast_debts_overlap_raw: 500,
            },
        },
    };
    const value = (forecast, debts, credits) =>
        evaluate(
            props,
            { value: forecast },
            { value: debts },
            { value: credits },
        );
    assert.equal(value(false, false, false), 2000);
    assert.equal(value(true, false, false), 1500);
    assert.equal(value(false, true, false), 1000);
    assert.equal(value(true, true, false), 1000);
    assert.equal(value(true, true, true), 1350);
});

test('aggregate page compiles and guards derived actions on desktop and mobile', () => {
    const { descriptor, errors } = parse(source, { filename });
    assert.deepEqual(errors, []);
    const script = compileScript(descriptor, {
        id: filename,
        fs: {
            fileExists: existsSync,
            readFile: (path) => readFileSync(path, 'utf8'),
        },
    });
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: filename,
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);
    assert.match(source, /if\s*\(item\.read_only\)\s*\{?\s*return\s*;/g);
    assert.equal(
        (source.match(/:href="selectedItem.source_url"/g) ?? []).length,
        2,
    );
    assert.ok((source.match(/!selectedItem.read_only/g) ?? []).length >= 4);
    assert.match(source, /originLabel\(item\)/);
    assert.match(source, /Posizioni aperte senza scadenza/);
    assert.match(source, /grid min-w-0 grid-cols-1 gap-4 xl:grid-cols/);
    assert.match(
        source,
        /flex flex-wrap items-center justify-between gap-3 border-b/,
    );
    assert.match(source, /['"]shrink-0 font-bold whitespace-nowrap['"]/);
});
