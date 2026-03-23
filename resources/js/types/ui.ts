export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type AppMeta = {
    name: string;
    version: string;
    environment: string;
    changelog_url: string;
};
