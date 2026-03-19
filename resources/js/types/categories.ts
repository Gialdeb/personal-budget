export type CategoryOption = {
    value: string;
    label: string;
};

export type CategoryItem = {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
    direction_type: string;
    direction_label: string;
    group_type: string;
    group_label: string;
    sort_order: number;
    is_active: boolean;
    is_selectable: boolean;
    depth: number;
    full_path: string;
    children_count: number;
    usage_count: number;
    is_deletable: boolean;
    descendant_ids: number[];
};

export type CategoryTreeItem = CategoryItem & {
    children: CategoryTreeItem[];
};

export type CategorySummary = {
    total_count: number;
    root_count: number;
    active_count: number;
    selectable_count: number;
    used_count: number;
};

export type CategoryPageProps = {
    categories: {
        tree: CategoryTreeItem[];
        flat: CategoryItem[];
        summary: CategorySummary;
    };
    options: {
        direction_types: CategoryOption[];
        group_types: CategoryOption[];
    };
};
