export type PublicSeoSharedData = {
    title: string;
    description: string;
    canonical_url: string;
    robots: string;
    og_type: string;
    locale: string;
    alternates: Array<{
        hreflang: string;
        url: string;
    }>;
    json_ld: Array<Record<string, unknown>>;
};
