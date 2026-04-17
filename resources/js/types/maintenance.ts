export type MaintenanceStatus = 'active' | 'inactive';

export type MaintenanceStateSharedData = {
    active: boolean;
    status: MaintenanceStatus;
    checked_at: string | null;
};

export type MaintenanceStateRealtimePayload = MaintenanceStateSharedData;
