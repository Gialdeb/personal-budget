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

export type AutomationTriggerType = 'scheduled' | 'manual' | 'retry' | 'system';

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
    supports_reference_date: boolean;
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

export type CommunicationTemplateMode = 'system' | 'customizable' | 'freeform';

export type CommunicationTemplateChannel =
    | 'mail'
    | 'database'
    | 'sms'
    | 'telegram';

export type CommunicationTemplateTopic = {
    uuid: string;
    key: string;
    label: string;
};

export type CommunicationTemplateOverrideStatus =
    CommunicationTemplateFields & {
        exists: boolean;
        uuid: string | null;
        is_active: boolean;
    };

export type AdminCommunicationTemplateItem = {
    uuid: string;
    key: string;
    name: string;
    description: string | null;
    channel: CommunicationTemplateChannel | null;
    channel_label: string;
    template_mode: CommunicationTemplateMode | null;
    template_mode_label: string;
    is_system_locked: boolean;
    is_active: boolean;
    topic: CommunicationTemplateTopic | null;
    override: CommunicationTemplateOverrideStatus;
    flags: Pick<
        AdminCommunicationTemplateFlags,
        'can_edit_override' | 'can_disable_override'
    >;
};

export type CommunicationTemplateFields = {
    subject_template: string | null;
    title_template: string | null;
    body_template: string | null;
    cta_label_template: string | null;
    cta_url_template: string | null;
};

export type AdminCommunicationTemplateOverride = CommunicationTemplateFields & {
    uuid: string;
    scope: string | null;
    is_active: boolean;
};

export type AdminCommunicationTemplatePreview = {
    subject: string | null;
    title: string | null;
    body: string | null;
    cta_label: string | null;
    cta_url: string | null;
};

export type AdminCommunicationTemplateFlags = {
    can_edit_override: boolean;
    can_disable_override: boolean;
    can_preview: boolean;
};

export type AdminCommunicationTemplateDetail = {
    uuid: string;
    key: string;
    name: string;
    description: string | null;
    channel: CommunicationTemplateChannel | null;
    channel_label: string;
    template_mode: CommunicationTemplateMode | null;
    template_mode_label: string;
    is_system_locked: boolean;
    is_active: boolean;
    topic: CommunicationTemplateTopic | null;
    base_template: CommunicationTemplateFields;
    global_override: AdminCommunicationTemplateOverride | null;
    resolved_content: CommunicationTemplateFields;
    preview: AdminCommunicationTemplatePreview;
    available_variables: string[];
    flags: AdminCommunicationTemplateFlags;
};

export type CommunicationTemplateFilters = {
    search: string;
    channel: string | null;
    template_mode: string | null;
    override_state: string | null;
    lock_state: string | null;
};

export type CommunicationTemplateOptions = {
    channels: CommunicationTemplateChannel[];
    template_modes: CommunicationTemplateMode[];
    override_states: string[];
    lock_states: string[];
};

export type PaginatedCommunicationTemplates = {
    data: AdminCommunicationTemplateItem[];
    links: ResourcePaginationLinks;
    meta: ResourcePaginationMeta;
};

export type AdminCommunicationTemplatesIndexPageProps = {
    templates: PaginatedCommunicationTemplates;
    filters: CommunicationTemplateFilters;
    options: CommunicationTemplateOptions;
};

export type AdminCommunicationTemplatesShowPageProps = {
    template: AdminCommunicationTemplateDetail;
};

export type AdminCommunicationTemplatesEditPageProps = {
    template: AdminCommunicationTemplateDetail;
};

export type ChangelogLocaleOption = {
    code: string;
    label: string;
};

export type ChangelogVersionSuggestions = {
    latest: string | null;
    patch: {
        beta: string;
        stable: string;
    };
    minor: {
        beta: string;
        stable: string;
    };
    major: {
        beta: string;
        stable: string;
    };
};

export type AdminChangelogReleaseListItem = {
    uuid: string;
    version_label: string;
    channel: string;
    is_published: boolean;
    is_pinned: boolean;
    published_at: string | null;
    sort_order: number | null;
    locales: string[];
    title: string | null;
};

export type AdminChangelogItemTranslation = {
    locale: string;
    title: string | null;
    body: string;
};

export type AdminChangelogItem = {
    sort_order: number;
    screenshot_key: string | null;
    link_url: string | null;
    link_label: string | null;
    item_type: string | null;
    platform: string | null;
    translations: AdminChangelogItemTranslation[];
};

export type AdminChangelogSectionTranslation = {
    locale: string;
    label: string;
};

export type AdminChangelogSection = {
    key: string;
    sort_order: number;
    translations: AdminChangelogSectionTranslation[];
    items: AdminChangelogItem[];
};

export type AdminChangelogReleaseTranslation = {
    locale: string;
    title: string;
    summary: string | null;
    excerpt: string | null;
};

