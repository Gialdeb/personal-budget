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

        if (
            name === 'transactions/TransactionFormSheet.vue' ||
            name === 'recurring/RecurringEntryFormSheet.vue'
        ) {
            if (name.startsWith('transactions/')) {
                assert.match(
                    component,
                    /form.tracked_item_uuid = value.slice\('tracked_item:'.length\)/,
                );
            }

            assert.match(component, /<ReferencePicker/);
        } else {
            assert.match(component, /tracked_item_uuid: trackedItemUuid/);
        }

        assert.match(
            component,
            /@update:model-value="\s*(?:handleReferenceSelection|selectCounterparty|\(value\) => handleReferenceSelection\(value \|\| NONE_VALUE\))\s*"/,
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

test('mobile reference search recognizes names and exact associated domains without spelling aliases', () => {
    const amazon = {
        value: 'amazon',
        label: 'Amazon',
        domain: 'amazon.it',
        search_terms: ['Il negozio online'],
    };

    for (const query of [
        'amazon',
        'Amazon',
        '  AMÁZON  ',
        'amazon.it',
        'www.amazon.it',
        'https://www.amazon.it/gp/cart?ref=test',
        ' HTTP://AMAZON.IT/ ',
    ]) {
        assert.equal(
            matchesReferenceSearch(amazon, query, { domain: true }),
            true,
            query,
        );
    }

    assert.equal(
        matchesReferenceSearch(amazon, 'il   negozio online', { domain: true }),
        true,
    );

    for (const query of [
        'notamazon.it',
        'amazon.it.evil.example',
        'amazon.com',
        'https://www.amazon.com/cart',
    ]) {
        assert.equal(
            matchesReferenceSearch(amazon, query, { domain: true }),
            false,
            query,
        );
    }

    assert.equal(
        matchesReferenceSearch(
            { ...amazon, domain: 'https://www.amazon.it/' },
            'amazon.it',
            { domain: true },
        ),
        true,
    );
    assert.equal(
        matchesReferenceSearch(
            { ...amazon, domain: 'notamazon.it' },
            'amazon.it',
            { domain: true },
        ),
        false,
    );
    // The existing desktop/generic behavior remains opt-in and unchanged.
    assert.equal(matchesReferenceSearch(amazon, 'amazon.it'), false);
    assert.equal(
        normalizeReferenceSearch('https://www.amazon.it/cart'),
        'https://www.amazon.it/cart',
    );
});

test('mobile reference domain normalization reuses accent and whitespace normalization', () => {
    assert.equal(
        normalizeReferenceSearch('  Caffè   Roma  ', { domain: true }),
        'caffe roma',
    );
    assert.equal(
        normalizeReferenceSearch('  HTTPS://WWW.AMAZON.IT/cart?q=1  ', {
            domain: true,
        }),
        'amazon.it',
    );
    assert.equal(
        normalizeReferenceSearch('www.amazon.it', { domain: true }),
        'amazon.it',
    );
    assert.equal(
        normalizeReferenceSearch('Amazon', { domain: true }),
        'amazon',
    );
    assert.equal(
        normalizeReferenceSearch('https://', { domain: true }),
        'https://',
    );
    assert.equal(
        normalizeReferenceSearch('javascript:amazon.it', { domain: true }),
        'javascript:amazon.it',
    );
});

test('mobile search ranks matching personal category references before truncation and retains distinct homonyms', () => {
    const options = [
        ...Array.from({ length: 8 }, (_, index) => ({
            value: `other-${index}`,
            label: 'Amazon',
            domain: 'amazon.com',
        })),
        { value: 'category-personal', label: 'Amazon', domain: 'amazon.it' },
        {
            value: 'different-brand',
            label: 'Amazonas',
            domain: 'amazonas.example',
        },
    ];
    const ranked = prioritizeReferenceOptions(
        options.filter((option) =>
            matchesReferenceSearch(option, 'AMAZON', { domain: true }),
        ),
        (option) => option.value === 'category-personal',
        () => true,
    );
    assert.equal(ranked.slice(0, 5)[0].value, 'category-personal');
    assert.equal(ranked.length, 10);
    assert.deepEqual(
        options
            .filter((option) =>
                matchesReferenceSearch(option, 'https://www.amazon.it/order', {
                    domain: true,
                }),
            )
            .map((option) => option.value),
        ['category-personal'],
    );
});
