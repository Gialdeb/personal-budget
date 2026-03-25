const DEFAULT_FORMAT_LOCALE = 'it-IT';
const DEFAULT_CURRENCY_CODE = 'EUR';
const DEFAULT_PRECISION = 2;
const MONEY_CONTROL_KEYS = new Set([
    'Backspace',
    'Delete',
    'Tab',
    'Enter',
    'Escape',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'ArrowDown',
    'Home',
    'End',
]);

/**
 * @param {string | null | undefined} formatLocale
 * @returns {string}
 */
export function resolveMoneyLocale(formatLocale) {
    return typeof formatLocale === 'string' && formatLocale !== ''
        ? formatLocale
        : DEFAULT_FORMAT_LOCALE;
}

/**
 * @param {number | null | undefined} precision
 * @returns {number}
 */
export function resolveMoneyPrecision(precision) {
    return Number.isInteger(precision) && precision >= 0
        ? precision
        : DEFAULT_PRECISION;
}

/**
 * @param {string} formatLocale
 * @returns {{ decimal: string, group: string }}
 */
export function getMoneySeparators(formatLocale) {
    const resolvedLocale = resolveMoneyLocale(formatLocale);
    const parts = new Intl.NumberFormat(resolvedLocale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        useGrouping: true,
    }).formatToParts(12345.67);

    return {
        decimal: parts.find((part) => part.type === 'decimal')?.value ?? ',',
        group: parts.find((part) => part.type === 'group')?.value ?? '.',
    };
}

/**
 * @param {string} currencyCode
 * @param {string} formatLocale
 * @returns {string}
 */
