<script setup lang="ts">
import { Wrench } from 'lucide-vue-next';
import { computed, onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMaintenanceState } from '@/composables/useMaintenanceState';

const { t } = useI18n();
const { isMaintenanceActive } = useMaintenanceState();

const title = computed(() => t('app.maintenance.title'));

function setAppContentBlocked(active: boolean): void {
    const contentRoot = document.querySelector<HTMLElement>(
        '[data-maintenance-content-root]',
    );

    document.documentElement.classList.toggle('overflow-hidden', active);
    document.body.classList.toggle('overflow-hidden', active);

    if (active) {
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }

        contentRoot?.setAttribute('inert', '');
        contentRoot?.setAttribute('aria-hidden', 'true');

        return;
    }

    contentRoot?.removeAttribute('inert');
    contentRoot?.removeAttribute('aria-hidden');
}

watch(
    isMaintenanceActive,
    (active) => {
        setAppContentBlocked(active);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    setAppContentBlocked(false);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isMaintenanceActive"
            data-test="maintenance-state-overlay"
            class="fixed inset-0 z-[250] flex min-h-[100dvh] items-center justify-center overflow-hidden bg-stone-950 px-5 py-8 text-stone-50 pointer-events-auto"
            role="alertdialog"
            aria-modal="true"
            :aria-label="title"
        >
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(245,158,11,0.22),transparent_34%),radial-gradient(circle_at_bottom_right,rgba(120,113,108,0.32),transparent_36%)]"
                aria-hidden="true"
            />

            <section
                class="relative w-full max-w-lg rounded-[2rem] border border-white/10 bg-white/10 p-6 shadow-2xl shadow-black/40 backdrop-blur-xl sm:p-8"
            >
                <div
                    class="mb-6 inline-flex items-center gap-2 rounded-full border border-amber-300/30 bg-amber-300/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-amber-100"
                >
                    <Wrench class="h-4 w-4" aria-hidden="true" />
                    <span>{{ t('app.maintenance.kicker') }}</span>
                </div>

                <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ title }}
                </h1>

                <p class="mt-4 text-base leading-7 text-stone-200">
                    {{ t('app.maintenance.message') }}
                </p>

                <div
                    class="mt-8 rounded-2xl border border-white/10 bg-stone-950/40 px-4 py-3 text-sm text-stone-200"
                >
                    {{ t('app.maintenance.status') }}
                </div>
            </section>
        </div>
    </Teleport>
</template>
