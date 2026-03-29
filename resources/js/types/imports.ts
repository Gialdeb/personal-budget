export type ImportTone = 'success' | 'warning' | 'danger' | 'info' | 'muted';

export type ImportAccountOption = {
    uuid: string;
    label: string;
    name: string;
    bank_name: string | null;
    currency: string;
};

export type ImportFormatOption = {
    uuid: string;
    name: string;
    code: string;
    version: string;
    parser_label: string;
    bank_name: string | null;
    is_generic: boolean;
    notes: string | null;
};

export type ImportDestinationAccountOption = {
    id: number;
    uuid: string;
    label: string;
};

export type ImportCategoryOption = {
    id: number;
    value: string;
    label: string;
};

export type ImportListItem = {
    uuid: string;
    status: string;
    status_label: string;
    status_tone: ImportTone;
    original_filename: string;
    account_name: string | null;
    bank_name: string | null;
    format_name: string | null;
    parser_label: string;
    imported_at_label: string | null;
    rows_count: number;
    ready_rows_count: number;
    review_rows_count: number;
    invalid_rows_count: number;
    duplicate_rows_count: number;
    management_year: number;
    management_year_label: string;
    show_url: string;
    can_delete: boolean;
    delete_url: string | null;
};

export type ImportDetail = ImportListItem & {
    parser_key: string | null;
    error_message: string | null;
    meta: Record<string, unknown> | null;
    completed_at_label: string | null;
    failed_at_label: string | null;
    rolled_back_at_label: string | null;
    blocked_year_rows_count: number;
    imported_rows_count: number;
    can_import_ready: boolean;
    can_rollback: boolean;
};

export type ImportPayloadEntry = {
    key: string;
    label: string;
    value: string | null;
};

export type ImportRowItem = {
    uuid: string;
    row_index: number;
    status: string;
    status_label: string;
    status_tone: ImportTone;
    parse_status: string;
    parse_status_label: string;
    description: string | null;
    amount: string | null;
    amount_value_raw: string | null;
    date: string | null;
    type_label: string | null;
    category_label: string | null;
    is_ready: boolean;
    is_imported: boolean;
    is_blocked: boolean;
    can_edit_review: boolean;
    can_skip: boolean;
    review_values: {
        date: string | null;
        type: string | null;
        amount: string | null;
        amount_value_raw: string | null;
        detail: string | null;
        category: string | null;
        reference: string | null;
        merchant: string | null;
        external_reference: string | null;
        balance: string | null;
        balance_value_raw: string | null;
        destination_account_id: number | null;
        destination_account_uuid: string | null;
        source_account_id?: number | null;
        source_account_uuid?: string | null;
    };
    review_update_url: string;
    skip_url: string;
    approve_duplicate_url: string | null;
    errors: string[];
    warnings: string[];
    raw_payload: ImportPayloadEntry[];
    normalized_payload: ImportPayloadEntry[];
};

export type ImportsIndexPageProps = {
    importsPage: {
        active_year: number;
        active_year_label: string;
        active_year_notice: string;
        available_years: Array<{
            value: number;
            label: string;
        }>;
        template_download_url: string;
    };
    imports: {
        data: ImportListItem[];
        summary: {
            total_count: number;
            review_required_count: number;
            completed_count: number;
            failed_count: number;
        };
        pagination: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
            has_pages: boolean;
            previous_page_url: string | null;
            next_page_url: string | null;
            pages: Array<{
                label: string;
                url: string;
                active: boolean;
            }>;
        };
    };
    filters: {
        current_status: string;
        status_options: Array<{
            value: string;
            label: string;
        }>;
    };
    options: {
        accounts: ImportAccountOption[];
        default_account_uuid: string | null;
        formats: ImportFormatOption[];
        default_format_uuid: string | null;
        has_single_active_format: boolean;
    };
};

export type ImportsShowPageProps = {
    importDetail: ImportDetail;
    rows: ImportRowItem[];
    destination_accounts: ImportDestinationAccountOption[];
    categories: ImportCategoryOption[];
};
