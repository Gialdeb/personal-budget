import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse, compileScript, compileTemplate, registerTS } from '@vue/compiler-sfc';
import ts from 'typescript';

registerTS(() => ts);
const recurringPath = new URL('../../resources/js/components/recurring/RecurringEntryFormSheet.vue', import.meta.url);
const sharedPath = new URL('../../resources/js/components/shared-expenses/SharedExpenseFields.vue', import.meta.url);

for (const path of [recurringPath, sharedPath]) {
    test(`${path.pathname.split('/').pop()} option cards compile`, () => {
        const source = readFileSync(path, 'utf8');
        const { descriptor, errors } = parse(source, { filename: path.pathname });
        assert.deepEqual(errors, []);
        const script = compileScript(descriptor, { id: path.pathname });
        const template = compileTemplate({ source: descriptor.template.content, filename: path.pathname, id: path.pathname, compilerOptions: { bindingMetadata: script.bindings } });
        assert.deepEqual(template.errors, []);
    });
}

test('recurring option cards have decorative icons and preserve existing models', () => {
    const source = readFileSync(recurringPath, 'utf8');
    assert.match(source, /<Gift[\s\S]*?aria-hidden="true"/);
    assert.match(source, /<SlidersHorizontal[\s\S]*?aria-hidden="true"/);
    assert.match(source, /v-model="form.trial_enabled"/);
    assert.match(source, /:model-value="form.is_amount_variable"/);
    assert.match(source, /<SharedExpenseFields\s+decorated/);
    assert.ok(source.includes('[&_button:not([role=checkbox])]:min-h-11'));
    assert.ok(source.includes('[&_input:not([type=checkbox])]:min-h-11'));
    const shared = readFileSync(sharedPath, 'utf8');
    assert.match(shared, /decorated: false/);
    assert.match(shared, /<UsersRound[\s\S]*?aria-hidden="true"/);
    assert.match(shared, /v-model="enabled"/);
});
