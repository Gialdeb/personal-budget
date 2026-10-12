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
                full_path:
                    category?.full_path ?? category?.name ?? uncategorizedLabel,
                icon: category?.icon ?? null,
                color: category?.color ?? null,
                templates: [],
            });
        }

        groups.get(uuid).templates.push(template);
    }

    const result = [...groups.values()];
    const names = result.map((group) => group.name.toLocaleLowerCase());

    for (const group of result) {
        if (
            names.filter((name) => name === group.name.toLocaleLowerCase())
                .length > 1
        ) {
            group.name = group.full_path;
        }
    }

    return result;
}
