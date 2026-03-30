<script setup lang="ts">
import { ShieldCheck, SlidersHorizontal, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useCookieConsent } from '@/composables/useCookieConsent';

const { t } = useI18n();
const {
    hasConsent,
    isBannerVisible,
    isPreferencesOpen,
    draftPreferences,
    acceptAll,
    acceptEssentialOnly,
    saveCustomPreferences,
    openPreferences,
    closePreferences,
} = useCookieConsent();

const optionalCategories = ['preferences', 'analytics', 'marketing'] as const;
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 bottom-0 z-50 px-4 pb-4 sm:px-6 sm:pb-6"
    >
        <div
            v-if="isBannerVisible"
            class="pointer-events-auto mx-auto w-full max-w-5xl rounded-[2rem] border border-[#ead8cd] bg-white/96 p-5 shadow-[0_30px_80px_-40px_rgba(15,23,42,0.32)] backdrop-blur sm:p-6"
            data-test="cookie-consent-banner"
        >
            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="max-w-3xl space-y-3">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                    >
                        <ShieldCheck class="size-3.5" />
                        {{ t('app.cookieConsent.badge') }}
                    </div>
                    <div class="space-y-2">
                        <h2
                            class="text-lg font-semibold tracking-tight text-slate-950 sm:text-xl"
                        >
                            {{ t('app.cookieConsent.title') }}
                        </h2>
                        <p
                            class="text-sm leading-7 text-slate-600 sm:text-base"
                        >
                            {{ t('app.cookieConsent.description') }}
                        </p>
                    </div>
                </div>

                <div
                    class="grid gap-3 rounded-[1.6rem] bg-[#fff8f4] p-2 sm:w-full sm:max-w-xl sm:grid-cols-3 sm:bg-transparent sm:p-0"
                >
                    <button
                        type="button"
                        class="inline-flex min-h-12 w-full items-center justify-center rounded-2xl border border-[#d7c2b3] bg-white px-5 py-3 text-center text-sm font-semibold text-slate-800 shadow-[0_14px_30px_-22px_rgba(15,23,42,0.26)] transition hover:border-[#c7ab99] hover:bg-[#fffdfa] hover:text-slate-950 sm:px-4"
                        @click="acceptEssentialOnly"
                    >
                        {{ t('app.cookieConsent.actions.reject') }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-12 w-full items-center justify-center rounded-2xl border border-[#efc6b6] bg-[#fff3ed] px-5 py-3 text-center text-sm font-semibold text-[#b84a34] shadow-[0_14px_30px_-22px_rgba(234,90,71,0.24)] transition hover:border-[#e0b19d] hover:bg-[#ffe6da] hover:text-[#9f3f2b] sm:px-4"
                        @click="openPreferences"
                    >
                        {{ t('app.cookieConsent.actions.customize') }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-12 w-full items-center justify-center rounded-2xl bg-[#ea5a47] bg-[linear-gradient(135deg,#ea5a47_0%,#ef6c5b_100%)] px-5 py-3 text-center text-sm font-semibold text-white shadow-[0_20px_38px_-22px_rgba(234,90,71,0.6)] transition hover:brightness-[0.97] sm:px-4"
                        @click="acceptAll"
                    >
                        {{ t('app.cookieConsent.actions.accept') }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="isPreferencesOpen"
            class="pointer-events-auto fixed inset-0 z-50 flex items-end bg-[rgba(15,23,42,0.36)] p-0 sm:items-center sm:justify-center sm:p-6"
        >
            <div
                class="w-full rounded-t-[2rem] border border-[#ead8cd] bg-[#fffdfb] shadow-[0_38px_100px_-48px_rgba(15,23,42,0.4)] sm:max-w-2xl sm:rounded-[2rem]"
                data-test="cookie-consent-preferences"
            >
                <div
                    class="flex items-start justify-between gap-4 border-b border-[#efe1d6] px-5 py-5 sm:px-6"
                >
                    <div class="space-y-2">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase"
                        >
                            <SlidersHorizontal class="size-3.5" />
                            {{ t('app.cookieConsent.preferences.badge') }}
                        </div>
                        <h2
                            class="text-xl font-semibold tracking-tight text-slate-950"
                        >
                            {{ t('app.cookieConsent.preferences.title') }}
                        </h2>
                        <p class="text-sm leading-7 text-slate-600">
                            {{ t('app.cookieConsent.preferences.description') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-2xl border border-[#ead8cd] bg-white text-slate-500 transition hover:text-slate-900"
                        :aria-label="t('app.common.close')"
                        @click="closePreferences"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <div
                        class="rounded-[1.5rem] border border-[#ece4dc] bg-white px-4 py-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-slate-950">
                                    {{
                                        t(
                                            'app.cookieConsent.categories.necessary.title',
                                        )
                                    }}
                                </p>
                                <p class="text-sm leading-6 text-slate-600">
                                    {{
                                        t(
                                            'app.cookieConsent.categories.necessary.description',
                                        )
                                    }}
                                </p>
                            </div>
                            <span
                                class="rounded-full border border-[#d9e6d4] bg-[#f3fbf0] px-3 py-1 text-[11px] font-semibold tracking-[0.14em] text-[#5e8b51] uppercase"
                            >
                                {{ t('app.cookieConsent.alwaysActive') }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-for="category in optionalCategories"
                        :key="category"
                        class="flex items-start justify-between gap-4 rounded-[1.5rem] border border-[#ece4dc] bg-white px-4 py-4"
                    >
                        <div class="space-y-1">
                            <label
                                :for="`cookie-consent-${category}`"
                                class="block text-sm font-semibold text-slate-950"
                            >
                                {{
                                    t(
                                        `app.cookieConsent.categories.${category}.title`,
                                    )
                                }}
                            </label>
                            <p class="text-sm leading-6 text-slate-600">
                                {{
                                    t(
                                        `app.cookieConsent.categories.${category}.description`,
                                    )
                                }}
                            </p>
                        </div>

                        <span
                            class="relative mt-1 inline-flex shrink-0 items-center"
                        >
                            <input
                                :id="`cookie-consent-${category}`"
                                v-model="draftPreferences[category]"
                                type="checkbox"
                                class="peer sr-only"
                            />
                            <span
                                class="h-7 w-12 rounded-full bg-slate-200 transition peer-checked:bg-[#ea5a47]"
                            />
                            <span
                                class="pointer-events-none absolute left-1 h-5 w-5 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5"
                            />
                        </span>
                    </div>
                </div>

                <div
                    class="flex flex-col gap-3 border-t border-[#efe1d6] px-5 py-5 sm:flex-row sm:justify-end sm:px-6"
                >
                    <button
                        v-if="hasConsent"
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-[#dcc9bc] bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-[0_10px_24px_-18px_rgba(15,23,42,0.2)] transition hover:border-[#cdb5a6] hover:bg-[#fffdfa] hover:text-slate-950"
                        @click="closePreferences"
                    >
                        {{ t('app.common.cancel') }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-[#efcfc1] bg-[#fff1ea] px-4 py-3 text-sm font-semibold text-[#b84a34] shadow-[0_10px_24px_-18px_rgba(234,90,71,0.2)] transition hover:border-[#e6b9a8] hover:bg-[#ffe7dc] hover:text-[#9f3f2b]"
                        @click="acceptEssentialOnly"
                    >
                        {{ t('app.cookieConsent.actions.essentialOnly') }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-[#ea5a47] bg-[linear-gradient(135deg,#ea5a47_0%,#ef6c5b_100%)] px-4 py-3 text-sm font-semibold text-white shadow-[0_18px_34px_-20px_rgba(234,90,71,0.58)] transition hover:brightness-[0.97]"
                        @click="saveCustomPreferences"
                    >
                        {{ t('app.cookieConsent.actions.save') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
