import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    parse,
    compileScript,
    compileTemplate,
    registerTS,
} from '@vue/compiler-sfc';
import { renderToString } from '@vue/server-renderer';
import ts from 'typescript';
import * as Vue from 'vue';

registerTS(() => ts);

test('partial payment breakdown renders due paid and remaining on mobile and desktop', async () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/pages/transactions/recurring/Show.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const breakdowns = source.match(
        /<dl\s+v-if="\s*\(occurrence\.allocated_amount \?\? 0\) >\s*0\s*"[\s\S]*?<\/dl>/g,
    );
    assert.equal(breakdowns?.length, 2);

    for (const breakdown of breakdowns) {
        const compiled = compileTemplate({
            source: breakdown,
            filename: 'payment-breakdown.vue',
            id: 'payment-breakdown',
            compilerOptions: { mode: 'function' },
        });
        assert.deepEqual(compiled.errors, []);
        const html = await renderToString(
            Vue.createSSRApp({
                render: new Function('Vue', compiled.code)(Vue),
                components: {
                    SensitiveValue: {
                        props: ['value'],
                        template: '<span>{{ value }}</span>',
                    },
                },
                setup: () => ({
                    occurrence: {
                        expected_amount: 500,
                        allocated_amount: 450,
                        remaining_amount: 50,
                    },
                    t: (key) => key,
                    formatMoney: (amount) => `${amount.toFixed(2)} EUR`,
                }),
            }),
        );

        for (const value of [
            'amountDue',
            'amountPaid',
            'amountRemaining',
            '500.00 EUR',
            '450.00 EUR',
            '50.00 EUR',
        ]) {
            assert.ok(html.includes(value));
        }
    }
});

for (const change of [
    null,
    { difference: 5, percentage: 16.67 },
    { difference: -5, percentage: -14.29 },
]) {
    test(`plan amount history renders ${change === null ? 'nothing without changes' : change.difference > 0 ? 'a price increase' : 'a price decrease'}`, async () => {
        const source = readFileSync(
            new URL(
                '../../resources/js/pages/transactions/recurring/Show.vue',
                import.meta.url,
            ),
            'utf8',
        );
        const section = source.match(
            /<section\s+v-if="recurringEntry.amount_history\?\.length"[\s\S]*?<\/section>/,
        )?.[0];
        assert.ok(section);
        const compiled = compileTemplate({
            source: section,
            filename: 'amount-history.vue',
            id: 'amount-history',
            compilerOptions: { mode: 'function' },
        });
        assert.deepEqual(compiled.errors, []);
        const render = new Function('Vue', compiled.code)(Vue);
        const history =
            change === null
                ? []
                : [
                      {
                          id: 1,
                          previous_amount:
                              change.difference > 0 ? 29.99 : 34.99,
                          new_amount: change.difference > 0 ? 34.99 : 29.99,
                          currency: 'EUR',
                          effective_from: '2027-01-01',
                          previous_until: '2026-12-31',
                          ...change,
                      },
                  ];
        const html = await renderToString(
            Vue.createSSRApp({
                render,
                components: {
                    Badge: { template: '<span><slot /></span>' },
                    SensitiveValue: {
                        props: ['value'],
                        template: '<span>{{ value }}</span>',
                    },
                },
                setup: () => ({
                    recurringEntry: { amount_history: history },
                    locale: 'it-IT',
                    t: (key, args) => `${key} ${args?.date ?? ''}`,
                    formatCurrency: (amount) =>
                        new Intl.NumberFormat('it-IT', {
                            style: 'currency',
                            currency: 'EUR',
                        }).format(amount),
                }),
            }),
        );

        if (change === null) {
            assert.ok(!html.includes('plan-amount-history'));
        } else {
            assert.ok(html.includes(change.difference > 0 ? '+5,00' : '-5,00'));
            assert.ok(
                html.includes(change.percentage > 0 ? '+16,67%' : '-14,29%'),
            );
            assert.ok(html.includes('01/01/2027'));
            assert.ok(html.includes('31/12/2026'));
        }
    });
}

