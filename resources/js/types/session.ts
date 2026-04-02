export type SessionWarningSharedData = {
    enabled: boolean;
    expires_at: string;
    warning_window_seconds: number;
    session_lifetime_seconds: number;
};

export type SessionRealtimeState = 'warning' | 'refreshed';

export type SessionWarningRealtimePayload = {
    state: SessionRealtimeState;
    expires_at: string;
    warning_window_seconds: number;
    session_lifetime_seconds: number;
};
