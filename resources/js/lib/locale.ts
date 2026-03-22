export function getCurrentLocale(): string {
    if (typeof document !== 'undefined' && document.documentElement.lang) {
        return document.documentElement.lang;
    }

    if (typeof navigator !== 'undefined' && navigator.language) {
        return navigator.language;
    }

    return 'en';
}

export function getCurrentIntlLocale(): string {
    const locale = getCurrentLocale();

    return locale.includes('-') ? locale : `${locale}-${locale.toUpperCase()}`;
}
