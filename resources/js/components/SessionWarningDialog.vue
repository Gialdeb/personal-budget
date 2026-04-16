<script setup lang="ts">
import { AlertTriangle, ArrowRight, House } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useSessionWarning } from '@/composables/useSessionWarning';

const { t } = useI18n();
const { state, countdownLabel, staySignedIn, signOut, signInAgain, goToHome } =
    useSessionWarning();
</script>

<template>
    <Dialog :open="state.isOpen">
        <DialogContent
            :show-close-button="false"
            class="h-[100dvh] max-h-[100dvh] max-w-none overflow-hidden rounded-none border-slate-200 bg-white/98 p-0 shadow-2xl sm:h-auto sm:max-h-[calc(100dvh-2rem)] sm:max-w-xl sm:rounded-3xl dark:border-slate-800 dark:bg-slate-950/98"
            data-test="session-warning-dialog"
            @escape-key-down.prevent
            @pointer-down-outside.prevent
            @interact-outside.prevent
        >
            <div class="flex h-full flex-col sm:h-auto">
                <div
                    class="relative overflow-hidden bg-linear-to-br from-slate-950 via-slate-900 to-teal-950 p-6 text-white sm:rounded-t-3xl"
                >
                <div
                    class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.24),transparent_40%),radial-gradient(circle_at_bottom_left,rgba(16,185,129,0.2),transparent_40%)]"
                />
                <div class="relative flex items-start gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/15 bg-white/8"
                    >
                        <AlertTriangle class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xs font-semibold tracking-[0.32em] text-sky-200/80 uppercase"
                        >
                            {{ state.isExpired ? 'SESSION' : 'WARNING' }}
                        </p>
                        <DialogHeader class="mt-2 gap-2 text-left">
                            <DialogTitle
                                class="text-2xl font-semibold text-white"
                            >
                                {{
                                    state.isExpired
                                        ? t('app.sessionWarning.expiredTitle')
                                        : t('app.sessionWarning.title')
                                }}
                            </DialogTitle>
                            <DialogDescription
                                class="max-w-lg text-sm leading-6 text-slate-200"
                            >
                                {{
                                    state.isExpired
                                        ? t('app.sessionWarning.expiredMessage')
                                        : state.isCheckingExpiry
                                          ? t(
                                                'app.sessionWarning.checkingMessage',
                                            )
                                          : t('app.sessionWarning.message', {
                                                countdown: countdownLabel,
                                            })
                                }}
                            </DialogDescription>
                        </DialogHeader>
                    </div>
                </div>
                <div
                    v-if="!state.isExpired"
                    class="relative mt-5 inline-flex items-center rounded-full border border-white/12 bg-white/8 px-4 py-2 text-sm font-medium text-white"
                >
                    {{
                        state.isCheckingExpiry
                            ? t('app.sessionWarning.checkingLabel')
                            : countdownLabel
                    }}
                </div>
                </div>

                <div
                    class="flex flex-1 flex-col justify-end space-y-4 px-6 py-5 sm:block"
                >
                    <div
                        v-if="state.keepAliveError"
                        class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200"
                    >
                        {{ t('app.sessionWarning.keepAliveError') }}
                    </div>

                    <DialogFooter class="grid gap-3 sm:grid-cols-3">
                        <button
                            v-if="!state.isExpired"
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            :disabled="state.keepAlivePending"
                            @click="staySignedIn"
                        >
                            {{
                                state.keepAlivePending
                                    ? t('app.common.loading')
                                    : t('app.sessionWarning.keepAlive')
                            }}
                        </button>
                        <button
                            v-if="!state.isExpired"
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100 dark:hover:border-slate-700 dark:hover:bg-slate-900"
                            @click="signOut"
                        >
                            {{ t('app.sessionWarning.logout') }}
                        </button>
                        <button
                            v-if="!state.isExpired"
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-white dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:hover:border-slate-700"
                            @click="goToHome"
                        >
                            <House class="h-4 w-4" />
                            {{ t('app.sessionWarning.home') }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            @click="signInAgain"
                        >
                            <ArrowRight class="h-4 w-4" />
                            {{ t('app.sessionWarning.signInAgain') }}
                        </button>
                        <button
                            v-if="state.isExpired"
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-white dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:hover:border-slate-700"
                            @click="goToHome"
                        >
                            <House class="h-4 w-4" />
                            {{ t('app.sessionWarning.home') }}
                        </button>
                    </DialogFooter>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
