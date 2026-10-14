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
