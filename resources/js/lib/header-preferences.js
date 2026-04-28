export const HEADER_INFO_EXPANDED_STORAGE_KEY = 'app.header.infoExpanded';

function logHeaderPreferenceStorageFailure(error) {
    if (import.meta.env?.DEV) {
        console.debug('Header preference storage is unavailable.', error);
    }
}

export function readHeaderInfoExpanded() {
    if (typeof window === 'undefined') {
        return true;
    }

    let storedValue = null;

    try {
        storedValue = window.localStorage.getItem(
            HEADER_INFO_EXPANDED_STORAGE_KEY,
        );
    } catch (error) {
        logHeaderPreferenceStorageFailure(error);

        return true;
    }

    if (storedValue === null) {
        return true;
    }

    return storedValue === 'true';
}

export function persistHeaderInfoExpanded(value) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            HEADER_INFO_EXPANDED_STORAGE_KEY,
            value ? 'true' : 'false',
        );
    } catch (error) {
        logHeaderPreferenceStorageFailure(error);
    }
}
