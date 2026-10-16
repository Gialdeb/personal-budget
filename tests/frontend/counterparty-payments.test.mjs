import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';
const source = readFileSync(
    new URL('../../resources/js/lib/counterparty-payments.ts', import.meta.url),
    'utf8',
);
const compiled = ts.transpileModule(source, {
    compilerOptions: {
        target: ts.ScriptTarget.ES2020,
        module: ts.ModuleKind.ES2020,
    },
}).outputText;
const { parseMinor, decimalMinor, fifoAllocation } = await import(
    'data:text/javascript;base64,' + Buffer.from(compiled).toString('base64')
);
test('exact decimal preview accepts commas and rejects excess precision without floating point', () => {
    assert.equal(parseMinor('100,01', 2), 10001n);
    assert.equal(parseMinor('0.001', 2), null);
    assert.equal(parseMinor('100.00', 0), 100n);
    assert.equal(parseMinor('1.000', 2), 100n);
    assert.equal(parseMinor('1e2', 2), null);
    assert.equal(decimalMinor(-15000n, 2), '-150.00');
    assert.equal(decimalMinor(1n, 0), '1');
    assert.equal(parseMinor('999999999999.99', 2), 99999999999999n);
});
test('FIFO uses economic dates and stable UUID tie breaks even if backend array order changes', () => {
    const claims = [
        { uuid: 'b', economic_date: '2026-01-01', remaining_minor: '10000' },
        { uuid: 'a', economic_date: '2026-01-01', remaining_minor: '4000' },
    ];
    assert.deepEqual(fifoAllocation(claims, 9000n), [
        { obligation_uuid: 'a', minor: 4000n },
        { obligation_uuid: 'b', minor: 5000n },
    ]);
    assert.deepEqual(fifoAllocation(claims, 0n), []);
    assert.deepEqual(
        fifoAllocation(claims, 20000n).map((a) => a.minor),
        [4000n, 10000n],
    );
});

test('timeline describes payers and returns without inventing missing source information', () => {
    const activitySource = readFileSync(
        new URL(
            '../../resources/js/components/shared-expenses/CounterpartyActivity.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const helpers = activitySource.slice(
        activitySource.indexOf('function title('),
        activitySource.indexOf('</script>'),
    );
    const compiledHelpers = ts.transpile(helpers);
    const copy = (text, values = {}) =>
        text.replace(/\{(\w+)\}/g, (_, name) => values[name]);
    const { title, payer } = new Function(
        'ui',
        'money',
        'props',
        `${compiledHelpers}; return { title, payer };`,
    )(copy, (value) => `${value} minor`, { relation: { name: 'Emanuela' } });
    const shared = {
        kind: 'shared',
        title: 'Cena',
        direction: 'received',
        expense_total_minor: '10000',
        original_minor: '5000',
    };
    assert.equal(payer(shared), 'Hai pagato 10000 minor');
    assert.equal(
        payer({ ...shared, direction: 'sent' }),
        'Emanuela ha pagato 10000 minor',
    );
    assert.equal(
        payer({ ...shared, expense_total_minor: null }),
        'Quota riconosciuta · sorgente parziale',
    );
    assert.equal(
        payer({ ...shared, original_minor: '0' }),
        'Quota riconosciuta · sorgente parziale',
    );
    assert.equal(
        payer({ kind: 'manual', direction: 'received' }),
        'Credito verso Emanuela',
    );
    assert.equal(
        payer({ kind: 'manual', direction: 'sent' }),
        'Debito verso Emanuela',
    );
    assert.equal(
        payer({ kind: 'payment', direction: 'received', is_return: false }),
        'Emanuela ti ha pagato',
    );
    assert.equal(
        payer({ kind: 'payment', direction: 'sent', is_return: false }),
        'Hai pagato a Emanuela',
    );
    assert.equal(
        payer({ kind: 'payment', direction: 'received', is_return: true }),
        'Emanuela ti ha restituito denaro',
    );
    assert.equal(
        payer({ kind: 'payment', direction: 'sent', is_return: true }),
        'Hai restituito denaro a Emanuela',
    );
    assert.equal(
        title({ kind: 'payment', direction: 'received', is_return: true }),
        'Rimborso ricevuto',
    );
    assert.equal(
        title({ kind: 'payment', direction: 'sent', is_return: true }),
        'Rimborso effettuato',
    );
    assert.equal(title({ kind: 'compensation' }), 'Compensazione');
});

test('open positions remain distinct from net balance and ignore history filters', () => {
    const page = readFileSync(
        new URL(
            '../../resources/js/pages/counterparties/Show.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const calculation = page.slice(
        page.indexOf('const openPositions ='),
        page.indexOf('const balanceTone ='),
    );
    const props = {
        obligations: [
            { uuid: 'oct-02', direction: 'received', remaining_minor: '0' },
            { uuid: 'oct-04', direction: 'received', remaining_minor: '2000' },
            { uuid: 'oct-10', direction: 'sent', remaining_minor: '6000' },
        ],
        activity: { data: [] },
    };
    const computed = (fn) => ({
        get value() {
            return fn();
        },
    });
    const positions = new Function(
        'computed',
        'props',
        `${ts.transpile(calculation)};return openPositions;`,
    )(computed, props);
    assert.deepEqual(positions.value, { receivable: 2000n, payable: 6000n });
    assert.equal(positions.value.receivable - positions.value.payable, -4000n);
    props.activity.data = [{ kind: 'payment' }];
    assert.deepEqual(positions.value, { receivable: 2000n, payable: 6000n });
    assert.equal(props.obligations[1].remaining_minor, '2000');
    assert.equal(props.obligations[2].remaining_minor, '6000');
});
