import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(
    new URL(
        '../../resources/js/components/tracked-items/TrackedItemFormSheet.vue',
        import.meta.url,
    ),
    'utf8',
);

const treeSource = readFileSync(
    new URL(
        '../../resources/js/components/tracked-items/TrackedItemsTreeList.vue',
        import.meta.url,
    ),
    'utf8',
);

const pageSource = readFileSync(
    new URL('../../resources/js/pages/settings/TrackedItems.vue', import.meta.url),
    'utf8',
);

test('tracked item form exposes a dedicated slug field and validation error output', () => {
    assert.match(source, /trackedItems\.form\.labels\.slug/);
    assert.match(source, /trackedItems\.form\.placeholders\.slug/);
    assert.match(source, /form\.errors\.slug/);
    assert.match(source, /slugDirty = true/);
    assert.match(source, /function slugify/);
});

test('tracked items settings de-emphasize hierarchy and keep category compatibility central', () => {
    assert.match(source, /trackedItems\.form\.labels\.compatibleCategories/);
    assert.match(source, /trackedItems\.form\.help\.compatibleCategories/);
    assert.doesNotMatch(source, /trackedItems\.form\.labels\.parent/);
    assert.doesNotMatch(source, /trackedItems\.form\.help\.parent/);
    assert.doesNotMatch(source, /trackedItems\.form\.placeholders\.noParent/);
    assert.doesNotMatch(treeSource, /trackedItems\.tree\.actions\.createChild/);
    assert.doesNotMatch(treeSource, /trackedItems\.tree\.status\.leaf/);
    assert.doesNotMatch(treeSource, /trackedItems\.tree\.labels\.parent/);
    assert.match(treeSource, /trackedItems\.tree\.labels\.categories/);
    assert.match(treeSource, /compatible_category_uuids/);
    assert.match(treeSource, /categoryLabelsByUuid/);
    assert.doesNotMatch(pageSource, /openCreateChild/);
    assert.doesNotMatch(pageSource, /structureStatus/);
    assert.doesNotMatch(pageSource, /trackedItems\.filters\.roots/);
    assert.doesNotMatch(pageSource, /trackedItems\.filters\.leavesOnly/);
    assert.match(pageSource, /trackedItems\.tree\.badges\.categoryDriven/);
    assert.match(pageSource, /trackedItems\.tree\.badges\.flatFirst/);
    assert.match(pageSource, /visibleFlatTrackedItems/);
});

test('tracked items settings expose a controlled personal to shared bridge', () => {
    assert.match(pageSource, /sharedBridge\?\.accounts|initialSharedBridgeAccounts/);
    assert.match(pageSource, /materializeTrackedItemToSharedAccount/);
    assert.match(pageSource, /trackedItems\.sharedBridge\.title/);
    assert.match(pageSource, /trackedItems\.sharedBridge\.action/);
    assert.match(pageSource, /settings\/tracked-items\/shared\/\$\{selectedBridgeAccountUuid\.value}\/materialize-personal/);
});
