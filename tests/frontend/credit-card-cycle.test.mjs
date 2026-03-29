import assert from 'node:assert/strict';
import test from 'node:test';

import { resolveCreditCardCycle } from '../../resources/js/lib/credit-card-cycle.js';

function formatIso(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

test('resolves current period and next payment for closing 15 payment 16 on 2026-03-27', () => {
    const cycle = resolveCreditCardCycle(new Date('2026-03-27T12:00:00Z'), 15, 16);

    assert.ok(cycle);
    assert.equal(formatIso(cycle.current_period_start), '2026-03-16');
    assert.equal(formatIso(cycle.current_period_end), '2026-04-15');
    assert.equal(formatIso(cycle.next_payment_date), '2026-04-16');
});

test('resolves current period and next payment for closing 17 payment 16 on 2026-03-27', () => {
    const cycle = resolveCreditCardCycle(new Date('2026-03-27T12:00:00Z'), 17, 16);

    assert.ok(cycle);
    assert.equal(formatIso(cycle.current_period_start), '2026-03-18');
    assert.equal(formatIso(cycle.current_period_end), '2026-04-17');
    assert.equal(formatIso(cycle.next_payment_date), '2026-05-16');
});

test('payment day equal to closing day moves to the month after the period end', () => {
    const cycle = resolveCreditCardCycle(new Date('2026-03-27T12:00:00Z'), 15, 15);

    assert.ok(cycle);
    assert.equal(formatIso(cycle.next_payment_date), '2026-05-15');
});

test('short months use the last valid day', () => {
    const cycle = resolveCreditCardCycle(new Date('2026-02-20T12:00:00Z'), 31, 31);

    assert.ok(cycle);
    assert.equal(formatIso(cycle.current_period_start), '2026-02-01');
    assert.equal(formatIso(cycle.current_period_end), '2026-02-28');
    assert.equal(formatIso(cycle.next_payment_date), '2026-03-31');
});

test('returns null for invalid inputs', () => {
    assert.equal(resolveCreditCardCycle(new Date('2026-03-27T12:00:00Z'), 0, 16), null);
    assert.equal(resolveCreditCardCycle(new Date('2026-03-27T12:00:00Z'), 15, 32), null);
});
