<script setup lang="ts">
import { RefreshCcw, WifiOff } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { usePwa } from '@/composables/usePwa';

const { t } = useI18n();
const { isEnabled, isOffline, isUpdateReady, isApplyingUpdate, applyUpdate } =
    usePwa();
</script>

<template>
    <div
        v-if="isEnabled"
        class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-3 px-4"
    >
        <Alert
            v-if="isUpdateReady"
            class="pointer-events-auto w-full max-w-2xl border-[#ea5a47]/20 bg-white/95 text-slate-950 shadow-xl shadow-black/10 backdrop-blur dark:border-[#ef6c5b]/30 dark:bg-slate-950/95 dark:text-slate-50"
        >
            <RefreshCcw class="size-4 text-[#ea5a47]" />
            <AlertTitle>{{ t('app.pwa.update.title') }}</AlertTitle>
            <AlertDescription class="space-y-3">
                <p>{{ t('app.pwa.update.description') }}</p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        class="bg-[#ea5a47] text-white hover:bg-[#dc4d3a]"
                        :disabled="isApplyingUpdate"
                        @click="applyUpdate"
                    >
                        {{
                            isApplyingUpdate
                                ? t('app.pwa.update.applying')
                                : t('app.pwa.update.action')
                        }}
                    </Button>
                </div>
            </AlertDescription>
        </Alert>

        <Alert
            v-if="isOffline"
            class="pointer-events-auto w-full max-w-2xl border-amber-200 bg-amber-50/95 text-amber-950 shadow-lg shadow-amber-900/10 backdrop-blur dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-50"
        >
            <WifiOff class="size-4" />
            <AlertTitle>{{ t('app.pwa.offline.title') }}</AlertTitle>
            <AlertDescription>
                {{ t('app.pwa.offline.description') }}
            </AlertDescription>
        </Alert>
    </div>
</template>
