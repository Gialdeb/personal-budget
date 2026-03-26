import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(
    new URL(
        '../../resources/js/components/categories/CategoryFormSheet.vue',
        import.meta.url,
    ),
    'utf8',
);

const treeListSource = readFileSync(
    new URL(
        '../../resources/js/components/categories/CategoryTreeList.vue',
        import.meta.url,
    ),
    'utf8',
);

test('system categories keep name and slug disabled while active stays visible but locked', () => {
    assert.match(source, /const isSystemCategory = computed/);
    assert.match(source, /v-model="form\.name"[\s\S]*:disabled="isSystemCategory"/);
    assert.match(source, /:model-value="form\.slug"[\s\S]*:disabled="isSystemCategory"/);
    assert.match(source, /:checked="form\.is_active"[\s\S]*:disabled="isSystemCategory"/);
    assert.match(source, /categories\.form\.help\.activeFoundation/);
});

test('system categories cannot be toggled or deleted from the tree quick actions', () => {
    assert.match(treeListSource, /:disabled="item\.is_system"/);
    assert.match(treeListSource, /:disabled="item\.is_system \|\| !item\.is_deletable"/);
});
