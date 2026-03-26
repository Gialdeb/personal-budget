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

test('tracked item form exposes a dedicated slug field and validation error output', () => {
    assert.match(source, /trackedItems\.form\.labels\.slug/);
    assert.match(source, /trackedItems\.form\.placeholders\.slug/);
    assert.match(source, /form\.errors\.slug/);
    assert.match(source, /slugDirty = true/);
    assert.match(source, /function slugify/);
});
