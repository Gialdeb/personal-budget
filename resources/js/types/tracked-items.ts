export type TrackedItemCounts = {
    children: number;
    transactions: number;
    budgets: number;
    recurring_entries: number;
    scheduled_entries: number;
};

export type TrackedItemLinkCounts = {
    total: number;
    direct: number;
    indirect: number;
    groups: Array<{
        key: string;
        total: number;
        direct: number;
        indirect: number;
        archived: number;
        types: Record<string, number>;
    }>;
};

export type TrackedItemItem = {
    link_counts?: TrackedItemLinkCounts;
    canonical_uuid?: string | null;
    canonical_name?: string | null;
    uuid: string;
    parent_uuid: string | null;
    name: string;
    slug: string;
    type: string | null;
    website_url: string | null;
    logo_url: string | null;
    is_active: boolean;
    is_shared_expense_counterpart: boolean;
    depth: number;
    full_path: string;
    parent_name: string | null;
    parent_full_path: string | null;
    children_count: number;
    counts: TrackedItemCounts;
    usage_count: number;
    used: boolean;
    is_deletable: boolean;
    descendant_uuids: string[];
    compatible_category_uuids: string[];
    ancestor_uuids: string[];
};

export type TrackedItemTreeItem = TrackedItemItem & {
    children: TrackedItemTreeItem[];
};

export type TrackedItemsSummary = {
    total_count: number;
    root_count: number;
    active_count: number;
    used_count: number;
    leaf_count: number;
};

export type TrackedItemsPageProps = {
    trackedItems: {
        tree: TrackedItemTreeItem[];
        flat: TrackedItemItem[];
        summary: TrackedItemsSummary;
    };
    options: {
        types: string[];
        categories: Array<{
            value: string;
            uuid: string;
            label: string;
        }>;
    };
    sharedBridge?: {
        accounts: Array<{
            value: string;
            uuid: string;
            label: string;
            shared_items_count: number;
            source_tracked_items: Array<{
                value: string;
                uuid: string;
                label: string;
                category_labels: string[];
            }>;
        }>;
    };
};