for (const automatic of [true, false]) {
    test(`plan detail renders ${automatic ? 'Automatica' : 'Manuale'} from its posting configuration`, async () => {
        const source = readFileSync(
            new URL(
                '../../resources/js/pages/transactions/recurring/Show.vue',
                import.meta.url,
            ),
            'utf8',
        );
        const badge = source.match(
            /<Badge\s+data-testid="plan-posting-mode"[\s\S]*?<\/Badge>/,
        )?.[0];
        assert.ok(badge);
        const compiled = compileTemplate({
            source: badge,
            filename: 'plan-mode.vue',
            id: 'plan-mode',
            compilerOptions: { mode: 'function' },
        });
        assert.deepEqual(compiled.errors, []);
        const render = new Function('Vue', compiled.code)(Vue);
        const html = await renderToString(
            Vue.createSSRApp({
                render,
                components: { Badge: { template: '<span><slot /></span>' } },
                setup: () => ({
                    entry: { auto_create_transaction: automatic },
                    t: (key) =>
                        key.endsWith('automaticMode')
                            ? 'Automatica'
                            : 'Manuale',
                }),
            }),
        );
        assert.ok(html.includes(automatic ? 'Automatica' : 'Manuale'));
        assert.ok(!html.includes(automatic ? 'Manuale' : 'Automatica'));
    });
}

const recurringPath = new URL(
    '../../resources/js/components/recurring/RecurringEntryFormSheet.vue',
    import.meta.url,
);
const sharedPath = new URL(
    '../../resources/js/components/shared-expenses/SharedExpenseFields.vue',
    import.meta.url,
);

for (const path of [recurringPath, sharedPath]) {
    test(`${path.pathname.split('/').pop()} option cards compile`, () => {
        const source = readFileSync(path, 'utf8');
        const { descriptor, errors } = parse(source, {
            filename: path.pathname,
        });
        assert.deepEqual(errors, []);
        const script = compileScript(descriptor, { id: path.pathname });
        const template = compileTemplate({
            source: descriptor.template.content,
            filename: path.pathname,
            id: path.pathname,
            compilerOptions: { bindingMetadata: script.bindings },
        });
        assert.deepEqual(template.errors, []);
    });
}

test('recurring sheet constrains iOS scrolling to its shrinkable content pane', () => {
    const source = readFileSync(recurringPath, 'utf8');
    assert.match(source, /flex h-full min-h-0 flex-col overflow-hidden/);
    assert.match(
        source,
        /min-h-0 min-w-0 flex-1 touch-pan-y overflow-x-hidden overflow-y-auto overscroll-y-contain/,
    );
    assert.match(source, /\[-webkit-overflow-scrolling:touch\]/);
    assert.match(source, /shrink-0 border-t/);
    assert.match(source, /:style="mobileFooterStyle"/);
});

test('refunded recurring history unlocks only the start date for realignment', () => {
    const source = readFileSync(recurringPath, 'utf8');
    assert.match(source, /const startDateLocked = computed/);
    assert.match(source, /!props.entry\?\.stats.can_realign_refunded_schedule/);
    assert.match(
        source,
        /id="recurring-start-date"[\s\S]*?:disabled="startDateLocked"/,
    );
    assert.match(source, /locked.refundedSchedule/);
});

test('recurring option cards have decorative icons and preserve existing models', () => {
    const source = readFileSync(recurringPath, 'utf8');
    assert.match(source, /<Gift[\s\S]*?aria-hidden="true"/);
    assert.match(source, /<SlidersHorizontal[\s\S]*?aria-hidden="true"/);
    assert.match(source, /v-model="form.trial_enabled"/);
    assert.match(source, /:model-value="\s*form\.is_amount_variable\s*"/);
    assert.match(source, /<SharedExpenseFields\b[^>]*\bdecorated\b/);
    assert.ok(source.includes('[&_button:not([role=checkbox])]:min-h-11'));
    assert.ok(source.includes('[&_input:not([type=checkbox])]:min-h-11'));
    const shared = readFileSync(sharedPath, 'utf8');
    assert.match(shared, /decorated: false/);
    assert.match(shared, /<UsersRound[\s\S]*?aria-hidden="true"/);
    assert.match(shared, /v-model="enabled"/);
});
