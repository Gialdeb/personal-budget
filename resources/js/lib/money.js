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
 * @returns {{ group: string, decimal: string } | null}
 */
export function getCustomMoneySeparators() {
    if (typeof document === 'undefined') {
        return null;
    }

    const group = document.documentElement.dataset.numberThousandsSeparator;
    const decimal = document.documentElement.dataset.numberDecimalSeparator;

    if (
        typeof group !== 'string' ||
        typeof decimal !== 'string' ||
        group === '' ||
        decimal === '' ||
        group === decimal
    ) {
        return null;
    }

    return { group: group === 'space' ? ' ' : group, decimal };
}

/**
 * @returns {Record<string, {
 *   code: string,
 *   name: string,
 *   symbol: string,
 *   minor_unit: number,
 *   symbol_position: 'prefix' | 'suffix',
 * }>}
 */
export function getMoneyCurrencyCatalog() {
    if (
        typeof window !== 'undefined' &&
        window.__soamcoBudgetCurrencyCatalog &&
        typeof window.__soamcoBudgetCurrencyCatalog === 'object'
    ) {
        return window.__soamcoBudgetCurrencyCatalog;
    }

    return {};
}

/**
 * @param {string | null | undefined} currencyCode
 * @returns {string}
 */
export function resolveMoneyCurrencyCode(currencyCode) {
    return typeof currencyCode === 'string' && currencyCode !== ''
        ? currencyCode.toUpperCase()
        : DEFAULT_CURRENCY_CODE;
}

/**
 * @param {string | null | undefined} currencyCode
 * @returns {{
 *   code: string,
 *   name: string,
 *   symbol: string,
 *   minor_unit: number,
 *   symbol_position: 'prefix' | 'suffix',
 * } | null}
 */
export function resolveMoneyCurrencyMeta(currencyCode) {
    const resolvedCurrencyCode = resolveMoneyCurrencyCode(currencyCode);

    return getMoneyCurrencyCatalog()[resolvedCurrencyCode] ?? null;
}

/**
 * @param {string | null | undefined} currencyCode
 * @returns {boolean}
 */
export function isAmbiguousCurrencySymbol(currencyCode) {
    const resolvedCurrencyMeta = resolveMoneyCurrencyMeta(currencyCode);

    if (!resolvedCurrencyMeta || !resolvedCurrencyMeta.symbol) {
        return false;
    }

    const catalog = Object.values(getMoneyCurrencyCatalog());
    const collisions = catalog.filter(
        (currency) => currency.symbol === resolvedCurrencyMeta.symbol,
    );

    if (collisions.length > 1) {
        return true;
    }

    if (resolvedCurrencyMeta.symbol.length === 1) {
        return catalog.some(
            (currency) =>
                currency.code !== resolvedCurrencyMeta.code &&
                currency.symbol.endsWith(resolvedCurrencyMeta.symbol),
        );
    }

    return false;
}

/**
 * @param {number | null | undefined} precision
 * @param {string | null | undefined} currencyCode
 * @returns {number}
 */
export function resolveMoneyPrecision(
    precision,
    currencyCode = DEFAULT_CURRENCY_CODE,
) {
    return Number.isInteger(precision) && precision >= 0
        ? precision
        : (resolveMoneyCurrencyMeta(currencyCode)?.minor_unit ??
              DEFAULT_PRECISION);
}

/**
 * @param {string} formatLocale
 * @returns {{ decimal: string, group: string }}
 */
