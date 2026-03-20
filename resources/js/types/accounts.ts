export type AccountBalanceNature = 'asset' | 'liability';

export type AccountOption = {
    value: string;
    label: string;
};

export type AccountBankOption = {
    uuid: string;
    bank_uuid: string | null;
    name: string;
    slug: string;
    is_custom: boolean;
    is_active: boolean;
    source_label: string;
    country_code: string | null;
    catalog_name: string | null;
};

export type AccountTypeOption = {
    uuid: string;
    code: string;
    name: string;
    balance_nature: AccountBalanceNature;
    balance_nature_label: string;
    default_allow_negative_balance: boolean;
};

export type AccountScopeOption = {
    uuid: string;
    name: string;
    type: string | null;
    color: string | null;
    is_active: boolean;
};

export type LinkedPaymentAccountOption = {
    uuid: string;
    name: string;
    bank_name: string | null;
    currency: string;
    account_type_name: string;
    account_type_code: string;
    balance_nature: AccountBalanceNature;
    is_active: boolean;
    label: string;
};

export type AccountCreditCardSettings = {
    credit_limit: number | null;
    linked_payment_account_uuid: string | null;
    statement_closing_day: number | null;
    payment_day: number | null;
    auto_pay: boolean;
};

export type AccountCounts = {
    transactions: number;
    imports: number;
    opening_balances: number;
    balance_snapshots: number;
    reconciliations: number;
    recurring_entries: number;
    scheduled_entries: number;
    linked_credit_cards: number;
};

export type AccountItem = {
    uuid: string;
    bank_uuid: string | null;
    user_bank_uuid: string | null;
    account_type_uuid: string;
    scope_uuid: string | null;
    name: string;
    iban: string | null;
    account_number_masked: string | null;
    currency: string;
    opening_balance: number | null;
    current_balance: number | null;
    is_manual: boolean;
    is_active: boolean;
    notes: string | null;
    settings: Record<string, unknown> | null;
    bank: AccountBankOption | null;
    bank_name: string | null;
    scope: AccountScopeOption | null;
    account_type: AccountTypeOption;
    balance_nature: AccountBalanceNature;
    balance_nature_label: string;
    linked_payment_account: LinkedPaymentAccountOption | null;
    credit_card_settings: AccountCreditCardSettings | null;
    counts: AccountCounts;
    usage_count: number;
    used: boolean;
    is_deletable: boolean;
    allow_negative_balance: boolean;
};

export type AccountsSummary = {
    total_count: number;
    active_count: number;
    inactive_count: number;
    manual_count: number;
    credit_cards_count: number;
    used_count: number;
};

export type AccountsPageProps = {
    accounts: {
        data: AccountItem[];
        summary: AccountsSummary;
    };
    options: {
        banks: AccountBankOption[];
        account_types: AccountTypeOption[];
        balance_natures: AccountOption[];
        scopes: AccountScopeOption[];
        linked_payment_accounts: LinkedPaymentAccountOption[];
    };
};
