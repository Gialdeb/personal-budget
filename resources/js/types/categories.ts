export type CategoryOption = {
    value: string;
    label: string;
    uuid?: string;
    full_path?: string;
    slug?: string;
    source_account_uuid?: string | null;
    source_account_name?: string | null;
    icon?: string | null;
    color?: string | null;
    groupLabel?: string | null;
    badgeLabel?: string | null;
    ancestor_uuids?: string[];
    is_selectable?: boolean;
};

export type CategoryItem = {
    uuid: string;
    parent_uuid: string | null;
    account_uuid: string | null;
    account_name: string | null;
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
    is_system: boolean;
    scope_kind: 'personal' | 'shared';
    is_personal: boolean;
    is_shared: boolean;
    foundation_key: string | null;
    depth: number;
    subtree_height: number;
    full_path: string;
    children_count: number;
    usage_count: number;
    is_deletable: boolean;
    ancestor_uuids: string[];
    descendant_uuids: string[];
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

export type SharedCategoryAccountCatalog = {
    uuid: string;
    name: string;
    bank_name: string | null;
    is_owned: boolean;
    is_shared: boolean;
    membership_role: string | null;
    membership_status: string | null;
    can_edit: boolean;
    source_categories: CategoryOption[];
    categories: {
        tree: CategoryTreeItem[];
        flat: CategoryItem[];
        summary: CategorySummary;
    };
};

export type SharedCategoryPageProps = {
    sharedCategories: {
        accounts: SharedCategoryAccountCatalog[];
    };
    options: {
        direction_types: CategoryOption[];
        group_types: CategoryOption[];
    };
};