export type AdminChangelogReleaseDetail = {
    uuid: string;
    version_label: string;
    channel: string;
    is_published: boolean;
    is_pinned: boolean;
    published_at: string | null;
    sort_order: number | null;
    translations: AdminChangelogReleaseTranslation[];
    sections: AdminChangelogSection[];
};

export type PaginatedAdminChangelogReleases = {
    data: AdminChangelogReleaseListItem[];
    links: ResourcePaginationLinks;
    meta: ResourcePaginationMeta;
};

export type AdminChangelogIndexPageProps = {
    releases: PaginatedAdminChangelogReleases;
    latestRelease: string | null;
    versionSuggestions: ChangelogVersionSuggestions;
    supportedLocales: ChangelogLocaleOption[];
};

export type AdminChangelogEditPageProps = {
    release: AdminChangelogReleaseDetail | null;
    latestRelease: string | null;
    versionSuggestions: ChangelogVersionSuggestions;
    supportedLocales: ChangelogLocaleOption[];
};

export type ManualCommunicationChannel =
    | 'mail'
    | 'database'
    | 'sms'
    | 'telegram';

export type ManualCommunicationChannelOption = {
    value: ManualCommunicationChannel;
    label: string;
};

export type AdminCommunicationComposerLocaleOption = {
    value: string;
    label: string;
};

export type AdminCommunicationComposerContentMode = 'template' | 'custom';

export type AdminCommunicationComposerContentModeOption = {
    value: AdminCommunicationComposerContentMode;
    label: string;
};

export type AdminManualCommunicationCategory = {
    uuid: string;
    key: string;
    name: string;
    description: string | null;
    context_type: string;
    channels: ManualCommunicationChannelOption[];
    channel_options: Array<
        ManualCommunicationChannelOption & {
            is_supported: boolean;
            is_selectable: boolean;
            is_disabled: boolean;
            is_fixed: boolean;
        }
    >;
    default_channel: ManualCommunicationChannel | null;
    fixed_channel: ManualCommunicationChannel | null;
    available_variables: string[];
    supported_content_modes: AdminCommunicationComposerContentMode[];
    flags: {
        available_for_manual_send: boolean;
        can_preview: boolean;
        can_send: boolean;
        requires_context: boolean;
    };
};

export type AdminManualCommunicationRecipient = {
    uuid: string;
    name: string;
    surname: string | null;
    full_name: string;
    email: string;
    label: string;
};

export type AdminManualCommunicationPreview = {
    category: {
        uuid: string;
        key: string;
        name: string;
    };
    sample_recipient: AdminManualCommunicationRecipient;
    recipient_count: number;
    locale: AdminCommunicationComposerLocaleOption;
    content_mode: AdminCommunicationComposerContentMode;
    previews: Array<{
        channel: ManualCommunicationChannelOption;
        template: {
            uuid: string;
            key: string;
            name: string;
        };
        context: {
            type: string;
            uuid: string;
            label: string;
        };
        content: {
            subject: string | null;
            title: string | null;
            body: string | null;
            cta_label: string | null;
            cta_url: string | null;
        };
        presentation: {
            layout: 'mail' | 'notification';
        };
    }>;
};

export type AdminManualCommunicationCustomContent = {
    subject: string;
    title: string;
    body: string;
    cta_label: string;
    cta_url: string;
};

export type AdminManualCommunicationDispatchResult = {
    outbound_count: number;
    recipient_count: number;
    channel_count: number;
    messages: Array<{
        uuid: string;
        channel: ManualCommunicationChannel | null;
        channel_label: string;
        status: string | null;
        queued_at: string | null;
        subject: string | null;
        title: string | null;
    }>;
};

export type AdminCommunicationComposerPageProps = {
    categories: AdminManualCommunicationCategory[];
    channels: ManualCommunicationChannelOption[];
    locale_options: AdminCommunicationComposerLocaleOption[];
    content_modes: AdminCommunicationComposerContentModeOption[];
    recipient_lookup_url: string;
    preview_url: string;
    send_url: string;
};

export type AdminCommunicationCategoryChannelTemplateOption = {
    uuid: string;
    key: string;
    name: string;
};

export type AdminCommunicationCategoryChannelOption = {
    value: ManualCommunicationChannel;
    label: string;
    is_globally_available: boolean;
    is_globally_enabled: boolean;
    is_transport_ready: boolean;
    is_supported: boolean;
    is_selectable: boolean;
    is_disabled: boolean;
    is_fixed: boolean;
    mapping_uuid: string | null;
    template: AdminCommunicationCategoryChannelTemplateOption | null;
    template_options?: AdminCommunicationCategoryChannelTemplateOption[];
};

export type AdminCommunicationCategoryItem = {
    uuid: string;
    key: string;
    name: string;
    description: string | null;
    audience: string | null;
    delivery_mode: string | null;
    preference_mode: string | null;
    context_type: string;
    is_active: boolean;
    fixed_channel: ManualCommunicationChannel | null;
    channels: AdminCommunicationCategoryChannelOption[];
};

export type PaginatedAdminCommunicationCategories = {
    data: AdminCommunicationCategoryItem[];
    links: ResourcePaginationLinks;
    meta: ResourcePaginationMeta;
};