export function getMoneySeparators(formatLocale) {
    const customSeparators = getCustomMoneySeparators();

    if (customSeparators !== null) {
        return customSeparators;
    }

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
 * @param {number} value
 * @param {string} group
 * @param {string} decimal
 * @param {number} precision
 * @returns {string}
 */
function formatNumberWithCustomSeparators(value, group, decimal, precision) {
    const safeGroup = group === ' ' ? '\u00A0' : group;
    const sign = value < 0 ? '-' : '';
    const normalizedValue = Math.abs(value).toFixed(precision);
    const [integerPart = '0', decimalPart = ''] = normalizedValue.split('.');
    const groupedIntegerPart = integerPart.replace(
        /\B(?=(\d{3})+(?!\d))/g,
        safeGroup,
    );

    if (precision === 0) {
        return `${sign}${groupedIntegerPart}`;
    }

    return `${sign}${groupedIntegerPart}${decimal}${decimalPart}`;
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
    const resolvedCurrencyMeta = resolveMoneyCurrencyMeta(currencyCode);

    if (resolvedCurrencyMeta?.symbol) {
        return resolvedCurrencyMeta.symbol;
    }

    try {
        const parts = new Intl.NumberFormat(resolveMoneyLocale(formatLocale), {
            style: 'currency',
            currency: resolveMoneyCurrencyCode(currencyCode),
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
 * @param {string | null | undefined} currencyCode
 * @param {{
 *   currencyDisplay?: 'auto' | 'symbol' | 'code',
 *   preferCodeWhenAmbiguous?: boolean,
 * }} options
 * @returns {'code' | 'narrowSymbol'}
 */
export function resolveCurrencyDisplayMode(currencyCode, options = {}) {
    if (options.currencyDisplay === 'code') {
        return 'code';
    }

    if (options.currencyDisplay === 'symbol') {
        return 'narrowSymbol';
    }

    if (
        options.preferCodeWhenAmbiguous !== false &&
        isAmbiguousCurrencySymbol(currencyCode)
    ) {
        return 'code';
    }

    return 'narrowSymbol';
}

/**
 * @param {string | null | undefined} currencyCode
 * @param {string} formatLocale
 * @param {{
 *   currencyDisplay?: 'auto' | 'symbol' | 'code',
 *   preferCodeWhenAmbiguous?: boolean,
 * }} options
 * @returns {'prefix' | 'suffix'}
 */
export function resolveCurrencyPosition(
    currencyCode = DEFAULT_CURRENCY_CODE,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    options = {},
) {
    const resolvedCurrencyCode = resolveMoneyCurrencyCode(currencyCode);
    const resolvedPrecision = resolveMoneyPrecision(
        undefined,
        resolvedCurrencyCode,
    );

    try {
        const parts = new Intl.NumberFormat(resolveMoneyLocale(formatLocale), {
            style: 'currency',
            currency: resolvedCurrencyCode,
            currencyDisplay: resolveCurrencyDisplayMode(
                resolvedCurrencyCode,
                options,
            ),
            minimumFractionDigits: resolvedPrecision,
            maximumFractionDigits: resolvedPrecision,
        }).formatToParts(1);
        const currencyIndex = parts.findIndex(
            (part) => part.type === 'currency',
        );
        const integerIndex = parts.findIndex((part) => part.type === 'integer');

        if (currencyIndex !== -1 && integerIndex !== -1) {
            return currencyIndex < integerIndex ? 'prefix' : 'suffix';
        }
    } catch {
        // Fall through to metadata/default fallback.
    }

    return (
        resolveMoneyCurrencyMeta(resolvedCurrencyCode)?.symbol_position ??
        'prefix'
    );
}

/**
 * @param {string | null | undefined} currencyCode
 * @param {string} formatLocale
 * @param {{
 *   currencyDisplay?: 'auto' | 'symbol' | 'code',
 *   preferCodeWhenAmbiguous?: boolean,
 * }} options
 * @returns {string}
 */
export function resolveCurrencyIndicator(
    currencyCode = DEFAULT_CURRENCY_CODE,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    options = {},
) {
    const resolvedCurrencyCode = resolveMoneyCurrencyCode(currencyCode);

    if (resolveCurrencyDisplayMode(resolvedCurrencyCode, options) === 'code') {
        return resolvedCurrencyCode;
    }

    return resolveCurrencySymbol(resolvedCurrencyCode, formatLocale);
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} currencyCode
 * @param {string} formatLocale
 * @param {number} precision
 * @param {{
 *   currencyDisplay?: 'auto' | 'symbol' | 'code',
 *   preferCodeWhenAmbiguous?: boolean,
 * }} options
 * @returns {string}
 */
export function formatMoneyValue(
    value,
    currencyCode = DEFAULT_CURRENCY_CODE,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = undefined,
    options = {},
) {
    const numericValue = Number(value);
    const resolvedLocale = resolveMoneyLocale(formatLocale);
    const resolvedCurrencyCode = resolveMoneyCurrencyCode(currencyCode);
    const safeNumericValue = Number.isFinite(numericValue) ? numericValue : 0;
    const resolvedPrecision = resolveMoneyPrecision(
        precision,
        resolvedCurrencyCode,
    );
    const resolvedDisplayMode = resolveCurrencyDisplayMode(
        resolvedCurrencyCode,
        options,
    );
    const resolvedCurrencyMeta = resolveMoneyCurrencyMeta(resolvedCurrencyCode);
    const customSeparators = getCustomMoneySeparators();

    if (customSeparators !== null) {
        const formattedNumber = formatNumberWithCustomSeparators(
            safeNumericValue,
            customSeparators.group,
            customSeparators.decimal,
            resolvedPrecision,
        );
        const currencyIndicator = resolveCurrencyIndicator(
            resolvedCurrencyCode,
            resolvedLocale,
            options,
        );
        const currencyPosition =
            resolvedCurrencyMeta?.symbol_position ??
            resolveCurrencyPosition(
                resolvedCurrencyCode,
                resolvedLocale,
                options,
            );

        return currencyPosition === 'suffix'
            ? `${formattedNumber} ${currencyIndicator}`
            : `${currencyIndicator} ${formattedNumber}`;
    }

    try {
        const formatter = new Intl.NumberFormat(resolvedLocale, {
            style: 'currency',
            currency: resolvedCurrencyCode,
            currencyDisplay: resolvedDisplayMode,
            minimumFractionDigits: resolvedPrecision,
            maximumFractionDigits: resolvedPrecision,
            useGrouping: true,
        });

        if (
            resolvedDisplayMode === 'narrowSymbol' &&
            resolvedCurrencyMeta?.symbol
        ) {
            return formatter
                .formatToParts(safeNumericValue)
                .map((part) =>
                    part.type === 'currency'
                        ? resolvedCurrencyMeta.symbol
                        : part.value,
                )
                .join('');
        }

        return formatter.format(safeNumericValue);
    } catch {
        return `${new Intl.NumberFormat(resolvedLocale, {
            minimumFractionDigits: resolvedPrecision,
            maximumFractionDigits: resolvedPrecision,
            useGrouping: true,
        }).format(safeNumericValue)} ${resolveCurrencyIndicator(
            resolvedCurrencyCode,
            resolvedLocale,
            options,
        )}`;
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

    const resolvedPrecision = resolveMoneyPrecision(
        options.precision,
        options.currencyCode,
    );

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
 * @param {string} currencyCode
 * @returns {string}
 */
export function normalizeMoneyValue(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = undefined,
    currencyCode = DEFAULT_CURRENCY_CODE,
) {
    const resolvedPrecision = resolveMoneyPrecision(precision, currencyCode);
    const parsed = parseMoneyNumber(
        value,
        formatLocale,
        resolvedPrecision,
        currencyCode,
    );

    if (parsed === null) {
        return '';
    }

    return toStandardMoneyString(parsed, resolvedPrecision);
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} formatLocale
 * @param {number} precision
 * @param {string} currencyCode
 * @returns {string}
 */
export function parseMoneyInput(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = undefined,
    currencyCode = DEFAULT_CURRENCY_CODE,
) {
    return normalizeMoneyValue(value, formatLocale, precision, currencyCode);
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} formatLocale
 * @param {number} precision
 * @param {string} currencyCode
 * @returns {string}
 */
export function formatMoneyDisplay(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = undefined,
    currencyCode = DEFAULT_CURRENCY_CODE,
) {
    const resolvedPrecision = resolveMoneyPrecision(precision, currencyCode);
    const normalized = normalizeMoneyValue(
        value,
        formatLocale,
        precision,
        currencyCode,
    );

    if (normalized === '') {
        return '';
    }

    const customSeparators = getCustomMoneySeparators();

    if (customSeparators !== null) {
        return formatNumberWithCustomSeparators(
            Number(normalized),
            customSeparators.group,
            customSeparators.decimal,
            resolvedPrecision,
        );
    }

    return new Intl.NumberFormat(resolveMoneyLocale(formatLocale), {
        minimumFractionDigits: resolvedPrecision,
        maximumFractionDigits: resolvedPrecision,
        useGrouping: true,
    }).format(Number(normalized));
}

/**
 * @param {string | number | null | undefined} value
 * @param {string} formatLocale
 * @param {number} precision
 * @param {string} currencyCode
 * @returns {string}
 */
export function formatMoneyEditable(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = undefined,
    currencyCode = DEFAULT_CURRENCY_CODE,
) {
    const normalized = normalizeMoneyValue(
        value,
        formatLocale,
        precision,
        currencyCode,
    );

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
 * @param {string} currencyCode
 * @returns {string}
 */
export function formatMoneyDraft(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = undefined,
    currencyCode = DEFAULT_CURRENCY_CODE,
) {
    const resolvedPrecision = resolveMoneyPrecision(precision, currencyCode);
    const sanitized = sanitizeMoneyInput(value);

    if (sanitized === '') {
        return '';
    }

    if (resolvedPrecision === 0) {
        const separators = [...sanitized.matchAll(/[.,]/g)].map(
            (match) => match.index ?? 0,
        );

        if (separators.length === 0) {
            return sanitized;
        }

        const lastSeparatorIndex = separators[separators.length - 1] ?? -1;
        const digitsAfterSeparator = sanitized.length - lastSeparatorIndex - 1;

        if (digitsAfterSeparator === 3) {
            return sanitized.replace(/[.,]/g, '');
        }

        return sanitized.slice(0, lastSeparatorIndex).replace(/[.,]/g, '');
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
 * @param {string} currencyCode
 * @returns {number | null}
 */
export function parseMoneyNumber(
    value,
    formatLocale = DEFAULT_FORMAT_LOCALE,
    precision = undefined,
    currencyCode = DEFAULT_CURRENCY_CODE,
) {
    resolveMoneyLocale(formatLocale);
    const sanitized = sanitizeMoneyInput(value);
    const resolvedPrecision = resolveMoneyPrecision(precision, currencyCode);

    if (sanitized === '') {
        return null;
    }

    if (resolvedPrecision === 0) {
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
        const normalized =
            digitsAfterSeparator === 3
                ? sanitized.replace(/[.,]/g, '')
                : sanitized.slice(0, lastSeparatorIndex).replace(/[.,]/g, '');
        const numericValue = Number.parseFloat(normalized);

        return Number.isFinite(numericValue)
            ? roundMoneyNumber(Math.abs(numericValue), resolvedPrecision)
            : null;
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
