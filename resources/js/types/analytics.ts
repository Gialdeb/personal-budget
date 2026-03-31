export type UmamiAnalyticsConfig = {
    enabled: boolean;
    host_url: string | null;
    website_id: string | null;
    domains: string[];
    environment_tag: string | null;
    respect_dnt: boolean;
    public_route_names: string[];
};

export type AnalyticsSharedData = {
    current_route_name: string | null;
    umami: UmamiAnalyticsConfig;
};
