import { getCurrentFormatLocale } from '@/lib/locale';
import {
    formatMoneyValue,
    getMoneyCurrencyCatalog,
    resolveMoneyCurrencyCode,
    resolveMoneyCurrencyMeta,
} from '@/lib/money.js';

export type CurrencyFormatOptions = {
    currencyDisplay?: 'auto' | 'symbol' | 'code';
    preferCodeWhenAmbiguous?: boolean;
};

export function getCurrentBaseCurrencyCode(): string {
    if (typeof document !== 'undefined') {
        const currencyCode = document.documentElement.dataset.baseCurrencyCode;

        if (currencyCode) {
            return currencyCode;
        }
    }

    return 'EUR';
}

export function resolveCurrencyCode(currency?: string | null): string {
    return resolveMoneyCurrencyCode(
        typeof currency === 'string' && currency !== ''
            ? currency
            : getCurrentBaseCurrencyCode(),
    );
}

export function getCurrencyCatalog() {
    return getMoneyCurrencyCatalog();
}

export function resolveCurrencyMeta(currency?: string | null) {
    return resolveMoneyCurrencyMeta(resolveCurrencyCode(currency));
}

export function formatCurrencyLabel(currency?: string | null): string {
    const resolvedCurrencyCode = resolveMoneyCurrencyCode(currency);
    const currencyMeta = resolveCurrencyMeta(resolvedCurrencyCode);

    if (!currencyMeta) {
        return resolvedCurrencyCode;
    }

    return `${currencyMeta.code} — ${currencyMeta.name} (${currencyMeta.symbol})`;
}

export function formatCurrency(
    value: number,
    currency?: string | null,
    formatLocale?: string | null,
    options: CurrencyFormatOptions = {},
): string {
    return formatMoneyValue(
        value,
        resolveCurrencyCode(currency),
        formatLocale ?? getCurrentFormatLocale(),
        undefined,
        options,
    );
}
