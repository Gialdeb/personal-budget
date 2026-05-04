<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CalendarClock,
    CircleCheck,
    KeyRound,
    Landmark,
    Mail,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import PublicCookieConsent from '@/components/public/PublicCookieConsent.vue';
import { home } from '@/routes';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        mode?: 'login' | 'register' | 'forgot-password' | 'reset-password';
    }>(),
    {
        mode: 'login',
    },
);

const { t } = useI18n();
const isRecoveryMode = computed(
    () => props.mode === 'forgot-password' || props.mode === 'reset-password',
);

const showcaseItems = computed(() => [
    {
        title: t('auth.showcase.transactions.salary.title'),
        meta: t('auth.showcase.transactions.salary.meta'),
        amount: t('auth.showcase.transactions.salary.amount'),
        variant: 'positive',
    },
    {
        title: t('auth.showcase.transactions.rent.title'),
        meta: t('auth.showcase.transactions.rent.meta'),
        amount: t('auth.showcase.transactions.rent.amount'),
        variant: 'neutral',
    },
    {
        title: t('auth.showcase.transactions.groceries.title'),
        meta: t('auth.showcase.transactions.groceries.meta'),
        amount: t('auth.showcase.transactions.groceries.amount'),
        variant: 'expense',
    },
]);

const valueHighlights = computed(() => [
    {
        icon: ShieldCheck,
        label: t('auth.showcase.highlights.security'),
    },
    {
        icon: CalendarClock,
        label: t('auth.showcase.highlights.planning'),
    },
    {
        icon: Landmark,
        label: t('auth.showcase.highlights.control'),
    },
]);

const recoveryHighlights = computed(() => [
    t('auth.showcase.recovery.highlights.email'),
    t('auth.showcase.recovery.highlights.link'),
    t('auth.showcase.recovery.highlights.security'),
]);

const panelHighlights = computed(() =>
    isRecoveryMode.value
        ? recoveryHighlights.value.map((label) => ({
              label,
              icon: null,
          }))
        : valueHighlights.value.map((item) => ({
              label: item.label,
              icon: item.icon,
          })),
);
</script>

