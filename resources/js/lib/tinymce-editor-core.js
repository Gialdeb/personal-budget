export const TINYMCE_EDITOR_PLUGINS = ['autolink', 'link', 'lists', 'image'];
export const TINYMCE_EDITOR_TOOLBAR =
    'undo redo | blocks | bold italic | bullist numlist | link unlink | image | removeformat';
export const TINYMCE_EDITOR_BLOCK_FORMATS =
    'Paragraph=p; Heading 2=h2; Heading 3=h3';

const MANAGED_IMAGE_PREFIX = 'editorial/rich-content/';
const STORAGE_IMAGE_PREFIX = '/storage/editorial/rich-content/';
const EDITORIAL_ASSET_PREFIX = '/editorial-assets';

export function resolveManagedImagePath(value) {
    if (typeof value !== 'string' || value.trim().length === 0) {
        return null;
    }

    const normalizedValue = value.trim();

    if (normalizedValue.startsWith(MANAGED_IMAGE_PREFIX)) {
        return normalizedValue;
    }

    try {
        const url = new URL(normalizedValue, 'https://soamco-budget.local');

        if (url.pathname === EDITORIAL_ASSET_PREFIX) {
            const queryPath = url.searchParams.get('path');

            if (queryPath?.startsWith(MANAGED_IMAGE_PREFIX)) {
                return queryPath;
            }
        }

        if (url.pathname.startsWith(STORAGE_IMAGE_PREFIX)) {
            return url.pathname.replace('/storage/', '');
        }
    } catch {
        return null;
    }

    return null;
}

export function extractManagedImagePaths(html) {
    const paths = new Set();

    if (typeof html !== 'string' || html.length === 0) {
        return paths;
    }

    const imageTags = html.match(/<img\b[^>]*>/gi) ?? [];

    imageTags.forEach((tag) => {
        const dataEditorPath = tag.match(
            /\bdata-editor-path=(["'])(.*?)\1/i,
        )?.[2];

        const managedPath =
            resolveManagedImagePath(dataEditorPath) ??
            resolveManagedImagePath(tag.match(/\bsrc=(["'])(.*?)\1/i)?.[2]);

        if (managedPath) {
            paths.add(managedPath);
        }
    });

    return paths;
}
