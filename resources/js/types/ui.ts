export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type AppChangelogMeta = {
    index_url: string;
    latest_release_label: string | null;
    latest_release_channel: string | null;
    latest_release_url: string;
    has_published_release: boolean;
};

export type AppMeta = {
    name: string;
    version: string;
    environment: string;
    changelog_url: string;
    changelog: AppChangelogMeta;
};
