import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { baseParse, compile } from '@vue/compiler-dom';
import { parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';
import { renderToString } from 'vue/server-renderer';
import {
    recurringOccurrenceHeading,
    recurringPlanHeading,
    sameRecordText,
    transactionHeading,
} from '../../resources/js/lib/transaction-presentation.js';

test('headings suppress equivalent text regardless of case and whitespace', () => {
    assert.equal(
        sameRecordText(' Internet  Casa ', 'INTERNET\u00a0CASA'),
        true,
    );
    assert.equal(sameRecordText('Inter net', 'internet'), true);
    assert.deepEqual(
        transactionHeading(
            { description: 'Fibra', detail: ' FIBRA ', notes: '\tfibra\n' },
            'Movimento',
        ),
        { title: 'Fibra', subtitle: null },
    );
    assert.deepEqual(
        recurringPlanHeading(
            { title: 'Fibra', description: ' fi bra ' },
            'Piano',
        ),
        { title: 'Fibra', subtitle: null },
    );
});

test('context determines primary text and distinct information remains visible', () => {
    const movement = Object.freeze({
        description: 'Rata ottobre',
        detail: 'Contratto 123',
        notes: 'Pagamento annuale',
    });
    assert.deepEqual(transactionHeading(movement, 'Movimento'), {
        title: 'Rata ottobre',
        subtitle: 'Contratto 123 · Pagamento annuale',
    });
    assert.deepEqual(
        recurringOccurrenceHeading(
            {
                title: 'Scadenza',
                description: 'Ottobre',
                recurring_entry: {
                    title: 'Internet casa',
                    description: 'Contratto 123',
                },
            },
            'Piano',
        ),
        { title: 'Internet casa', subtitle: 'Ottobre · Contratto 123' },
    );
    assert.deepEqual(
        transactionHeading(
            {
                description: 'Rata',
                detail: 'Provider',
                tracked_item_label: ' provider ',
            },
            'Movimento',
        ),
        { title: 'Rata', subtitle: null },
    );
    assert.equal(movement.description, 'Rata ottobre');
});

test('missing or blank text uses coherent fallbacks without empty subtitles', () => {
    assert.deepEqual(transactionHeading(null, 'Movimento senza descrizione'), {
        title: 'Movimento senza descrizione',
        subtitle: null,
    });
    assert.deepEqual(
        transactionHeading(
            { description: ' ', detail: 'Dettaglio' },
            'Movimento',
        ),
        { title: 'Dettaglio', subtitle: null },
    );
    assert.deepEqual(
        recurringPlanHeading(
            { title: ' ', description: 'Descrizione' },
            'Piano ricorrente',
        ),
        { title: 'Descrizione', subtitle: null },
    );
    assert.deepEqual(recurringOccurrenceHeading({}, 'Piano ricorrente'), {
        title: 'Piano ricorrente',
        subtitle: null,
    });
});

function headingParagraphs(filename, helper, offset = 0) {
    const { descriptor } = parse(readFileSync(filename, 'utf8'));
    const nodes = [];
    function visit(node) {
        if (
            node.type === 1 &&
            ['p', 'Link'].includes(node.tag) &&
            node.loc.source.includes(helper)
        ) {
            nodes.push(node.loc.source);

            return;
        }

        node.children?.forEach(visit);
    }
    visit(baseParse(descriptor.template.content));
    assert.ok(nodes.length >= 2, filename);

    // These elements are the actual primary and secondary headings, not metadata or actions.
    return nodes
        .slice(offset, offset + (helper === 'movementHeading' ? 2 : 3))
        .join('\n');
}

for (const [context, filename, helper, offset] of [
    [
        'transaction desktop',
        'resources/js/pages/transactions/Show.vue',
        'movementHeading',
    ],
    [
        'transaction mobile',
        'resources/js/pages/transactions/Show.vue',
        'movementHeading',
        2,
    ],
    [
        'recurring desktop',
        'resources/js/pages/transactions/recurring/Index.vue',
        'recurringOccurrenceHeading',
    ],
    [
        'recurring mobile',
        'resources/js/components/recurring/RecurringOccurrencesMobileList.vue',
        'recurringOccurrenceHeading',
    ],
]) {
    test(`${context} renders shared headings with equal, different and missing texts`, async () => {
        const snippet = headingParagraphs(filename, helper, offset);
        const { code } = compile(`<div>${snippet}</div>`, {
            mode: 'function',
            prefixIdentifiers: true,
        });
        const render = new Function('Vue', code)(Vue);
        async function html(description, title = 'Fibra') {
            return renderToString(
                Vue.createSSRApp({
                    components: {
                        Link: {
                            setup:
                                (_, { slots }) =>
                                () =>
                                    Vue.h('a', slots.default?.()),
                        },
                    },
                    setup: () => ({
                        transaction: {
                            description: title,
                            detail: description,
                        },
                        occurrence: {
                            title,
                            description,
                            recurring_entry: { title, show_url: '/plan' },
                        },
                        movementHeading: (record) =>
                            transactionHeading(record, 'Movimento'),
                        recurringOccurrenceHeading,
                        ui: (value) => value,
                    }),
                    render,
                }),
            );
        }
        const equal = await html(' FIBRA ');
        assert.equal((equal.match(/Fibra/g) ?? []).length >= 1, true);
        assert.doesNotMatch(equal, /> FIBRA </);
        assert.match(await html('Contratto 123'), /Contratto 123/);
        assert.doesNotMatch(await html(null), /undefined|null/);
        assert.match(
            await html(null, ''),
            context.startsWith('transaction')
                ? /Movimento/
                : /Piano ricorrente/,
        );
    });
}
