import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import {
    compileScript,
    compileTemplate,
    parse,
    registerTS,
} from '@vue/compiler-sfc';
import ts from 'typescript';
import * as Vue from 'vue';
import * as SSR from 'vue/server-renderer';
import { createI18n } from 'vue-i18n';

registerTS(() => ts);
function evaluate(source, require = () => ({})) {
    const exports = {};
    new Function(
        'require',
        'exports',
        ts.transpile(source, {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        }),
    )(require, exports);

    return exports;
}
const source = readFileSync(
    new URL('../../resources/js/lib/date-picker.ts', import.meta.url),
    'utf8',
);
const dates = evaluate(source);

test('technical instants use Italian minute precision and never expose ISO fractions or offsets', () => {
    assert.equal(
        dates.formatDateTime(
            '2026-11-03T12:04:59.123456Z',
            'it',
            'Europe/Rome',
        ),
        '03/11/2026, 13:04',
    );
    assert.equal(
        dates.formatDateTime(
            '2026-11-03T13:04:59.123456+01:00',
            'it',
            'Europe/Rome',
        ),
        '03/11/2026, 13:04',
    );
});

test('formatting follows the selected language', () => {
    assert.equal(
        dates.formatDateTime('2026-11-03T12:04:00Z', 'en-US', 'UTC'),
        '11/03/2026, 12:04 PM',
    );
    assert.equal(
        dates.formatDateTime('2026-11-03T12:04:00Z', 'en-GB', 'UTC'),
        '03/11/2026, 12:04',
    );
});

test('IANA timezone conversion handles both daylight saving transitions', () => {
    for (const [instant, expected] of [
        ['2026-03-29T00:30:00Z', '29/03/2026, 01:30'],
        ['2026-03-29T01:30:00Z', '29/03/2026, 03:30'],
        ['2026-10-25T00:30:00Z', '25/10/2026, 02:30'],
        ['2026-10-25T01:30:00Z', '25/10/2026, 02:30'],
    ]) {
        assert.equal(
            dates.formatDateTime(instant, 'it', 'Europe/Rome'),
            expected,
        );
    }

    assert.equal(
        dates.formatDateTime('2026-01-01T00:15:00Z', 'it', 'America/New_York'),
        '31/12/2025, 19:15',
    );
    assert.equal(
        dates.formatDateTime('2026-01-01T00:15:00Z', 'it', 'Asia/Kathmandu'),
        '01/01/2026, 06:00',
    );
});

test('the browser timezone is used by default and calendar-only values never shift or acquire a time', () => {
    for (const zone of [
        'Europe/Rome',
        'America/Los_Angeles',
        'Pacific/Kiritimati',
    ]) {
        const code = ts.transpile(source.replace(/export /g, ''), {
            target: ts.ScriptTarget.ES2022,
        });
        const result = spawnSync(
            process.execPath,
            [
                '-e',
                `${code}; process.stdout.write(JSON.stringify([formatDateTime('2026-01-01T00:15:00Z','it'), formatDateTime('2026-01-01','it')]));`,
            ],
            { encoding: 'utf8', env: { ...process.env, TZ: zone } },
        );
        assert.equal(result.status, 0, result.stderr);
        assert.deepEqual(JSON.parse(result.stdout), [
            dates.formatDateTime('2026-01-01T00:15:00Z', 'it', zone),
            '01/01/2026',
        ]);
    }
});

test('invalid or missing values never leak technical input to the UI', () => {
    for (const value of [
        null,
        undefined,
        '',
        'not-a-date',
        '2026-02-30',
        '2026-02-30T12:00:00Z',
        '2026-11-03T12:00:00',
    ]) {
        assert.equal(dates.formatDateTime(value, 'it', 'Europe/Rome'), '—');
    }
});

