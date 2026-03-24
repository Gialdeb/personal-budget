export type AdminUserRole = 'admin' | 'staff' | 'user';

export type AutomationPipelineState =
    | 'healthy'
    | 'running'
    | 'warning'
    | 'failed'
    | 'stale'
    | 'stuck'
    | 'never_ran'
    | 'disabled'
    | 'timed_out'
    | 'unknown';

export type AutomationRunStatus =
    | 'pending'
    | 'running'
    | 'success'
    | 'warning'
    | 'failed'
    | 'skipped'
    | 'timed_out';

export type AutomationTriggerType =
    | 'scheduled'
    | 'manual'
    | 'retry'
    | 'system';

export type AutomationPipelineOption = {
    value: string;
    label: string;
};

export type AutomationLatestRun = {
    uuid: string;
    status: AutomationRunStatus | null;
    trigger_type: AutomationTriggerType | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string | null;
    duration_ms: number | null;
    error_message: string | null;
};

export type AutomationPipelineStatus = {
    key: string;
    enabled: boolean;
    critical: boolean;
    alert_on_failure: boolean;
    max_expected_interval_minutes: number;
    state: AutomationPipelineState;
    latest_run: AutomationLatestRun | null;
};

export type AutomationRunItem = {
    uuid: string;
    automation_key: string;
    pipeline: string | null;
    job_class: string | null;
    status: AutomationRunStatus | null;
    trigger_type: AutomationTriggerType | null;
    started_at: string | null;
    finished_at: string | null;
    duration_ms: number | null;
    processed_count: number;
    success_count: number;
    warning_count: number;
    error_count: number;
    batch_id: string | null;
    attempt: number | null;
    host: string | null;
    context: Record<string, unknown> | Array<unknown>;
    result: Record<string, unknown> | Array<unknown>;
    error_message: string | null;
    exception_class: string | null;
    created_at: string | null;
    updated_at: string | null;
    is_retryable: boolean;
};

export type AutomationRunsFilters = {
    pipeline: string | null;
    status: string | null;
    trigger_type: string | null;
};

export type AutomationRunsOptions = {
    pipelines: string[];
    statuses: string[];
    trigger_types: string[];
};

export type PaginationMetaLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type ResourcePaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationMetaLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
};

export type ResourcePaginationLinks = {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
};

export type PaginatedAutomationRuns = {
    data: AutomationRunItem[];
    links: ResourcePaginationLinks;
    meta: ResourcePaginationMeta;
};

export type AdminAutomationIndexPageProps = {
    runs: PaginatedAutomationRuns;
    statuses: AutomationPipelineStatus[];
    filters: AutomationRunsFilters;
    options: AutomationRunsOptions;
};

export type AdminAutomationShowPageProps = {
    run: AutomationRunItem;
};

export type AdminUserItem = {
    id: number;
    name: string;
    surname: string | null;
    full_name: string;
    email: string;
    roles: AdminUserRole[];
    primary_role: AdminUserRole | null;
    status: string;
    status_label: string;
    plan_code: string | null;
    subscription_status: string;
    subscription_status_label: string;
    is_impersonable: boolean;
    email_verified_at: string | null;
    created_at: string | null;
    can_impersonate: boolean;
    can_ban: boolean;
    can_suspend: boolean;
    can_reactivate: boolean;
    can_manage_roles: boolean;
    can_delete: boolean;
};

export type AdminUserFilterValue = {
    value: string;
    label: string;
};

export type AdminUsersFilters = {
    search: string;
    role: string;
    status: string;
    plan: string;
};

export type AdminUsersOptions = {
    roles: AdminUserFilterValue[];
    statuses: AdminUserFilterValue[];
    plans: AdminUserFilterValue[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedAdminUsers = {
    data: AdminUserItem[];
    links: PaginationLink[];
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
    per_page: number;
};

export type AdminUsersPageProps = {
    users: PaginatedAdminUsers;
    filters: AdminUsersFilters;
    options: AdminUsersOptions;
};
