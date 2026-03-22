import { createI18n } from 'vue-i18n';
import { messages } from '@/i18n/messages';
import type { LocaleSharedData } from '@/types';

type SharedPageProps = Record<string, unknown> & {
    locale?: LocaleSharedData;
};

export const availableMessageLocales = Object.keys(messages);

export function resolveSharedLocale(
    pageProps: SharedPageProps | undefined,
): LocaleSharedData {
    return {
        current: normalizeLocale(pageProps?.locale?.current),
        fallback: normalizeLocale(pageProps?.locale?.fallback ?? 'en'),
        available: pageProps?.locale?.available ?? [],
    };
}

export function normalizeLocale(locale: string | undefined): string {
    if (locale && availableMessageLocales.includes(locale)) {
        return locale;
    }

    return 'en';
}

export function createAppI18n(pageProps: SharedPageProps | undefined) {
    const sharedLocale = resolveSharedLocale(pageProps);

    return createI18n({
        legacy: false,
        locale: sharedLocale.current,
        fallbackLocale: sharedLocale.fallback,
        messages,
    });
}
