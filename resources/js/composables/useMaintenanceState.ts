import { usePage } from '@inertiajs/vue3';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    readonly,
    ref,
    watch,
} from 'vue';
import { listenOnPublicChannel } from '@/lib/realtime/echo';
import { status as maintenanceStatus } from '@/routes/maintenance';
import type {
    MaintenanceStateRealtimePayload,
    MaintenanceStateSharedData,
} from '@/types';

const MAINTENANCE_SYNC_CHANNEL_NAME = 'soamco-budget-maintenance-state';
const MAINTENANCE_SYNC_STORAGE_KEY = 'soamco-budget-maintenance-state-sync';
const MAINTENANCE_POLL_INTERVAL_MS = 10_000;

const DEFAULT_MAINTENANCE_STATE: MaintenanceStateSharedData = {
    active: false,
    status: 'inactive',
    checked_at: null,
};

const maintenanceState = ref<MaintenanceStateSharedData>({
    ...DEFAULT_MAINTENANCE_STATE,
});

let realtimeSubscriptionCount = 0;
let unsubscribeFromRealtime: (() => void) | null = null;
let syncChannel: BroadcastChannel | null = null;
let pollingIntervalId: number | null = null;
let isPollingMaintenanceStatus = false;
let isStorageSyncListening = false;

function logMaintenanceDebug(message: string, context?: unknown): void {
    if (!import.meta.env.DEV) {
        return;
    }

    console.debug(`[maintenance] ${message}`, context);
}

function normalizeMaintenanceState(
    state: MaintenanceStateSharedData | null,
): MaintenanceStateSharedData {
    if (state === null) {
        return { ...DEFAULT_MAINTENANCE_STATE };
    }

    const active = state.active;

    return {
        active,
        status: active ? 'active' : 'inactive',
        checked_at: state.checked_at ?? null,
    };
}

function writeStorageSync(state: MaintenanceStateSharedData): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            MAINTENANCE_SYNC_STORAGE_KEY,
            JSON.stringify(state),
        );
    } catch (error) {
        logMaintenanceDebug('unable to write cross-tab storage sync', error);
    }
}

function broadcastCrossTabState(state: MaintenanceStateSharedData): void {
    syncChannel?.postMessage(state);
    writeStorageSync(state);
}

function startMaintenanceStatusPolling(): void {
    if (typeof window === 'undefined' || pollingIntervalId !== null) {
        return;
    }

    pollingIntervalId = window.setInterval(() => {
        void refreshMaintenanceStatus();
    }, MAINTENANCE_POLL_INTERVAL_MS);
}

function stopMaintenanceStatusPolling(): void {
    if (typeof window === 'undefined' || pollingIntervalId === null) {
        return;
    }

    window.clearInterval(pollingIntervalId);
    pollingIntervalId = null;
}

function applyMaintenanceState(
    state: MaintenanceStateSharedData | null,
    source: 'shared-props' | 'realtime' | 'poll' | 'cross-tab',
    publish: boolean,
): void {
    const nextState = normalizeMaintenanceState(state);
    maintenanceState.value = nextState;

    if (nextState.active) {
        startMaintenanceStatusPolling();
    } else {
        stopMaintenanceStatusPolling();
    }

    if (publish) {
        broadcastCrossTabState(nextState);
    }

    logMaintenanceDebug(`state applied from ${source}`, nextState);
}

function applyRealtimeMaintenanceState(
    payload: MaintenanceStateRealtimePayload,
): void {
    applyMaintenanceState(payload, 'realtime', true);
}

async function refreshMaintenanceStatus(): Promise<void> {
    if (isPollingMaintenanceStatus || typeof window === 'undefined') {
        return;
    }

    isPollingMaintenanceStatus = true;

    try {
        const response = await window.fetch(maintenanceStatus.url(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as MaintenanceStateSharedData;
        applyMaintenanceState(payload, 'poll', true);
    } catch (error) {
        logMaintenanceDebug('status poll failed', error);
    } finally {
        isPollingMaintenanceStatus = false;
    }
}

function parseSyncedState(value: string | null): MaintenanceStateSharedData {
    if (value === null) {
        return { ...DEFAULT_MAINTENANCE_STATE };
    }

    try {
        return normalizeMaintenanceState(
            JSON.parse(value) as MaintenanceStateSharedData,
        );
    } catch (error) {
        logMaintenanceDebug('invalid cross-tab storage payload', error);

        return { ...DEFAULT_MAINTENANCE_STATE };
    }
}

function handleStorageSync(event: StorageEvent): void {
    if (event.key !== MAINTENANCE_SYNC_STORAGE_KEY) {
        return;
    }

    applyMaintenanceState(parseSyncedState(event.newValue), 'cross-tab', false);
}

function handleBroadcastChannelMessage(event: MessageEvent): void {
    applyMaintenanceState(
        normalizeMaintenanceState(
            event.data as MaintenanceStateSharedData | null,
        ),
        'cross-tab',
        false,
    );
}

function ensureCrossTabSync(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (typeof BroadcastChannel !== 'undefined' && syncChannel === null) {
        syncChannel = new BroadcastChannel(MAINTENANCE_SYNC_CHANNEL_NAME);
        syncChannel.addEventListener('message', handleBroadcastChannelMessage);
    }

    if (!isStorageSyncListening) {
        window.addEventListener('storage', handleStorageSync);
        isStorageSyncListening = true;
    }
}

function releaseCrossTabSync(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener('storage', handleStorageSync);
    isStorageSyncListening = false;

    syncChannel?.removeEventListener('message', handleBroadcastChannelMessage);
    syncChannel?.close();
    syncChannel = null;
}

function ensureRealtimeSubscription(): void {
    if (unsubscribeFromRealtime !== null) {
        return;
    }

    unsubscribeFromRealtime =
        listenOnPublicChannel<MaintenanceStateRealtimePayload>(
            'app.maintenance',
            'maintenance.state.updated',
            applyRealtimeMaintenanceState,
        );
}

function releaseRealtimeSubscription(): void {
    unsubscribeFromRealtime?.();
    unsubscribeFromRealtime = null;
}

function releaseMaintenanceSubscriptions(): void {
    releaseRealtimeSubscription();
    releaseCrossTabSync();
    stopMaintenanceStatusPolling();
}

export function useMaintenanceState() {
    const page = usePage();
    const sharedMaintenanceState = computed(
        () =>
            (page.props.maintenanceState ??
                null) as MaintenanceStateSharedData | null,
    );

    watch(
        sharedMaintenanceState,
        (value) => {
            applyMaintenanceState(value, 'shared-props', false);
        },
        { immediate: true, deep: true },
    );

    onMounted(() => {
        realtimeSubscriptionCount += 1;
        ensureCrossTabSync();
        ensureRealtimeSubscription();

        if (maintenanceState.value.active) {
            void refreshMaintenanceStatus();
        }
    });

    onBeforeUnmount(() => {
        realtimeSubscriptionCount = Math.max(0, realtimeSubscriptionCount - 1);

        if (realtimeSubscriptionCount === 0) {
            releaseMaintenanceSubscriptions();
        }
    });

    return {
        maintenanceState: readonly(maintenanceState),
        isMaintenanceActive: computed(() => maintenanceState.value.active),
    };
}
