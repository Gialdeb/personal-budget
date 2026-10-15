<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Notivue,
    NotivueSwipe,
    Notification,
    NotificationProgress,
    pastelTheme,
    darkTheme,
    useNotivue,
} from 'notivue';
import type { NotivueTheme } from 'notivue';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { useUiCopy } from '@/i18n/ui-copy';
import {
    bindNotificationFeedback,
    notificationFlash,
} from '@/lib/notification-feedback.js';
import {
    configureNotifications,
    delayedNotification,
    notify,
    notifyFailure,
    notifySuccess,
    notifyValidation,
} from '@/lib/notifications';

const ui = useUiCopy();
const page = usePage();
const config = useNotivue();
const isDark = ref(false);
const notificationTheme = computed<NotivueTheme>(() => ({
    ...(isDark.value ? darkTheme : pastelTheme),
    '--nv-width': '100%',
    '--nv-min-width': '100%',
    '--nv-radius': '16px',
    '--nv-message-size': '0.875rem',
    '--nv-title-size': '0.875rem',
}));
configureNotifications((text) => ui(text as Parameters<typeof ui>[0]));
let stop: (() => void) | undefined;
let finishProgress: (() => void) | undefined;
let themeObserver: MutationObserver | undefined;
const mobile = () =>
    (config.position.value = window.matchMedia('(max-width: 639px)').matches
        ? 'top-center'
        : 'top-right');
function invalidField(event: Event): void {
    const field = event.target;

    if (
        !(
            field instanceof HTMLInputElement ||
            field instanceof HTMLSelectElement ||
            field instanceof HTMLTextAreaElement
        )
    ) {
        return;
    }

    const label =
        field.labels?.[0]?.textContent?.trim() ||
        field.getAttribute('aria-label') ||
        '';
    notifyValidation(
        label
            ? `${label}: ${field.validationMessage}`
            : field.validationMessage,
    );
}
onMounted(() => {
    mobile();
    window.addEventListener('resize', mobile);
    document.addEventListener('invalid', invalidField, true);
    const theme = () => {
        isDark.value = document.documentElement.classList.contains('dark');
    };
    theme();
    themeObserver = new MutationObserver(theme);
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
    stop = bindNotificationFeedback(router, {
        initialFlash: page.props.flash,
        start: () => {
            finishProgress ??= delayedNotification();
        },
        finish: () => {
            finishProgress?.();
            finishProgress = undefined;
        },
        success: notifySuccess,
        failure: notifyFailure,
        validation: notifyValidation,
        message: notify,
    });
    notificationFlash(page.props.flash).forEach((item) =>
        notify(item.type, item.message),
    );
    notifyValidation(page.props.errors);
});
onBeforeUnmount(() => {
    stop?.();
    themeObserver?.disconnect();
    window.removeEventListener('resize', mobile);
    document.removeEventListener('invalid', invalidField, true);
});
</script>

<template>
    <Notivue
        v-slot="item"
        class="soamco-notifications"
        :list-aria-label="ui('Notifiche')"
        :styles="{
            list: { zIndex: 10000 },
            itemContainer: { width: 'min(100%, 30rem)' },
        }"
    >
        <NotivueSwipe :item="item">
            <Notification
                :item="item"
                :theme="notificationTheme"
                :close-aria-label="ui('Chiudi notifica')"
            >
                <NotificationProgress :item="item" />
            </Notification>
        </NotivueSwipe>
    </Notivue>
</template>

<style>
.soamco-notifications {
    --nv-root-width: 100%;
    --nv-root-top: max(1rem, env(safe-area-inset-top));
    --nv-root-right: max(1rem, env(safe-area-inset-right));
    --nv-radius: 16px;
    --nv-spacing: 0.85rem;
    --nv-message-size: 0.875rem;
    --nv-title-size: 0.875rem;
    font-family: inherit;
}
.soamco-notifications .Notivue__notification {
    width: 100%;
}
.soamco-notifications .Notivue__content {
    min-width: 0;
    overflow-wrap: anywhere;
}
@media (max-width: 639px) {
    .soamco-notifications {
        --nv-root-width: 100%;
        --nv-root-top: max(0.75rem, env(safe-area-inset-top));
        --nv-root-left: 0.75rem;
        --nv-root-right: 0.75rem;
        --nv-spacing: 0.75rem;
    }
}
</style>