const passThrough = Vue.defineComponent({
    inheritAttrs: false,
    setup:
        (_, { slots }) =>
        () =>
            Vue.h('div', slots.default?.()),
});
function loadComponent(path) {
    const filename = fileURLToPath(new URL(`../../${path}`, import.meta.url));
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), {
        filename,
    });
    assert.deepEqual(errors, []);
    const script = compileScript(descriptor, {
        id: filename,
        fs: {
            fileExists: existsSync,
            readFile: (file) => readFileSync(file, 'utf8'),
        },
    });
    const require = (name) => {
        if (name === 'vue') {
            return Vue;
        }

        if (name === 'vue/server-renderer') {
            return SSR;
        }

        if (name === 'vue-i18n') {
            return {
                createI18n,
                useI18n: () => ({ locale: Vue.inject('fixtureLocale') }),
            };
        }

        if (name === '@/lib/date-picker') {
            return dates;
        }

        if (name === '@/lib/payment-management') {
            return evaluate(
                readFileSync(
                    new URL(
                        '../../resources/js/lib/payment-management.ts',
                        import.meta.url,
                    ),
                    'utf8',
                ),
            );
        }

        if (name === '@/i18n/ui-copy') {
            return {
                useUiCopy:
                    () =>
                    (label, params = {}) =>
                        label.replace(/\{(\w+)\}/g, (_, key) =>
                            String(params[key] ?? ''),
                        ),
            };
        }

        if (name === '@/lib/currency') {
            return { formatCurrency: (amount) => `${amount} EUR` };
        }

        if (name === '@/composables/useNotifiedForm') {
            return { useForm: (data) => Vue.reactive({ ...data, errors: {} }) };
        }

        if (name === '@inertiajs/vue3') {
            return { Link: passThrough };
        }

        return new Proxy(
            { default: passThrough },
            {
                get: (target, key) =>
                    key === '__esModule' ? true : (target[key] ?? passThrough),
            },
        );
    };
    const component = evaluate(script.content, require).default;
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: filename,
        ssr: true,
        ssrCssVars: descriptor.cssVars,
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.deepEqual(template.errors, []);
    component.ssrRender = evaluate(template.code, require).ssrRender;

    return component;
}

for (const locale of ['it', 'en-US']) {
    test(`payment history uses the central formatter in the dashboard and occurrence detail (${locale})`, async () => {
        const timestamp = '2026-11-03T12:04:59.123456Z';
        const management = {
            occurrence_uuid: 'occurrence',
            entry_uuid: 'entry',
            reference: 'Payment',
            expected_amount: 100,
            expected_date: '2026-11-02',
            actual_amount: null,
            actual_date: null,
            estimated_date: null,
            state: 'pending',
            has_automatic_transaction: false,
            can_rectify: false,
            history: [
                {
                    id: 1,
                    reason: 'not_charged',
                    action: 'pending',
                    created_at: timestamp,
                    notes: null,
                    before: {},
                    after: {},
                },
            ],
        };

        for (const [path, props] of [
            [
                'resources/js/components/dashboard/ManagePaymentDialog.vue',
                {
                    open: true,
                    currency: 'EUR',
                    item: { currency: 'EUR', payment_management: management },
                },
            ],
            [
                'resources/js/components/recurring/OccurrencePayments.vue',
                { currency: 'EUR', payments: [], management },
            ],
        ]) {
            const app = Vue.createSSRApp(loadComponent(path), props);
            app.provide('fixtureLocale', Vue.ref(locale));
            const html = await SSR.renderToString(app);
            const visible = html.replace(/<[^>]*>/g, '');
            assert.ok(
                visible.includes(dates.formatDateTime(timestamp, locale)),
                visible,
            );
            assert.ok(
                visible.includes(
                    dates.formatDatePickerValue('2026-11-02', locale),
                ),
                visible,
            );
            assert.doesNotMatch(visible, /2026-11-03T|123456|2026-11-02/);
            assert.ok(html.includes(`datetime="${timestamp}"`));
        }
    });
}