export type AdminCommunicationCategoriesIndexPageProps = {
    categories: PaginatedAdminCommunicationCategories;
    filters: {
        search: string;
    };
};

export type AdminCommunicationCategoryDetail =
    AdminCommunicationCategoryItem & {
        flags: {
            available_for_manual_send: boolean;
            has_active_dispatch_channels: boolean;
        };
        channels: Array<
            AdminCommunicationCategoryChannelOption & {
                template_options: AdminCommunicationCategoryChannelTemplateOption[];
            }
        >;
    };

export type AdminCommunicationCategoriesShowPageProps = {
    category: AdminCommunicationCategoryDetail;
};

export type AdminOutboundStatus = 'queued' | 'sent' | 'failed' | 'skipped';

export type AdminOutboundChannel = 'mail' | 'database' | 'sms' | 'telegram';

export type AdminOutboundOption = {
    value: string;
    label: string;
};

export type AdminOutboundActor = {
    uuid: string | null;
    label: string;
    email: string | null;
    type?: string;
};

export type AdminOutboundCategory = {
    uuid: string | null;
    key: string | null;
    name: string | null;
    description?: string | null;
};

export type AdminOutboundTemplate = {
    uuid: string;
    key: string;
    name: string;
    channel?: string | null;
};

export type AdminOutboundContext = {
    uuid: string | null;
    label: string;
    type: string;
};

export type AdminOutboundContent = {
    subject: string | null;
    title: string | null;
    body: string | null;
    cta_label: string | null;
    cta_url: string | null;
};

export type AdminOutboundItem = {
    uuid: string;
    created_at: string | null;
    queued_at: string | null;
    sent_at: string | null;
    failed_at: string | null;
    channel: AdminOutboundChannel | null;
    channel_label: string;
    status: AdminOutboundStatus | null;
    status_label: string;
    error_message: string | null;
    category: AdminOutboundCategory;
    template: AdminOutboundTemplate | null;
    recipient: AdminOutboundActor | null;
    context: AdminOutboundContext | null;
    content: AdminOutboundContent;
};

export type AdminOutboundDetail = AdminOutboundItem & {
    updated_at: string | null;
    creator: AdminOutboundActor | null;
    payload_snapshot: Record<string, unknown> | Array<unknown> | null;
};

export type PaginatedAdminOutboundMessages = {
    data: AdminOutboundItem[];
    links: ResourcePaginationLinks;
    meta: ResourcePaginationMeta;
};

export type AdminOutboundFilters = {
    search: string;
    status: string | null;
    channel: string | null;
    category: string | null;
    recipient: string;
    date_from: string | null;
    date_to: string | null;
};

export type AdminOutboundOptions = {
    statuses: AdminOutboundStatus[];
    channels: AdminOutboundChannel[];
    categories: AdminOutboundOption[];
};

export type AdminCommunicationOutboundIndexPageProps = {
    outboundMessages: PaginatedAdminOutboundMessages;
    filters: AdminOutboundFilters;
    options: AdminOutboundOptions;
};

export type AdminCommunicationOutboundShowPageProps = {
    outboundMessage: AdminOutboundDetail;
};

export type AdminUserItem = {
    id: number;
    uuid: string;
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
    support_state: string;
    support_state_label: string;
    support_plan_code: string | null;
    last_contribution_at: string | null;
    support_window_ends_at: string | null;
    next_support_reminder_at: string | null;
    donations_count: number;
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

export type AdminBillingOption = {
    value: string;
    label: string;
};

export type AdminBillingTransactionItem = {
    id: number;
    provider: string;
    provider_transaction_id: string | null;
    provider_event_id: string | null;
    billing_plan_code: string | null;
    customer_email: string | null;
    customer_name: string | null;
    currency: string;
    amount: string;
    status: string;
    paid_at: string | null;
    received_at: string | null;
    is_recurring: boolean;
    reconciliation_status: string;
    reconciled_at: string | null;
    admin_notes: string | null;
};

export type AdminUserBillingSummary = {
    id: number;
    uuid: string;
    name: string;
    surname: string | null;
    full_name: string;
    email: string;
    plan_code: string | null;
    support_plan_code: string | null;
    support_status: string;
    support_state_label: string;
    is_supporter: boolean;
    support_started_at: string | null;
    support_window_ends_at: string | null;
    last_contribution_at: string | null;
    next_support_reminder_at: string | null;
    admin_notes: string | null;
    donations_count: number;
};

export type AdminAssignableBillingTransaction = {
    id: number;
    provider: string;
    amount: string;
    currency: string;
    customer_email: string | null;
    status: string;
    received_at: string | null;
};

export type AdminUserBillingPageProps = {
    user: AdminUserBillingSummary;
    transactions: AdminBillingTransactionItem[];
    plans: AdminBillingOption[];
    providers: AdminBillingOption[];
    supportStates: AdminBillingOption[];
    availableTransactions: AdminAssignableBillingTransaction[];
};
