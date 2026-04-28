<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import {
    ArrowLeft,
    ChartBarBig,
    ChartColumn,
    Landmark,
    LayoutPanelTop,
    PiggyBank,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { budgetPlanning, reports } from '@/routes';
import type { NavItem, ReportLauncherSection } from '@/types';

type ReportNavItem = NavItem & {
    key: string;
    summary: string;
};

const props = defineProps<{
    reportSections: ReportLauncherSection[];
}>();

const { t } = useI18n();
const page = usePage();
const currentUrl = computed(
    () =>
        new URL(
            String(page.url ?? '/'),
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        ),
);
const mobileLauncherHref = computed(() => reports());
const isReportsRoot = computed(() => currentUrl.value.pathname === '/reports');
const isMobileLauncher = computed(
    () =>
        isReportsRoot.value ||
        currentUrl.value.searchParams.get('mobile') === 'launcher',
);
const isMobileViewport = useMediaQuery('(max-width: 767px)');
const showMobileLauncher = computed(
    () => isMobileViewport.value && isMobileLauncher.value,
);

const sectionIcons = {
    kpis: LayoutPanelTop,
    categories: ChartColumn,
    category_analysis: ChartBarBig,
    accounts: Landmark,
} as const;

const sidebarNavItems = computed<ReportNavItem[]>(() => [
    ...props.reportSections.map((section) => ({
        title: section.title,
        href: section.href,
        key: section.key,
        icon:
            sectionIcons[section.key as keyof typeof sectionIcons] ??
            ChartColumn,
        summary: section.summary,
    })),
    {
        title: t('reports.planning.title'),
        href: budgetPlanning(),
        key: 'planning',
        icon: PiggyBank,
        summary: t('reports.planning.description'),
    },
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
const activeReportItem = computed<ReportNavItem | null>(
    () =>
        sidebarNavItems.value.find((item) => isCurrentOrParentUrl(item.href)) ??
        null,
);
</script>

<template>
    <div class="px-4 py-5 md:px-6 md:py-6">
        <div
            v-if="showMobileLauncher"
            class="md:hidden"
            data-test="reports-mobile-launcher"
        >
            <div
                class="rounded-[1.75rem] border border-border/80 bg-card/92 px-5 py-5 text-card-foreground shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                >
                    {{ t('reports.areaLabel') }}
                </p>
                <h1
                    class="mt-2 text-[1.65rem] leading-tight font-semibold tracking-[-0.03em] text-foreground"
                >
                    {{ t('reports.title') }}
                </h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    {{ t('reports.hero.description') }}
                </p>
            </div>

            <nav
                class="mt-4 grid grid-cols-2 gap-3"
                :aria-label="t('reports.navigationLabel')"
            >
                <Link
                    v-for="item in sidebarNavItems"
                    :key="`mobile-${item.key}`"
                    :href="item.href"
                    :class="[
                        'group rounded-3xl border px-4 py-4 transition-all',
                        isCurrentOrParentUrl(item.href)
                            ? 'border-foreground bg-foreground text-background shadow-[0_18px_40px_-28px_rgba(15,23,42,0.45)]'
                            : 'border-border/80 bg-card/92 text-card-foreground shadow-[0_18px_40px_-32px_rgba(15,23,42,0.18)] hover:border-border hover:bg-accent/45',
                    ]"
                >
                    <div
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-2xl border',
                            isCurrentOrParentUrl(item.href)
                                ? 'border-background/15 bg-background/10 text-background'
                                : 'border-border bg-muted text-muted-foreground',
                        ]"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                    </div>

                    <div class="mt-4 min-w-0">
                        <p class="text-sm leading-5 font-semibold">
                            {{ item.title }}
                        </p>
                        <p
                            class="mt-1 line-clamp-2 text-[11px] leading-4"
                            :class="
                                isCurrentOrParentUrl(item.href)
                                    ? 'text-background/72'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ item.summary }}
                        </p>
                    </div>
                </Link>
            </nav>
        </div>

        <div class="hidden md:block">
            <Heading
                :title="t('reports.title')"
                :description="t('reports.description')"
            />
        </div>

        <div
            v-if="!showMobileLauncher"
            class="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]"
        >
            <aside
                class="hidden space-y-4 md:block"
                data-test="reports-desktop-sidebar"
            >
                <div
                    class="overflow-hidden rounded-[1.75rem] border border-border/80 bg-card/90 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur"
                >
                    <div
                        class="border-b border-border/70 bg-linear-to-br from-foreground/6 via-accent/80 to-secondary px-5 py-6 text-foreground"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.24em] text-muted-foreground uppercase"
                        >
                            {{ t('reports.areaLabel') }}
                        </p>
                        <h2 class="mt-3 text-lg font-semibold tracking-tight">
                            {{ t('reports.sidebarTitle') }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ t('reports.hero.description') }}
                        </p>
                    </div>

                    <nav
                        class="space-y-2 p-3"
                        :aria-label="t('reports.navigationLabel')"
                    >
                        <Link
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            :href="item.href"
                            :class="[
                                'group flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-left transition-all',
                                isCurrentOrParentUrl(item.href)
                                    ? 'bg-foreground text-background shadow-[0_20px_44px_-28px_rgba(15,23,42,0.65)]'
                                    : 'text-foreground hover:bg-accent/65',
                            ]"
                        >
                            <div
                                :class="[
                                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border transition-colors',
                                    isCurrentOrParentUrl(item.href)
                                        ? 'border-background/15 bg-background/10 text-background'
                                        : 'border-border/80 bg-muted/80 text-muted-foreground',
                                ]"
                            >
                                <component :is="item.icon" class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <p
                                    :class="[
                                        'truncate text-sm font-medium transition-colors',
                                        isCurrentOrParentUrl(item.href)
                                            ? 'text-background'
                                            : 'text-foreground',
                                    ]"
                                >
                                    {{ item.title }}
                                </p>
                                <p
                                    class="mt-0.5 line-clamp-1 text-xs transition-colors"
                                    :class="
                                        isCurrentOrParentUrl(item.href)
                                            ? 'text-background/72'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ item.summary }}
                                </p>
                            </div>
                        </Link>
                    </nav>
                </div>
            </aside>

            <div class="min-w-0">
                <div
                    class="mb-4 flex items-center gap-3 md:hidden"
                    data-test="reports-mobile-page-header"
                >
                    <Link
                        :href="mobileLauncherHref"
                        class="flex h-11 w-11 items-center justify-center rounded-2xl border border-border/80 bg-card/92 text-muted-foreground shadow-sm transition hover:bg-accent hover:text-accent-foreground"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-[1.65rem] leading-tight font-semibold tracking-[-0.03em] text-foreground"
                        >
                            {{ activeReportItem?.title ?? t('reports.title') }}
                        </h1>
                    </div>
                </div>

                <section class="space-y-4 md:space-y-6">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
