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
        '../../resources/js/components/admin/editorial/RichContentEditor.vue',
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
    assert.match(editSource, /RichContentEditor/);
    assert.match(editSource, /changelog-summary-\$\{currentLocale}/);
    assert.match(editSource, /changelog-item-body-/);
    assert.doesNotMatch(editSource, /SimpleRichTextEditor/);
});

test('rich content editor exposes editorial actions and image upload hooks', () => {
    assert.match(editorSource, /@tinymce\/tinymce-vue/);
    assert.match(editorSource, /TINYMCE_EDITOR_TOOLBAR/);
    assert.match(editorSource, /images_upload_handler/);
    assert.match(editorSource, /file_picker_callback/);
});

test('admin layout exposes changelog navigation entry', () => {
    assert.match(layoutSource, /admin\.sections\.changelog/);
    assert.match(layoutSource, /changelogIndex/);
});