<template>
    <div
        class="min-h-svh bg-[linear-gradient(180deg,#fffdfb_0%,#fff9f5_58%,#fffdfb_100%)] text-slate-950 dark:bg-[radial-gradient(circle_at_top,_rgba(234,90,71,0.18),_transparent_24%),radial-gradient(circle_at_85%_18%,_rgba(6,182,212,0.14),_transparent_28%),linear-gradient(180deg,_rgba(7,12,26,1),_rgba(12,20,36,1))] dark:text-white"
    >
        <div
            class="mx-auto grid min-h-svh w-full max-w-[1480px] grid-cols-1 lg:grid-cols-[minmax(0,42rem)_minmax(24rem,36rem)] lg:justify-center"
        >
            <section
                class="relative flex min-h-svh flex-col justify-center px-5 py-8 sm:px-8 lg:px-12 xl:px-14"
            >
                <div
                    class="absolute inset-x-0 top-0 h-48 bg-[radial-gradient(circle_at_top,_rgba(234,90,71,0.1),_transparent_58%)]"
                />

                <div
                    class="relative z-10 mx-auto flex w-full max-w-[38rem] flex-col gap-7"
                >
                    <Link :href="home()" class="inline-flex w-fit items-center">
                        <AppLogo />
                        <span class="sr-only">{{ t('app.name') }}</span>
                    </Link>

                    <div class="space-y-4">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-[#f2dfd8] bg-[#fff7f4] px-3 py-1 text-[11px] font-semibold tracking-[0.16em] text-[#b65642] uppercase shadow-sm dark:border-white/12 dark:bg-white/8 dark:text-slate-200"
                        >
                            <ArrowUpRight class="size-3.5" />
                            {{ t('auth.showcase.eyebrow') }}
                        </div>

                        <div class="space-y-3">
                            <h1
                                v-if="title"
                                class="text-4xl leading-none font-semibold tracking-[-0.04em] text-slate-950 sm:text-5xl dark:text-white"
                            >
                                {{ title }}
                            </h1>
                            <p
                                v-if="description"
                                class="max-w-2xl text-base leading-7 text-slate-600 dark:text-slate-300"
                            >
                                {{ description }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-[2rem] border border-[#ece4dc] bg-white/96 p-6 shadow-[0_34px_90px_-54px_rgba(15,23,42,0.32)] backdrop-blur sm:p-8 dark:border-white/10 dark:bg-slate-950/72 dark:shadow-[0_34px_90px_-54px_rgba(2,6,23,0.75)]"
                    >
                        <slot />
                    </div>
                </div>
            </section>

            <aside
                class="relative hidden overflow-hidden border-l border-[#efe2d8] bg-[linear-gradient(180deg,#fffaf6_0%,#fff3ec_100%)] lg:flex lg:min-h-svh lg:items-center lg:justify-center dark:border-white/8 dark:bg-[linear-gradient(180deg,#0f1b31_0%,#0d1729_100%)]"
                data-test="auth-showcase-panel"
            >
                <div
                    class="absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(234,90,71,0.12),transparent_28%),radial-gradient(circle_at_82%_28%,rgba(242,170,132,0.15),transparent_28%),radial-gradient(circle_at_50%_78%,rgba(251,191,36,0.12),transparent_22%)]"
                />

                <div
                    class="relative z-10 flex w-full max-w-[33rem] flex-col gap-7 px-8 py-16 xl:px-10"
                >
                    <div class="max-w-md space-y-4">
                        <p
                            class="text-[11px] font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            {{
                                isRecoveryMode
                                    ? t('auth.showcase.recovery.panelLabel')
                                    : t('auth.showcase.panelLabel')
                            }}
                        </p>
                        <h2
                            class="text-[2.15rem] leading-[1.02] font-semibold tracking-[-0.04em] text-slate-950 dark:text-white"
                        >
                            {{
                                isRecoveryMode
                                    ? t('auth.showcase.recovery.title')
                                    : t('auth.showcase.title')
                            }}
                        </h2>
                        <p
                            class="text-base leading-7 text-slate-600 dark:text-slate-300"
                        >
                            {{
                                isRecoveryMode
                                    ? t('auth.showcase.recovery.description')
                                    : t('auth.showcase.description')
                            }}
                        </p>
                    </div>

                    <div v-if="!isRecoveryMode" class="space-y-4">
                        <article
                            v-for="item in showcaseItems"
                            :key="item.title"
                            class="rounded-[1.75rem] border border-white/90 bg-white/92 px-5 py-4 shadow-[0_22px_40px_-30px_rgba(15,23,42,0.2)] backdrop-blur dark:border-white/10 dark:bg-white/6 dark:shadow-none"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div class="min-w-0 space-y-1">
                                    <p
                                        class="truncate text-base font-semibold text-slate-950 dark:text-white"
                                    >
                                        {{ item.title }}
                                    </p>
                                    <p
                                        class="truncate text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ item.meta }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p
                                        class="text-sm font-semibold"
                                        :class="
                                            item.variant === 'positive'
                                                ? 'text-emerald-600'
                                                : item.variant === 'expense'
                                                  ? 'text-rose-600 dark:text-rose-300'
                                                  : 'text-slate-700 dark:text-slate-200'
                                        "
                                    >
                                        {{ item.amount }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-slate-400 dark:text-slate-500"
                                    >
                                        {{
                                            t('auth.showcase.transactionBadge')
                                        }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-[2rem] border border-white/88 bg-white/88 p-6 shadow-[0_26px_48px_-34px_rgba(15,23,42,0.2)] backdrop-blur dark:border-white/10 dark:bg-white/6 dark:shadow-none"
                        data-test="auth-recovery-visual"
                    >
                        <div
                            class="flex items-center justify-center rounded-[1.75rem] bg-[linear-gradient(180deg,#fff8f3_0%,#fffdfb_100%)] p-8"
                        >
                            <div class="relative h-64 w-full max-w-[21rem]">
                                <div
                                    class="absolute right-6 bottom-7 left-8 rounded-[1.5rem] border-4 border-[#86a57b] bg-[#fffef7] shadow-[0_22px_40px_-30px_rgba(15,23,42,0.25)]"
                                >
                                    <div
                                        class="border-b border-[#dce6d7] px-6 py-5"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="rounded-xl bg-[#fff3cc] p-3 text-[#d48a20]"
                                            >
                                                <KeyRound class="size-5" />
                                            </div>
                                            <div class="space-y-1">
                                                <p
                                                    class="text-sm font-semibold text-slate-950"
                                                >
                                                    {{
                                                        t(
                                                            'auth.showcase.recovery.cardTitle',
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    class="text-xs text-slate-500"
                                                >
                                                    {{
                                                        t(
                                                            'auth.showcase.recovery.cardDescription',
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-3 px-6 py-5">
                                        <div
                                            v-for="item in recoveryHighlights"
                                            :key="item"
                                            class="flex items-center gap-3 rounded-2xl bg-[#f8fbf6] px-4 py-3"
                                        >
                                            <CircleCheck
                                                class="size-4 shrink-0 text-[#86a57b]"
                                            />
                                            <p class="text-sm text-slate-700">
                                                {{ item }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="absolute top-2 left-0 rotate-[-8deg] rounded-[1.5rem] border-4 border-[#f4c04a] bg-white px-5 py-6 shadow-[0_18px_34px_-24px_rgba(234,90,71,0.3)]"
                                >
                                    <Mail class="size-8 text-[#ea5a47]" />
                                </div>

                                <div
                                    class="absolute right-0 bottom-0 rotate-[3deg] rounded-[1.2rem] border-4 border-[#74a6a0] bg-[#f3fbfa] px-4 py-5 shadow-[0_18px_34px_-24px_rgba(15,23,42,0.22)]"
                                >
                                    <ShieldCheck
                                        class="size-8 text-[#4f8d87]"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 xl:grid-cols-3">
                        <div
                            v-for="item in panelHighlights"
                            :key="item.label"
                            class="rounded-2xl border border-white/85 bg-white/82 px-4 py-3 backdrop-blur"
                        >
                            <component
                                v-if="item.icon"
                                :is="item.icon"
                                class="mb-3 size-4 text-[#ea5a47]"
                            />
                            <p
                                class="text-sm leading-6 font-medium text-slate-700"
                            >
                                {{ item.label }}
                            </p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <PublicCookieConsent />
    </div>
</template>
