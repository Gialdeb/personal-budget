import { router } from '@inertiajs/vue3';
import { readonly, ref } from 'vue';
import { bindNavigationFeedback } from '@/lib/navigation-feedback.js';

const pendingUrl = ref<string | null>(null);
let stop: (() => void) | undefined;

export function initializeNavigationFeedback(): void {
    stop?.();
    stop = bindNavigationFeedback(router, (url: string | null) => {
        pendingUrl.value = url;
    });
}

export function useNavigationFeedback() {
    return {
        pendingUrl: readonly(pendingUrl),
        isPending: (url: string): boolean =>
            pendingUrl.value !== null &&
            new URL(url, window.location.origin).href === pendingUrl.value,
    };
}

if (import.meta.hot) {
    import.meta.hot.dispose(() => stop?.());
}
