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

const categoriesMessagesSource = readFileSync(
    new URL('../../resources/js/i18n/messages/categories.ts', import.meta.url),
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
    assert.match(source, /item\.direction_type !== category\.direction_type/);
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

test('category UI copy distinguishes operational and organizational categories clearly', () => {
    assert.match(source, /categories\.form\.labels\.categoryType/);
    assert.match(source, /selectCategoryType\(true\)/);
    assert.match(source, /selectCategoryType\(false\)/);
    assert.match(source, /categories\.form\.typeOptions\.operationalTitle/);
    assert.match(source, /categories\.form\.typeOptions\.organizationalTitle/);
    assert.match(source, /categories\.form\.typeOptions\.operationalDescription/);
    assert.match(source, /categories\.form\.typeOptions\.organizationalDescription/);
    assert.match(source, /:aria-pressed="form\.is_selectable"/);
    assert.match(source, /:aria-pressed="!form\.is_selectable"/);
    assert.match(source, /categories\.form\.state\.operational/);
    assert.match(source, /categories\.form\.state\.container/);
    assert.match(source, /categories\.form\.help\.categoryType/);
    assert.match(source, /categories\.form\.labels\.availability/);
    assert.match(source, /categories\.form\.help\.availability/);
    assert.match(source, /categories\.form\.labels\.currentType/);
    assert.match(source, /categories\.form\.labels\.currentAvailability/);
    assert.match(source, /:checked="form\.is_active"/);
    assert.match(treeListSource, /categories\.tree\.status\.selectable/);
    assert.match(treeListSource, /categories\.tree\.status\.container/);
    assert.match(categoriesMessagesSource, /Categoria organizzativa/);
    assert.match(categoriesMessagesSource, /Categoria operativa/);
    assert.match(
        categoriesMessagesSource,
        /non può ricevere importi monetari e non compare nelle select operative/,
    );
    assert.match(
        categoriesMessagesSource,
        /Se abilitata, la categoria può essere scelta normalmente dove compatibile/,
    );
    assert.match(categoriesMessagesSource, /Disponibile nelle nuove selezioni/);
    assert.match(categoriesMessagesSource, /Nuove selezioni operative/);
    assert.match(categoriesMessagesSource, /Organizational category/);
    assert.match(categoriesMessagesSource, /Operational category/);
});
