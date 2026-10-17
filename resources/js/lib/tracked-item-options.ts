export function normalizeReferenceSearch(value: string): string {
    return value
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .trim()
        .replace(/\s+/gu, ' ')
        .toLowerCase();
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
    },
    query: string,
): boolean {
    const normalizedQuery = normalizeReferenceSearch(query);

    return [
        option.label,
        option.fullPath,
        option.full_path,
        option.value,
        ...(option.search_terms ?? []),
    ].some(
        (value) =>
            typeof value === 'string' &&
            normalizeReferenceSearch(value).includes(normalizedQuery),
    );
}
