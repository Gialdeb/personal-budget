export function normalizeReferenceSearch(
    value: string,
    options: { domain?: boolean } = {},
): string {
    const normalized = value
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .trim()
        .replace(/\s+/gu, ' ')
        .toLowerCase();

    if (options.domain && !/\s/u.test(normalized)) {
        try {
            const url = new URL(
                normalized.includes('://')
                    ? normalized
                    : `https://${normalized.replace(/^\/\//u, '')}`,
            );

            if (
                ['http:', 'https:'].includes(url.protocol) &&
                url.hostname.includes('.') &&
                !url.username &&
                !url.password
            ) {
                return url.hostname.replace(/^www\./u, '').replace(/\.$/u, '');
            }
        } catch {
            // Names and incomplete domains retain the existing text normalization.
        }
    }

    return normalized;
}

export function prioritizeReferenceOptions<T extends { value: string }>(
    options: T[],
    matchesContext: (option: T) => boolean,
    isAvailable: (option: T) => boolean,
): T[] {
    const available = options.filter(isAvailable);
    const suggested = available.filter(matchesContext);
    const others = available.filter((option) => !matchesContext(option));

    return [...suggested, ...others];
}

export function matchesReferenceSearch(
    option: {
        value: string;
        label: string;
        fullPath?: string;
        full_path?: string;
        search_terms?: string[];
        domain?: string | null;
    },
    query: string,
    options: { domain?: boolean } = {},
): boolean {
    const normalizedQuery = normalizeReferenceSearch(query, options);
    const domainQuery =
        options.domain && /^[^\s/:?#]+\.[^\s/:?#]+$/u.test(normalizedQuery);

    if (domainQuery) {
        return [
            option.domain,
            option.label,
            ...(option.search_terms ?? []),
        ].some(
            (value) =>
                typeof value === 'string' &&
                normalizeReferenceSearch(value, options) === normalizedQuery,
        );
    }

    return [
        option.label,
        option.fullPath,
        option.full_path,
        option.value,
        ...(option.search_terms ?? []),
        ...(options.domain ? [option.domain] : []),
    ].some(
        (value) =>
            typeof value === 'string' &&
            normalizeReferenceSearch(value).includes(normalizedQuery),
    );
}
