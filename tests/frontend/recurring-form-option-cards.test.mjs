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
        /<dl\s[^>]*>[\s\S]*?amountDue:[\s\S]*?<\/dl>/g,
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
                    ui: (text) => text,
                    occurrenceAmountLabel: (label) =>
                        ({
                            amountDue: 'Totale',
                            amountPaid: 'Già pagato',
                            amountRemaining: 'Residuo',
                        })[label],
                    formatMoney: (amount) => `${amount.toFixed(2)} EUR`,
                }),
            }),
        );

        for (const value of [
            'Totale',
            'Già pagato',
            'Residuo',
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
                    entry: { expected_amount: history[0]?.new_amount ?? 0 },
                    historyOpen: true,
                    latestAmountChange: history[0] ?? null,
                    formatMoney: (amount, currency = 'EUR') =>
                        new Intl.NumberFormat('it-IT', {
                            style: 'currency',
                            currency,
                        }).format(amount),
                    ui: (text) => text,
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
            assert.ok(html.includes('amount-history-details'));
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

test('income type cards keep boolean selection, icons and accessible native radio controls', async () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/components/recurring/RecurringEntryFormSheet.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const choices = source.match(
        /<fieldset\s[^>]*data-income-type-choices[\s\S]*?<\/fieldset>/,
    )?.[0];
    assert.ok(choices);
    assert.doesNotMatch(choices, /<select/);
    const compiled = compileTemplate({
        source: choices,
        filename: 'income-type-choices.vue',
        id: 'income-type-choices',
        compilerOptions: { mode: 'function' },
    });
    assert.deepEqual(compiled.errors, []);
    const render = new Function('Vue', compiled.code)(Vue);
    const { Banknote, BriefcaseBusiness, Check } =
        await import('lucide-vue-next');
    const context = {
        isEditing: false,
        isSalary: false,
        Banknote,
        BriefcaseBusiness,
        Check,
        t: (key) => key,
    };
    function inputs(node) {
        if (Array.isArray(node)) {
            return node.flatMap(inputs);
        }

        if (!node || typeof node !== 'object') {
            return [];
        }

        return node.type === 'input' ? [node] : inputs(node.children);
    }
    let radios = [];
    const renderChoices = () =>
        renderToString(
            Vue.createSSRApp({
                components: { Check },
                render() {
                    const node = render(context, []);
                    radios = inputs(node);

                    return node;
                },
            }),
        );
    await renderChoices();

    for (const selected of [false, true, false]) {
        assert.equal(radios.length, 2);
        assert.deepEqual(
            radios.map((radio) => radio.props.value),
            [false, true],
        );
        const radio = radios.find((item) => item.props.value === selected);
        radio.props['onUpdate:modelValue'](selected);
        assert.equal(context.isSalary, selected);
        const html = await renderChoices();
        assert.equal((html.match(/type="radio"/g) ?? []).length, 2);
        assert.equal((html.match(/ checked/g) ?? []).length, 1);
        assert.ok(html.includes('salary.normal'));
        assert.ok(html.includes('salary.salary'));
        assert.ok(html.includes('lucide-banknote'));
        assert.ok(html.includes('lucide-briefcase-business'));
        assert.match(
            html,
            /bg-emerald-100 text-emerald-700[^<]*><svg[^>]*lucide-briefcase-business/,
        );
        assert.ok(html.includes('grid-cols-2'));
        assert.ok(html.includes('peer-focus-visible:outline-2'));
    }

    context.isEditing = true;
    await renderChoices();
    assert.equal(radios.length, 0);
});

test('salary mode animates plan controls and employer reference without changing the visibility rules', () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/components/recurring/RecurringEntryFormSheet.vue',
            import.meta.url,
        ),
        'utf8',
    );
    assert.match(
        source,
        /<Transition\s+name="salary-fields"[\s\S]*?>\s*<div v-if="!salaryMode" class="grid">/,
    );
    assert.match(
        source,
        /<Transition name="salary-fields">\s*<div v-if="salaryMode" class="grid">/,
    );
    assert.match(source, /grid-template-rows 450ms ease-in-out/);
    assert.match(source, /@after-leave="keepIncomeTypeVisible"/);
    assert.match(source, /incomeTypeChoices.value\?\.scrollIntoView/);
    assert.match(
        source,
        /salary-fields-leave-to[\s\S]*?grid-template-rows: 0fr/,
    );
    assert.match(
        source,
        /prefers-reduced-motion: reduce[\s\S]*?transition: none/,
    );
});

test('employer reference menus remain unclipped after salary mode finishes animating', () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/components/recurring/RecurringEntryFormSheet.vue',
            import.meta.url,
        ),
        'utf8',
    );
    const referenceWrapper = source.match(
        /<div\s+class="salary-reference-content[^>]*>[\s\S]*?<\/div>/,
    )?.[0];
    assert.ok(referenceWrapper);
    assert.match(referenceWrapper, /<ReuseReference/);
    assert.doesNotMatch(referenceWrapper, /overflow-hidden/);
    assert.match(
        source,
        /\.salary-fields-enter-active > \.salary-reference-content,[\s\S]*?\.salary-fields-leave-active > \.salary-reference-content\s*\{\s*overflow: hidden;/,
    );
});

test('cancelled monthly rows only expose plan information on desktop and mobile', async () => {
    for (const path of [
        'resources/js/pages/transactions/recurring/Index.vue',
        'resources/js/components/recurring/RecurringOccurrencesMobileList.vue',
    ]) {
        const source = readFileSync(
            new URL(`../../${path}`, import.meta.url),
            'utf8',
        );
        const actions = source.match(
            /<template\s+v-if="\s*occurrence.status\s*!==\s*'cancelled'\s*"\s*>[\s\S]*?<\/Link>/,
        )?.[0];
        assert.ok(actions, path);
        const compiled = compileTemplate({
            source: `<div>${actions}</div>`,
            filename: 'monthly-actions.vue',
            id: 'monthly-actions',
            compilerOptions: { mode: 'function' },
        });
        assert.deepEqual(compiled.errors, []);
        const render = new Function('Vue', compiled.code)(Vue);

        for (const status of ['cancelled', 'pending']) {
            const app = Vue.createSSRApp({
                render,
                setup: () => ({
                    occurrence: {
                        status,
                        can_convert: true,
                        converted_transaction: { can_refund: true },
                        recurring_entry: {
                            uuid: 'child',
                            can_edit: true,
                            can_delete: true,
                            show_url: '/recurring-entries/plan',
                        },
                    },
                    linkedEntry: (occurrence) => occurrence.recurring_entry,
                    t: (key) => key,
                }),
            });

            for (const [componentName, tag] of [
                ['Button', 'button'],
                ['Link', 'a'],
            ]) {
                app.component(componentName, {
                    render() {
                        return Vue.h(
                            tag,
                            tag === 'a' ? { href: this.$attrs.href } : {},
                            this.$slots.default?.(),
                        );
                    },
                });
            }

            for (const icon of ['Pencil', 'Trash2']) {
                app.component(icon, { render: () => null });
            }

            const html = await renderToString(app);
            assert.ok(
                html.includes('transactions.recurring.actions.openPlan'),
                path,
            );
            assert.ok(html.includes('href="/recurring-entries/plan"'), path);
            assert.equal(
                (html.match(/<button/g) ?? []).length,
                status === 'cancelled' ? 0 : 4,
                `${path} ${status}`,
            );
        }
    }
});
