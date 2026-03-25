export const HEADER_INFO_EXPANDED_STORAGE_KEY = 'app.header.infoExpanded';

export function readHeaderInfoExpanded() {
    if (typeof window === 'undefined') {
        return true;
    }

    const storedValue = window.localStorage.getItem(
        HEADER_INFO_EXPANDED_STORAGE_KEY,
    );

    if (storedValue === null) {
        return true;
    }

    return storedValue === 'true';
}

export function persistHeaderInfoExpanded(value) {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        HEADER_INFO_EXPANDED_STORAGE_KEY,
        String(value),
    );
}
