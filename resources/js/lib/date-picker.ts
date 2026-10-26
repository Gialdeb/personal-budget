export function parseDatePickerValue(value: string): Date | null {
    const match =
        /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2})(?::\d{2})?)?$/.exec(value);

    if (!match) {
        return null;
    }

    const [, year, month, day, hours = '0', minutes = '0'] = match;
    const date = new Date(0);
    date.setFullYear(Number(year), Number(month) - 1, Number(day));
    date.setHours(Number(hours), Number(minutes), 0, 0);

    return date.getFullYear() === Number(year) &&
        date.getMonth() === Number(month) - 1 &&
        date.getDate() === Number(day) &&
        date.getHours() === Number(hours) &&
        date.getMinutes() === Number(minutes)
        ? date
        : null;
}

export function datePickerValue(date: Date, withTime = false): string {
    const pad = (value: number) => String(value).padStart(2, '0');
    const day = `${String(date.getFullYear()).padStart(4, '0')}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

    return withTime
        ? `${day}T${pad(date.getHours())}:${pad(date.getMinutes())}`
        : day;
}

export function formatDatePickerValue(
    value: string,
    locale: string,
    withTime = false,
): string {
    const date = parseDatePickerValue(value);

    if (!date) {
        return '';
    }

    const formatted = new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(date);

    return withTime
        ? `${formatted} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
        : formatted;
}

export function formatDateTime(
    value: string | null | undefined,
    locale: string,
    timeZone?: string,
): string {
    if (!value) {
        return '—';
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return formatDatePickerValue(value, locale) || '—';
    }

    if (
        !/^\d{4}-\d{2}-\d{2}T.*(?:Z|[+-]\d{2}:\d{2})$/i.test(value) ||
        !parseDatePickerValue(value.slice(0, 10))
    ) {
        return '—';
    }

    const instant = new Date(
        value.replace(
            /\.(\d+)(?=Z|[+-]\d{2}:\d{2}$)/i,
            (_, fraction: string) => `.${fraction.padEnd(3, '0').slice(0, 3)}`,
        ),
    );

    if (!Number.isFinite(instant.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        timeZone,
    }).format(instant);
}

export function parseDatePickerInput(
    value: string,
    locale: string,
    withTime = false,
): string | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '';
    }

    if (parseDatePickerValue(trimmed)) {
        return datePickerValue(parseDatePickerValue(trimmed)!, withTime);
    }

    const match =
        /^(\d{1,4})[/.\-](\d{1,2})[/.\-](\d{1,4})(?:\s+(\d{1,2}):(\d{2}))?$/.exec(
            trimmed,
        );

    if (
        !match ||
        (withTime && match[4] === undefined) ||
        (!withTime && match[4] !== undefined)
    ) {
        return null;
    }

    const order = new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    })
        .formatToParts(new Date(2026, 9, 16))
        .filter((part) => ['year', 'month', 'day'].includes(part.type));
    const parts = Object.fromEntries(
        order.map((part, index) => [part.type, match[index + 1]]),
    );

    if (parts.year.length !== 4) {
        return null;
    }

    const normalized = `${parts.year}-${parts.month.padStart(2, '0')}-${parts.day.padStart(2, '0')}${withTime ? `T${match[4].padStart(2, '0')}:${match[5]}` : ''}`;

    return parseDatePickerValue(normalized) ? normalized : null;
}

export function datePickerValueAllowed(
    value: string,
    min?: string | null,
    max?: string | null,
): boolean {
    if (!parseDatePickerValue(value)) {
        return false;
    }

    return (
        (!min || value >= min) && (!max || value.slice(0, max.length) <= max)
    );
}
