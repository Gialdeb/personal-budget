export type TransactionTemplatePrefill = {
    uuid: string;
    name: string;
    icon: string;
    color: string;
    direction: 'income' | 'expense';
    account_uuid: string | null;
    category_uuid: string | null;
    tracked_item_uuid: string | null;
    shared_counterparty_uuid: string | null;
    description: string | null;
    notes?: string | null;
    default_amount: string | null;
    shared_enabled: boolean;
    shared_payer: 'self' | 'counterparty';
    shared_split_mode: 'equal' | 'percentage';
    my_share_basis_points: number;
    issues: Record<string, string>;
};
