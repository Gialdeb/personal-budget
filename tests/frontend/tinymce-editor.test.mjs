import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    extractManagedImagePaths,
    resolveManagedImagePath,
    TINYMCE_EDITOR_BLOCK_FORMATS,
    TINYMCE_EDITOR_PLUGINS,
    TINYMCE_EDITOR_TOOLBAR,
} from '../../resources/js/lib/tinymce-editor-core.js';

const editorSource = readFileSync(
    new URL(
        '../../resources/js/components/admin/editorial/RichContentEditor.vue',
        import.meta.url,
    ),
    'utf8',
);

const managedImagePath = ['editorial', 'rich-content', '2026', '04', 'example.webp'].join('/');
const managedAlphaImagePath = ['editorial', 'rich-content', '2026', '04', 'alpha.webp'].join('/');
const managedBetaImagePath = ['editorial', 'rich-content', '2026', '04', 'beta.webp'].join('/');

test('rich content editor wraps TinyMCE self-hosted for reuse across admin flows', () => {
    assert.match(editorSource, /@tinymce\/tinymce-vue/);
    assert.match(editorSource, /license-key="gpl"/);
    assert.match(editorSource, /images_upload_handler/);
    assert.match(editorSource, /file_picker_callback/);
    assert.match(editorSource, /skin:\s*false/);
    assert.match(editorSource, /content_css:\s*false/);
    assert.match(editorSource, /data-editor-provider="tinymce"/);
    assert.match(editorSource, /editor\.on\('init'/);
    assert.match(editorSource, /Caricamento editor/);
    assert.doesNotMatch(editorSource, /contenteditable="true"/);
});

test('tinymce config exposes the editorial toolbar baseline', () => {
    assert.deepEqual(TINYMCE_EDITOR_PLUGINS, [
        'autolink',
        'link',
        'lists',
        'image',
    ]);
    assert.equal(
        TINYMCE_EDITOR_TOOLBAR,
        'undo redo | blocks | bold italic | bullist numlist | link unlink | image | removeformat',
    );
    assert.equal(
        TINYMCE_EDITOR_BLOCK_FORMATS,
        'Paragraph=p; Heading 2=h2; Heading 3=h3',
    );
});

test('managed image path resolver supports direct paths and storage urls', () => {
    assert.equal(
        resolveManagedImagePath(managedImagePath),
        managedImagePath,
    );
    assert.equal(
        resolveManagedImagePath(
            `/storage/${managedImagePath}`,
        ),
        managedImagePath,
    );
    assert.equal(
        resolveManagedImagePath(
            `https://app.example.com/storage/${managedImagePath}`,
        ),
        managedImagePath,
    );
    assert.equal(
        resolveManagedImagePath(
            `/editorial-assets?path=${managedImagePath}`,
        ),
        managedImagePath,
    );
    assert.equal(resolveManagedImagePath('/storage/avatars/user.png'), null);
});

test('managed image extraction tracks images from existing html content', () => {
    const html = `
        <p>Intro</p>
        <p><img src="/storage/${managedAlphaImagePath}" alt=""></p>
        <p><img src="https://app.example.com/storage/${managedBetaImagePath}" data-editor-path="${managedBetaImagePath}" alt=""></p>
    `;

    assert.deepEqual([...extractManagedImagePaths(html)], [
        managedAlphaImagePath,
        managedBetaImagePath,
    ]);
});
