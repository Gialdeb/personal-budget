import { getCurrentFormatLocale } from '@/lib/locale';
import { formatMoneyValue } from '@/lib/money.js';

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
    return typeof currency === 'string' && currency !== ''
        ? currency
        : getCurrentBaseCurrencyCode();
}

export function formatCurrency(
    value: number,
    currency?: string | null,
    formatLocale?: string | null,
): string {
    return formatMoneyValue(
        value,
        resolveCurrencyCode(currency),
        formatLocale ?? getCurrentFormatLocale(),
    );
}
