export type SupportCategoryOption = {
    value: 'bug' | 'feature_request' | 'general_support';
    label: string;
    description: string;
};

export type SupportContext = {
    source_url: string | null;
    source_route: string | null;
    locale: string;
};

export type SupportPageProps = {
    historyNextUrl: string | null;
    historyPreviousUrl: string | null;
    currentTicket: SupportTicket | null;
    openTicketCounts: Record<SupportCategoryOption['value'], number>;
    hasActiveTicket: boolean;
    ticketHistory: Omit<SupportTicket, 'messages' | 'closed_at'>[];
    appVersion: string | null;
    supportCategories: SupportCategoryOption[];
    supportContext: SupportContext;
};

export type SupportTicketMessage = {
    uuid: string;
    author_type: 'user' | 'admin' | 'system';
    body: string;
    created_at: string;
    admin_name?: string | null;
};
export type SupportTicket = {
    auto_close_at?: string | null;
    uuid: string;
    category: SupportCategoryOption['value'];
    subject: string;
    status: 'waiting_admin' | 'waiting_user' | 'closed';
    app_version: string | null;
    created_at: string;
    closed_at: string | null;
    messages: SupportTicketMessage[];
};
