import { getCurrentIntlLocale } from '@/lib/locale';

export function formatCurrency(
    value: number,
    currency: string = 'EUR',
): string {
    const numericValue = Number(value);
    const locale = getCurrentIntlLocale();

    if (!Number.isFinite(numericValue)) {
        return new Intl.NumberFormat(locale, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
            useGrouping: true,
        }).format(0);
    }

    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
            useGrouping: true,
        }).format(numericValue);
    } catch {
        return `${new Intl.NumberFormat(locale, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
            useGrouping: true,
        }).format(numericValue)} ${currency}`;
    }
}
