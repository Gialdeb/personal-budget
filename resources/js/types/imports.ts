export type ImportTone = 'success' | 'warning' | 'danger' | 'info' | 'muted';

export type ImportFormatOption = {
    uuid: string;
    name: string;
    code: string;
    version: string;
    parser_label: string;
    bank_name: string | null;
    is_generic: boolean;
    is_advanced: boolean;
    notes: string | null;
};

export type ImportAccountOption = {
    uuid: string;
    label: string;
    bank_name: string | null;
    is_default: boolean;
};

export type ImportDestinationAccountOption = {
    id: number;
    uuid: string;
    label: string;
};

export type ImportCategoryOption = {
    id: number;
    value: string;
    uuid: string;
    label: string;
    full_path?: string;
    slug?: string;
    group_type?: string | null;
    direction_type?: string | null;
    icon?: string | null;
    color?: string | null;
    is_selectable?: boolean;
    ancestor_uuids: string[];
};

export type ImportReferenceOption = {
    value: string;
    uuid: string;
    label: string;
    full_path?: string;
    group_keys?: string[];
    category_uuids?: string[];
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
    is_archived: boolean;
    archived_at_label: string | null;
    management_year: number;
    management_year_label: string;
    show_url: string;
    can_delete: boolean;
    delete_url: string | null;
    can_archive: boolean;
    archive_url: string | null;
    can_restore: boolean;
    restore_url: string | null;
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
    suggested_category: {
        category_uuid: string | null;
        category_label: string | null;
        source: string | null;
        source_label: string | null;
        strategy: string | null;
        confidence: number | null;
        same_account_matches: number | null;
    } | null;
    is_ready: boolean;
    is_imported: boolean;
    is_blocked: boolean;
    can_edit_review: boolean;
    can_skip: boolean;
    review_values: {
        account_id: number | null;
        account_uuid: string | null;
        date: string | null;
        value_date: string | null;
        type: string | null;
        amount: string | null;
        amount_value_raw: string | null;
        detail: string | null;
        category: string | null;
        category_uuid: string | null;
        reference: string | null;
        tracked_item_uuid: string | null;
        merchant: string | null;
        external_reference: string | null;
        balance: string | null;
        balance_value_raw: string | null;
        currency: string | null;
        destination_account_id: number | null;
        destination_account_uuid: string | null;
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
        current_archive: string;
        status_options: Array<{
            value: string;
            label: string;
        }>;
        archive_options: Array<{
            value: string;
            label: string;
        }>;
    };
    options: {
        formats: ImportFormatOption[];
        accounts: ImportAccountOption[];
        default_format_uuid: string | null;
        has_single_active_format: boolean;
    };
};

export type ImportsShowPageProps = {
    importDetail: ImportDetail;
    rows: ImportRowItem[];
    destination_accounts: ImportDestinationAccountOption[];
    categories: ImportCategoryOption[];
    reference_options: ImportReferenceOption[];
};
