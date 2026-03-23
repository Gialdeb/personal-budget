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

export function getCurrentFormatLocale(): string {
    if (typeof document !== 'undefined') {
        const formatLocale = document.documentElement.dataset.formatLocale;

        if (formatLocale) {
            return formatLocale;
        }
    }

    return getCurrentIntlLocale();
}
