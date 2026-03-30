export type PublicChangelogItem = {
    sort_order: number;
    screenshot_key: string | null;
    link_url: string | null;
    link_label: string | null;
    item_type: string | null;
    platform: string | null;
    title: string | null;
    body: string;
};

export type PublicChangelogSection = {
    key: string;
    label: string | null;
    sort_order: number;
    items: PublicChangelogItem[];
};

export type PublicChangelogRelease = {
    uuid: string;
    version_label: string;
    channel: string;
    is_pinned: boolean;
    published_at: string | null;
    locale: string;
    available_locales: string[];
    title: string | null;
    summary: string | null;
    excerpt: string | null;
    sections: PublicChangelogSection[];
};
