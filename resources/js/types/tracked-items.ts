export type TrackedItemCounts = {
    children: number;
    transactions: number;
    budgets: number;
    recurring_entries: number;
    scheduled_entries: number;
};

export type TrackedItemItem = {
    uuid: string;
    parent_uuid: string | null;
    name: string;
    slug: string;
    type: string | null;
    is_active: boolean;
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
