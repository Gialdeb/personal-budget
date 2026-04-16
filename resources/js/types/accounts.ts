export type AccountBalanceNature = 'asset' | 'liability';

export type AccountOption = {
    value: string;
    label: string;
};

export type CurrencyOption = {
    code: string;
    name: string;
    symbol: string;
    minor_unit: number;
    symbol_position: 'prefix' | 'suffix';
    label: string;
};

export type AccountBankOption = {
    uuid: string;
    bank_uuid: string | null;
    name: string;
    display_name: string | null;
    slug: string;
    is_custom: boolean;
    is_active: boolean;
    source_label: string;
    country_code: string | null;
    catalog_name: string | null;
    catalog_display_name: string | null;
    logo_url: string | null;
};

export type AccountTypeOption = {
    uuid: string;
    code: string;
    name: string;
    balance_nature: AccountBalanceNature;
    balance_nature_label: string;
    default_allow_negative_balance: boolean;
};

export type LinkedPaymentAccountOption = {
    uuid: string;
    name: string;
    bank_uuid: string | null;
    user_bank_uuid: string | null;
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
    name: string;
    iban: string | null;
    account_number_masked: string | null;
    currency: string;
    currency_label: string;
    opening_balance: number | null;
    opening_balance_direction: 'positive' | 'negative';
    opening_balance_date: string | null;
    current_balance: number | null;
    is_manual: boolean;
    is_active: boolean;
    is_reported: boolean;
    is_default: boolean;
    notes: string | null;
    settings: Record<string, unknown> | null;
    bank: AccountBankOption | null;
    bank_name: string | null;
    account_type: AccountTypeOption;
    balance_nature: AccountBalanceNature;
    balance_nature_label: string;
    linked_payment_account: LinkedPaymentAccountOption | null;
    credit_card_settings: AccountCreditCardSettings | null;
    counts: AccountCounts;
    usage_count: number;
    used: boolean;
    is_deletable: boolean;
    can_update_currency: boolean;
    currency_lock_message: string | null;
    can_toggle_active: boolean;
    is_protected_cash_account: boolean;
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

export type SharedAccountItem = {
    uuid: string;
    membership_uuid: string | null;
    name: string;
    bank_name: string | null;
    currency: string;
    current_balance: number | null;
    is_active: boolean;
    owner_name: string | null;
    membership_role: string | null;
    membership_role_label: string | null;
    membership_status: string | null;
    membership_status_label: string | null;
    can_leave: boolean;
};

export type AccountsPageProps = {
    accounts: {
        data: AccountItem[];
        summary: AccountsSummary;
    };
    shared_accounts: SharedAccountItem[];
    options: {
        opening_balance_date: {
            available_years: number[];
            min: string | null;
            max: string | null;
            today: string;
        };
        banks: AccountBankOption[];
        account_types: AccountTypeOption[];
        balance_natures: AccountOption[];
        currencies: CurrencyOption[];
        linked_payment_accounts: LinkedPaymentAccountOption[];
        default_account_uuid: string | null;
    };
};
