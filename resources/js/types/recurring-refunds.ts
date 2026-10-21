export interface PlanRefundPreview {
    currency: string;
    total_cents: number;
    account_uuid: string;
    accounts: Array<{ uuid: string; name: string }>;
    refunds: Array<{
        uuid: string;
        date: string;
        amount_cents: number;
        account: string;
        payments: Array<{ uuid: string; date: string; amount_cents: number }>;
    }>;
}
