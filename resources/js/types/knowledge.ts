export type PublicKnowledgeSectionSummary = {
    uuid: string;
    slug: string;
    title: string | null;
};

export type PublicKnowledgeArticle = {
    uuid: string;
    slug: string;
    sort_order: number;
    published_at: string | null;
    locale: string;
    available_locales: string[];
    title: string | null;
    excerpt: string | null;
    body: string | null;
    section: PublicKnowledgeSectionSummary | null;
};

export type PublicKnowledgeSection = {
    uuid: string;
    slug: string;
    sort_order: number;
    locale: string;
    available_locales: string[];
    title: string | null;
    description: string | null;
    article_count: number;
    articles: PublicKnowledgeArticle[];
};

export type HelpCenterIndexPageProps = {
    canRegister: boolean;
    sections: PublicKnowledgeSection[];
    articleCount: number;
};

export type HelpCenterSectionPageProps = {
    canRegister: boolean;
    section: PublicKnowledgeSection;
};

export type HelpCenterArticlePageProps = {
    canRegister: boolean;
    article: PublicKnowledgeArticle;
    relatedArticles: PublicKnowledgeArticle[];
};
