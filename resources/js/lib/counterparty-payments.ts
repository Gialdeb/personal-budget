export type Claim = {
    uuid: string;
    economic_date: string;
    direction: string;
    remaining_minor: string;
    domain: string;
};
export function parseMinor(value: string, precision: number): bigint | null {
    const normalized = value.trim().replace(',', '.');

    if (!/^\d+(\.\d+)?$/.test(normalized)) {
        return null;
    }

    const [whole, fraction = ''] = normalized.split('.');

    if (/[^0]/.test(fraction.slice(precision))) {
        return null;
    }

    return (
        BigInt(whole) * 10n ** BigInt(precision) +
        BigInt(fraction.slice(0, precision).padEnd(precision, '0') || '0')
    );
}
export function decimalMinor(
    value: bigint | string,
    precision: number,
): string {
    const minor = BigInt(value);
    const sign = minor < 0n ? '-' : '';
    const digits = (minor < 0n ? -minor : minor)
        .toString()
        .padStart(precision + 1, '0');

    return (
        sign +
        (precision
            ? `${digits.slice(0, -precision)}.${digits.slice(-precision)}`
            : digits)
    );
}
export function fifoAllocation(
    claims: Claim[],
    amount: bigint,
): { obligation_uuid: string; minor: bigint }[] {
    let remaining = amount;

    return [...claims]
        .sort(
            (a, b) =>
                a.economic_date.localeCompare(b.economic_date) ||
                a.uuid.localeCompare(b.uuid),
        )
        .flatMap((claim) => {
            const capacity = BigInt(claim.remaining_minor);
            const minor = remaining < capacity ? remaining : capacity;
            remaining -= minor;

            return minor > 0n ? [{ obligation_uuid: claim.uuid, minor }] : [];
        });
}
