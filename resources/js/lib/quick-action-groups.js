/** Group the active, user-owned catalog by its structural category UUID. */
export function groupQuickActions(templates, uncategorizedLabel) {
    const groups = new Map();

    for (const template of templates) {
        const category = template.category;
        const uuid = category?.uuid ?? 'uncategorized';

        if (!groups.has(uuid)) {
            groups.set(uuid, {
                uuid,
                name: category?.name ?? uncategorizedLabel,
                icon: category?.icon ?? null,
                color: category?.color ?? null,
                templates: [],
            });
        }

        groups.get(uuid).templates.push(template);
    }

    return [...groups.values()];
}
