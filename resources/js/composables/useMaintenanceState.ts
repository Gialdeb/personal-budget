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
import type {
    MaintenanceStateRealtimePayload,
    MaintenanceStateSharedData,
} from '@/types';

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

function applyRealtimeMaintenanceState(
    payload: MaintenanceStateRealtimePayload,
): void {
    maintenanceState.value = normalizeMaintenanceState(payload);
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
            maintenanceState.value = normalizeMaintenanceState(value);
        },
        { immediate: true, deep: true },
    );

    onMounted(() => {
        realtimeSubscriptionCount += 1;
        ensureRealtimeSubscription();
    });

    onBeforeUnmount(() => {
        realtimeSubscriptionCount = Math.max(0, realtimeSubscriptionCount - 1);

        if (realtimeSubscriptionCount === 0) {
            releaseRealtimeSubscription();
        }
    });

    return {
        maintenanceState: readonly(maintenanceState),
        isMaintenanceActive: computed(() => maintenanceState.value.active),
    };
}
