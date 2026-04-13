export type ContextualHelpKnowledgeArticleLink = {
    uuid: string;
    slug: string;
    title: string | null;
    url: string;
};

export type CurrentContextualHelpSharedData = {
    uuid: string;
    page_key: string;
    sort_order: number;
    locale: string;
    available_locales: string[];
    title: string | null;
    body: string | null;
    knowledge_article: ContextualHelpKnowledgeArticleLink | null;
};
