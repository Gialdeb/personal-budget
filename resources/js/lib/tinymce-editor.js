import { destroy, store } from '@/routes/admin/rich-content-assets';
import { resolveManagedImagePath } from './tinymce-editor-core';

export * from './tinymce-editor-core';

export function readCsrfToken() {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export async function uploadManagedEditorImage(file) {
    const formData = new FormData();
    formData.append('image', file);

    const response = await fetch(store.url(), {
        method: 'POST',
        body: formData,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': readCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Image upload failed');
    }

    return response.json();
}

export async function deleteManagedEditorImage(path) {
    const managedPath = resolveManagedImagePath(path);

    if (!managedPath) {
        return false;
    }

    const response = await fetch(destroy.url(), {
        method: 'DELETE',
        body: JSON.stringify({ path: managedPath }),
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': readCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    return response.ok;
}
