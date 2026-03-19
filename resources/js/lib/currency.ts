export function formatCurrency(
    value: number,
    currency: string = 'EUR',
): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}
