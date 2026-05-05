export const PRIVACY_MODE_STORAGE_KEY = 'soamco-budget:privacy-mode';

export function readPrivacyMode() {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.localStorage.getItem(PRIVACY_MODE_STORAGE_KEY) === '1';
}

export function persistPrivacyMode(isEnabled) {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        PRIVACY_MODE_STORAGE_KEY,
        isEnabled ? '1' : '0',
    );
}
