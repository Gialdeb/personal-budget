export type TawkToIntegrationConfig = {
    enabled: boolean;
    propertyId: string | null;
    widgetId: string | null;
};

export type PublicIntegrationsSharedData = {
    tawkTo: TawkToIntegrationConfig;
};
