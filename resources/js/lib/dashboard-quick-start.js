const DASHBOARD_QUICK_START_STORAGE_PREFIX = 'dashboard-quick-start-dismissed:';

export function buildDashboardQuickStartStorageKey(identity) {
    return `${DASHBOARD_QUICK_START_STORAGE_PREFIX}${identity || 'anonymous'}`;
}

export function readDashboardQuickStartDismissed(identity) {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.localStorage.getItem(
            buildDashboardQuickStartStorageKey(identity),
        ) === 'true'
    );
}

export function persistDashboardQuickStartDismissed(identity, value) {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        buildDashboardQuickStartStorageKey(identity),
        value ? 'true' : 'false',
    );
}

export { DASHBOARD_QUICK_START_STORAGE_PREFIX };