export function resolveCurrencySymbol(
    currencyCode = DEFAULT_CURRENCY_CODE,
    formatLocale = DEFAULT_FORMAT_LOCALE,
) {
    try {
        const parts = new Intl.NumberFormat(resolveMoneyLocale(formatLocale), {
            style: 'currency',
            currency: currencyCode,
            currencyDisplay: 'narrowSymbol',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).formatToParts(1);

        return (
            parts.find((part) => part.type === 'currency')?.value ??
            currencyCode
        );
    } catch {
        return currencyCode;
    }
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} currencyCode
 * @param {string} formatLocale
 * @param {number} precision
 * @returns {string}
 */
export function formatMoneyValue(
    value,
    currencyCode = DEFAULT_CURRENCY_CODE,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = DEFAULT_PRECISION,
) {
    const numericValue = Number(value);
    const resolvedLocale = resolveMoneyLocale(formatLocale);
    const resolvedCurrencyCode =
        typeof currencyCode === 'string' && currencyCode !== ''
            ? currencyCode
            : DEFAULT_CURRENCY_CODE;
    const safeNumericValue = Number.isFinite(numericValue) ? numericValue : 0;

    try {
        return new Intl.NumberFormat(resolvedLocale, {
            style: 'currency',
            currency: resolvedCurrencyCode,
            minimumFractionDigits: resolveMoneyPrecision(precision),
            maximumFractionDigits: resolveMoneyPrecision(precision),
            useGrouping: true,
        }).format(safeNumericValue);
    } catch {
        return `${new Intl.NumberFormat(resolvedLocale, {
            minimumFractionDigits: resolveMoneyPrecision(precision),
            maximumFractionDigits: resolveMoneyPrecision(precision),
            useGrouping: true,
        }).format(safeNumericValue)} ${resolvedCurrencyCode}`;
    }
}

/**
 * @param {string | number | null | undefined} value
 * @returns {string}
 */
export function sanitizeMoneyInput(value) {
    return String(value ?? '')
        .replace(/-/g, '')
        .replace(/[^\d.,]/g, '')
        .replace(/\s+/g, '');
}

/**
 * @param {string} key
 * @param {{
 *   formatLocale?: string,
 *   precision?: number,
 *   currentValue?: string,
 *   selectionStart?: number | null,
 *   selectionEnd?: number | null,
 *   ctrlKey?: boolean,
 *   metaKey?: boolean,
 * }} options
 * @returns {boolean}
 */
export function shouldAllowMoneyKey(key, options = {}) {
    if (options.ctrlKey === true || options.metaKey === true) {
        return true;
    }

    if (MONEY_CONTROL_KEYS.has(key)) {
        return true;
    }

    if (/^\d$/.test(key)) {
        return true;
    }

    const resolvedPrecision = resolveMoneyPrecision(options.precision);

    if (resolvedPrecision === 0) {
        return false;
    }

    const { decimal } = getMoneySeparators(options.formatLocale);

    if (key !== decimal) {
        return false;
    }

    const currentValue = String(options.currentValue ?? '');
    const currentDecimalIndex = currentValue.indexOf(decimal);
    const selectionStart = options.selectionStart ?? currentValue.length;
    const selectionEnd = options.selectionEnd ?? selectionStart;

    if (currentDecimalIndex === -1) {
        return true;
    }

    return (
        selectionStart <= currentDecimalIndex &&
        selectionEnd > currentDecimalIndex
    );
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} formatLocale
 * @param {number} precision
 * @returns {string}
 */
export function normalizeMoneyValue(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = DEFAULT_PRECISION,
) {
    const resolvedPrecision = resolveMoneyPrecision(precision);
    const parsed = parseMoneyNumber(value, formatLocale, resolvedPrecision);

    if (parsed === null) {
        return '';
    }

    return toStandardMoneyString(parsed, resolvedPrecision);
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} formatLocale
 * @param {number} precision
 * @returns {string}
 */
export function parseMoneyInput(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = DEFAULT_PRECISION,
) {
    return normalizeMoneyValue(value, formatLocale, precision);
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} formatLocale
 * @param {number} precision
 * @returns {string}
 */
export function formatMoneyDisplay(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = DEFAULT_PRECISION,
) {
    const normalized = normalizeMoneyValue(value, formatLocale, precision);

    if (normalized === '') {
        return '';
    }

    return new Intl.NumberFormat(resolveMoneyLocale(formatLocale), {
        minimumFractionDigits: resolveMoneyPrecision(precision),
        maximumFractionDigits: resolveMoneyPrecision(precision),
        useGrouping: true,
    }).format(Number(normalized));
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} formatLocale
 * @param {number} precision
 * @returns {string}
 */
export function formatMoneyEditable(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = DEFAULT_PRECISION,
) {
    const normalized = normalizeMoneyValue(value, formatLocale, precision);

    if (normalized === '') {
        return '';
    }

    const { decimal } = getMoneySeparators(formatLocale);
    const [integerPart, decimalPart] = normalized.split('.');

    return decimalPart === undefined || decimalPart === ''
        ? integerPart
        : `${integerPart}${decimal}${decimalPart}`;
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} formatLocale
 * @param {number} precision
 * @returns {string}
 */
export function formatMoneyDraft(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = DEFAULT_PRECISION,
) {
    const resolvedPrecision = resolveMoneyPrecision(precision);
    const sanitized = sanitizeMoneyInput(value);

    if (sanitized === '') {
        return '';
    }

    const { decimal } = getMoneySeparators(formatLocale);
    const lastCommaIndex = sanitized.lastIndexOf(',');
    const lastDotIndex = sanitized.lastIndexOf('.');
    const lastSeparatorIndex = Math.max(lastCommaIndex, lastDotIndex);
    const hasAnySeparator = lastSeparatorIndex !== -1;
    const hasTrailingSeparator =
        hasAnySeparator && lastSeparatorIndex === sanitized.length - 1;
    const decimalsLength = hasAnySeparator
        ? sanitized.length - lastSeparatorIndex - 1
        : 0;
    const separatorsCount = (sanitized.match(/[.,]/g) ?? []).length;
    const shouldTreatAsDecimal =
        hasAnySeparator &&
        (hasTrailingSeparator ||
            decimalsLength <= resolvedPrecision ||
            (separatorsCount > 1 && decimalsLength <= resolvedPrecision));

    if (!shouldTreatAsDecimal) {
        return sanitized.replace(/[.,]/g, '');
    }

    const integerDigits = sanitized
        .slice(0, lastSeparatorIndex)
        .replace(/[.,]/g, '');
    const decimalDigits = sanitized
        .slice(lastSeparatorIndex + 1)
        .replace(/[.,]/g, '')
        .slice(0, resolvedPrecision);
    const safeInteger = integerDigits === '' ? '0' : integerDigits;

    if (hasTrailingSeparator && decimalDigits === '') {
        return `${safeInteger}${decimal}`;
    }

    return decimalDigits === ''
        ? safeInteger
        : `${safeInteger}${decimal}${decimalDigits}`;
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} formatLocale
 * @param {number} precision
 * @returns {number | null}
 */
export function parseMoneyNumber(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = DEFAULT_PRECISION,
) {
    resolveMoneyLocale(formatLocale);
    const sanitized = sanitizeMoneyInput(value);
    const resolvedPrecision = resolveMoneyPrecision(precision);

    if (sanitized === '') {
        return null;
    }

    const separators = [...sanitized.matchAll(/[.,]/g)].map(
        (match) => match.index ?? 0,
    );

    if (separators.length === 0) {
        const numericValue = Number.parseFloat(sanitized);

        return Number.isFinite(numericValue)
            ? roundMoneyNumber(Math.abs(numericValue), resolvedPrecision)
            : null;
    }

    const lastSeparatorIndex = separators[separators.length - 1] ?? -1;
    const digitsAfterSeparator = sanitized.length - lastSeparatorIndex - 1;
    const singleSeparator = separators.length === 1;

    if (
        singleSeparator &&
        (digitsAfterSeparator > resolvedPrecision || digitsAfterSeparator === 0)
    ) {
        const thousandsValue = Number.parseFloat(
            sanitized.replace(/[.,]/g, ''),
        );

        return Number.isFinite(thousandsValue)
            ? roundMoneyNumber(Math.abs(thousandsValue), resolvedPrecision)
            : null;
    }

    const integerPart = sanitized
        .slice(0, lastSeparatorIndex)
        .replace(/[.,]/g, '');
    const decimalPart = sanitized
        .slice(lastSeparatorIndex + 1)
        .replace(/[.,]/g, '')
        .slice(0, resolvedPrecision);
    const normalized =
        decimalPart === '' ? integerPart : `${integerPart}.${decimalPart}`;
    const numericValue = Number.parseFloat(normalized);

    return Number.isFinite(numericValue)
        ? roundMoneyNumber(Math.abs(numericValue), resolvedPrecision)
        : null;
}

/**
 * @param {number} value
 * @param {number} precision
 * @returns {number}
 */
export function roundMoneyNumber(value, precision = DEFAULT_PRECISION) {
    const multiplier = 10 ** resolveMoneyPrecision(precision);

    return Math.round(Math.abs(value) * multiplier) / multiplier;
}

/**
 * @param {number} value
 * @param {number} precision
 * @returns {string}
 */
export function toStandardMoneyString(value, precision = DEFAULT_PRECISION) {
    const resolvedPrecision = resolveMoneyPrecision(precision);
    const rounded = roundMoneyNumber(value, resolvedPrecision);

    if (resolvedPrecision === 0) {
        return String(Math.trunc(rounded));
    }

    return rounded.toFixed(resolvedPrecision).replace(/\.?0+$/, '');
}
