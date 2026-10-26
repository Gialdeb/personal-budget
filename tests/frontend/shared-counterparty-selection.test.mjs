import assert from 'node:assert/strict';
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

registerTS(() => ts);
const filename = fileURLToPath(
    new URL(
        '../../resources/js/components/shared-expenses/CounterpartySelect.vue',
        import.meta.url,
    ),
);
const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), {
    filename,
});
const script = compileScript(descriptor, {
    id: filename,
    fs: {
        fileExists: existsSync,
        readFile: (file) => readFileSync(file, 'utf8'),
    },
});
const existing = {
    value: '8ff4523f-b7bc-4113-8140-739aa75a989c',
    label: 'Persona esistente',
};
function setupPicker(
    options = [existing],
    response = { uuid: 'pending-reference:person', name: 'Persona esistente' },
    ok = true,
) {
    const requests = [];
    const emitted = [];
    const model = Vue.ref('');
    const exports = {};
    const require = (name) => {
        if (name === 'vue') {
            return { ...Vue, useModel: () => model };
        }

        if (name === '@/i18n/ui-copy') {
            return { useUiCopy: () => (value) => value };
        }

        if (name === '@/routes/shared-expenses/counterparties') {
            return { store: { url: () => '/shared-expenses/counterparties' } };
        }

        if (name === '@/lib/notifications') {
            return {
                fetchWithNotifications: async (url, input) => {
                    requests.push({ url, ...JSON.parse(input.body) });

                    return { ok, json: async () => response };
                },
            };
        }

        return {};
    };
    new Function(
        'require',
        'exports',
        ts.transpile(script.content, {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        }),
    )(require, exports);
    const bindings = exports.default.setup(
        {
            counterparties: options,
            disabled: false,
            side: 'bottom',
            teleport: true,
        },
        { expose: () => {}, emit: (...args) => emitted.push(args) },
    );

    return { bindings, requests, emitted, model };
}
const previousDocument = Object.getOwnPropertyDescriptor(
    globalThis,
    'document',
);
test.before(() =>
    Object.defineProperty(globalThis, 'document', {
        value: { querySelector: () => null },
        configurable: true,
    }),
);
test.after(() => {
    if (previousDocument) {
        Object.defineProperty(globalThis, 'document', previousDocument);
    } else {
        delete globalThis.document;
    }
});

test('selecting an existing reference prepares its UUID without creating or renaming it', async () => {
    const picker = setupPicker();
    await picker.bindings.selectCounterparty(existing.value);
    assert.deepEqual(picker.requests, [
        {
            url: '/shared-expenses/counterparties',
            draft: true,
            tracked_item_uuid: existing.value,
        },
    ]);
    assert.equal(picker.model.value, 'pending-reference:person');
    assert.equal(
        picker.bindings.counterparties.value.find(
            (option) => option.value === picker.model.value,
        )?.label,
        'Persona esistente',
    );
    assert.equal(picker.emitted[0][0], 'prepared');
    assert.equal(
        picker.emitted.some(([name]) => name === 'created'),
        false,
    );
    await picker.bindings.selectCounterparty(picker.model.value);
    assert.equal(picker.requests.length, 1);
});

test('inline creation is provisional and remains selectable until the principal form is submitted', async () => {
    const picker = setupPicker([], {
        uuid: 'pending-reference:new',
        name: 'Nuova persona',
    });
    await picker.bindings.createCounterparty(' Nuova persona ');
    assert.deepEqual(picker.requests, [
        {
            url: '/shared-expenses/counterparties',
            draft: true,
            name: 'Nuova persona',
        },
    ]);
    assert.equal(picker.model.value, 'pending-reference:new');
    assert.equal(picker.emitted[0][0], 'created');
    await picker.bindings.selectCounterparty(picker.model.value);
    assert.equal(picker.requests.length, 1);
});

test('empty unknown and rejected selections never produce an unsolicited reference', async () => {
    const picker = setupPicker(
        [existing],
        { errors: { tracked_item_uuid: ['Selezione non disponibile'] } },
        false,
    );
    await picker.bindings.selectCounterparty('unavailable');
    await picker.bindings.createCounterparty('  ');
    assert.equal(picker.requests.length, 0);
    await picker.bindings.selectCounterparty(existing.value);
    assert.equal(picker.model.value, '');
    assert.deepEqual(picker.emitted, []);
    assert.ok(picker.bindings.counterpartyCreationError.value);
});

test('provisional selections survive closing and reopening the shared fields', async () => {
    const picker = setupPicker([
        {
            value: 'pending-reference:previous-selection',
            label: 'Persona provvisoria',
        },
    ]);
    await picker.bindings.selectCounterparty(
        'pending-reference:previous-selection',
    );
    assert.equal(picker.model.value, 'pending-reference:previous-selection');
    assert.equal(picker.requests.length, 0);
    assert.deepEqual(picker.emitted, []);
});

test('counterparty selector compiles with its existing shared expense form interface', () => {
    assert.deepEqual(errors, []);
    assert.deepEqual(
        compileTemplate({
            source: descriptor.template.content,
            filename,
            id: filename,
            compilerOptions: { bindingMetadata: script.bindings },
        }).errors,
        [],
    );
});
