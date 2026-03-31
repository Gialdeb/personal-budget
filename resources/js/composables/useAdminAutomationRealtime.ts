import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { listenOnPrivateChannel } from '@/lib/realtime/echo';

type AutomationRunRealtimePayload = {
    run: {
        uuid: string;
        automation_key: string;
        status: string | null;
        trigger_type: string | null;
        started_at: string | null;
        finished_at: string | null;
        created_at: string | null;
        updated_at: string | null;
        error_message: string | null;
    };
};

export function useAdminAutomationRealtime(
    only: string[] = ['runs', 'statuses'],
): void {
    let stopListening = () => {};
    let pendingReload: ReturnType<typeof setTimeout> | null = null;

    onMounted(() => {
        stopListening = listenOnPrivateChannel<AutomationRunRealtimePayload>(
            'admin.automation.runs',
            'automation.run.updated',
            () => {
                if (pendingReload !== null) {
                    return;
                }

                pendingReload = setTimeout(() => {
                    router.reload({
                        only,
                    });

                    pendingReload = null;
                }, 200);
            },
        );
    });

    onUnmounted(() => {
        stopListening();

        if (pendingReload !== null) {
            clearTimeout(pendingReload);
        }
    });
}
