/** Calculate a template percentage on the actual amount, in whole cents. */
export function transactionTemplateSplit(amount, basisPoints) {
    const normalized = String(amount).replace(',', '.');

    if (
        !/^\d+(?:\.\d{0,2})?$/.test(normalized) ||
        !Number.isInteger(basisPoints) ||
        basisPoints < 0 ||
        basisPoints > 10000
    ) {
        return null;
    }

    const total = Math.round(Number(normalized) * 100);

    if (!Number.isSafeInteger(total)) {
        return null;
    }

    const mine = Math.round((total * basisPoints) / 10000);

    return {
        myShare: (mine / 100).toFixed(2),
        counterpartyShare: ((total - mine) / 100).toFixed(2),
    };
}
