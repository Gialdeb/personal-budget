import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const indexSource = readFileSync(
    new URL(
        '../../resources/js/pages/admin/Changelog/Index.vue',
        import.meta.url,
    ),
    'utf8',
);
const editSource = readFileSync(
    new URL(
        '../../resources/js/pages/admin/Changelog/Edit.vue',
        import.meta.url,
    ),
    'utf8',
);
const editorSource = readFileSync(
    new URL(
        '../../resources/js/components/admin/changelog/SimpleRichTextEditor.vue',
        import.meta.url,
    ),
    'utf8',
);
const layoutSource = readFileSync(
    new URL('../../resources/js/layouts/admin/Layout.vue', import.meta.url),
    'utf8',
);

test('admin changelog index exposes release list and create action', () => {
    assert.match(indexSource, /Nuova release/);
    assert.match(indexSource, /versionSuggestions/);
    assert.match(indexSource, /editChangelogRelease/);
});

test('admin changelog edit page exposes multilingual fields sections and items', () => {
    assert.match(editSource, /supportedLocales/);
    assert.match(editSource, /version_label/);
    assert.match(editSource, /sections/);
    assert.match(editSource, /items/);
    assert.match(editSource, /SimpleRichTextEditor/);
});

test('simple rich text editor exposes basic WYSIWYG actions', () => {
    assert.match(editorSource, /contenteditable="true"/);
    assert.match(editorSource, /document\.execCommand/);
    assert.match(editorSource, /createLink/);
    assert.match(editorSource, /insertOrderedList/);
    assert.match(editorSource, /insertUnorderedList/);
});

test('admin layout exposes changelog navigation entry', () => {
    assert.match(layoutSource, /admin\.sections\.changelog/);
    assert.match(layoutSource, /changelogIndex/);
});
