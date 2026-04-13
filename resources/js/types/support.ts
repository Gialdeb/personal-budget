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
    supportCategories: SupportCategoryOption[];
    supportContext: SupportContext;
};
