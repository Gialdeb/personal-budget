import { onUnmounted, ref } from 'vue';

export type ToastFeedback = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

export function useToastFeedback(durationMs = 4000) {
    const feedback = ref<ToastFeedback | null>(null);
    let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

    function clearFeedback(): void {
        if (feedbackTimeout) {
            clearTimeout(feedbackTimeout);
            feedbackTimeout = null;
        }

        feedback.value = null;
    }

    function showFeedback(nextFeedback: ToastFeedback): void {
        if (feedbackTimeout) {
            clearTimeout(feedbackTimeout);
            feedbackTimeout = null;
        }

        feedback.value = nextFeedback;
        feedbackTimeout = setTimeout(() => {
            feedback.value = null;
            feedbackTimeout = null;
        }, durationMs);
    }

    onUnmounted(() => {
        if (feedbackTimeout) {
            clearTimeout(feedbackTimeout);
        }
    });

    return {
        feedback,
        showFeedback,
        clearFeedback,
    };
}
