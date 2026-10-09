export function templateReferenceMatches(reference, categoryUuids, groupKey) {
    const categories = reference.category_uuids ?? [];
    const groups = reference.group_keys ?? [];

    return (
        (!categories.length ||
            categories.some((uuid) => categoryUuids.includes(uuid))) &&
        (!groups.length || groups.includes(groupKey))
    );
}
