import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(
    new URL('../../resources/js/lib/tracked-item-options.ts', import.meta.url),
    'utf8',
);
const compiled = ts.transpileModule(source, {
    compilerOptions: {
        module: ts.ModuleKind.ESNext,
        target: ts.ScriptTarget.ES2022,
    },
}).outputText;
const {
    normalizeReferenceSearch,
    prioritizeReferenceOptions,
    matchesReferenceSearch,
} = await import(
    `data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`
);

test('reference searches normalize case whitespace and composed or decomposed accents', () => {
    for (const input of [
        '  CAFFE   ROMA ',
        'Caffè Roma',
        'Caffe\u0300 Roma',
        'Caffé\u00a0Roma',
    ]) {
        assert.equal(normalizeReferenceSearch(input), 'caffe roma');
    }
});

test('global reference search keeps category suggestions first and other categories searchable', () => {
    const options = [
        {
            value: 'other-category',
            label: 'Caffè Roma',
            compatible: false,
            available: true,
        },
        {
            value: 'foreign',
            label: 'Caffè Roma',
            compatible: true,
            available: false,
        },
        {
            value: 'suggested',
            label: 'Bar corrente',
            compatible: true,
            available: true,
        },
        {
            value: 'uncategorized',
            label: 'Nuovo nome',
            compatible: false,
            available: true,
        },
    ];
    const ranked = prioritizeReferenceOptions(
        options,
        (option) => option.compatible,
        (option) => option.available,
    );
    assert.deepEqual(
        ranked.map((option) => option.value),
        ['suggested', 'other-category', 'uncategorized'],
    );
    assert.deepEqual(
        ranked
            .filter((option) =>
                normalizeReferenceSearch(option.label).includes(
                    normalizeReferenceSearch('CAFFE'),
                ),
            )
            .map((option) => option.value),
        ['other-category'],
    );
    assert.deepEqual(
        options.map((option) => option.value),
        ['other-category', 'foreign', 'suggested', 'uncategorized'],
    );
});

test('transaction recurring and shared selectors submit existing references by UUID', () => {
    for (const name of [
        'transactions/TransactionFormSheet.vue',
        'recurring/RecurringEntryFormSheet.vue',
        'shared-expenses/CounterpartySelect.vue',
    ]) {
        const component = readFileSync(
            new URL(`../../resources/js/components/${name}`, import.meta.url),
            'utf8',
        );
        assert.match(component, /tracked_item_uuid: trackedItemUuid/);
        assert.match(
            component,
            /@update:model-value="\s*(?:handleReferenceSelection|selectCounterparty)\s*"/,
        );
    }

    for (const name of [
        'MobileSearchableSelect.vue',
        'transactions/SearchableSelect.vue',
    ]) {
        const component = readFileSync(
            new URL(`../../resources/js/components/${name}`, import.meta.url),
            'utf8',
        );
        assert.match(component, /matchesReferenceSearch\(option, query\)/);
    }
});

test('alias searches suggest only the canonical option while retaining category priority', () => {
    const canonical = {
        value: 'canonical-uuid',
        label: 'Bar canonico',
        search_terms: ['Caffè precedente', 'alias-uuid'],
        is_selectable: true,
    };
    const historical = {
        value: 'alias-uuid',
        label: 'Caffè precedente',
        is_selectable: false,
    };
    const options = prioritizeReferenceOptions(
        [historical, canonical],
        () => true,
        (option) => option.is_selectable,
    );

    for (const query of [
        ' CAFFE  PRECEDENTE ',
        'alias-uuid',
        'BAR CANONICO',
        'canonical-uuid',
    ]) {
        assert.deepEqual(
            options
                .filter((option) => matchesReferenceSearch(option, query))
                .map((option) => option.value),
            ['canonical-uuid'],
        );
    }

    assert.equal(matchesReferenceSearch(canonical, 'unrelated'), false);
});
