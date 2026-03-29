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
    assert.match(source, /const isRootSystemCategory = computed/);
    assert.match(source, /v-model="form\.name"[\s\S]*:disabled="isSystemCategory"/);
    assert.match(source, /:model-value="form\.slug"[\s\S]*:disabled="isSystemCategory"/);
    assert.match(source, /:disabled="isRootSystemCategory"/);
    assert.match(source, /inheritsParentClassification \|\|[\s\S]*isRootSystemCategory/);
    assert.match(source, /:checked="form\.is_active"[\s\S]*:disabled="isSystemCategory"/);
    assert.match(source, /categories\.form\.help\.activeFoundation/);
});

test('system categories cannot be toggled or deleted from the tree quick actions', () => {
    assert.match(treeListSource, /:disabled="readOnly \|\| item\.is_system"/);
    assert.match(
        treeListSource,
        /:disabled="readOnly \|\| item\.is_system \|\| !item\.is_deletable"/,
    );
    assert.match(treeListSource, /v-if="canCreateChild\(item\)"/);
});

test('personal category form inherits direction and group from the selected parent and hides invalid parent depths', () => {
    assert.match(source, /const selectedParent = computed/);
    assert.match(source, /const inheritsParentClassification = computed/);
    assert.match(source, /const currentSubtreeHeight = computed/);
    assert.match(source, /return props\.parentOptions\.filter\(\(item\) => item\.depth <= 1\)/);
    assert.match(source, /item\.depth > maxParentDepth/);
    assert.match(source, /item\.direction_type !== props\.category\.direction_type/);
    assert.match(source, /form\.direction_type = parent\.direction_type/);
    assert.match(source, /form\.group_type = parent\.group_type/);
    assert.match(source, /categories\.form\.help\.inheritedDirection/);
    assert.match(source, /categories\.form\.help\.inheritedGroup/);
});

test('category tree shows personal or shared perimeter badges and shared account name', () => {
    assert.match(treeListSource, /categories\.tree\.badges\.personal/);
    assert.match(treeListSource, /categories\.tree\.badges\.shared/);
    assert.match(treeListSource, /item\.is_shared/);
    assert.match(treeListSource, /item\.account_name/);
    assert.match(treeListSource, /categories\.tree\.scopeAccount/);
});
