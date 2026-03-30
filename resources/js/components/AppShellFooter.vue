<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import { index as imports } from '@/routes/imports';
import { edit as profile } from '@/routes/profile';
import { show as transactionsShow } from '@/routes/transactions';
import type { AppMeta, TransactionsNavigation } from '@/types';

const page = usePage();
const { t } = useI18n();

const appMeta = computed(() => page.props.app as AppMeta);
const displayedVersion = computed(
    () => appMeta.value.changelog.latest_release_label ?? appMeta.value.version,
);
const changelogHref = computed(
    () =>
        appMeta.value.changelog.latest_release_url ??
        appMeta.value.changelog_url,
);
const navigation = computed(
    () => page.props.transactionsNavigation as TransactionsNavigation | null,
);

const transactionsHref = computed(() => {
    if (navigation.value?.context.year && navigation.value?.context.month) {
        return transactionsShow({
            year: navigation.value.context.year,
            month: navigation.value.context.month,
        });
    }

    const now = new Date();

    return transactionsShow({
        year: now.getFullYear(),
        month: now.getMonth() + 1,
    });
});

const links = computed(() => [
    {
        label: t('app.shell.footerLinks.dashboard'),
        href: dashboard(),
    },
    {
        label: t('app.shell.footerLinks.transactions'),
        href: transactionsHref.value,
    },
    {
        label: t('app.shell.footerLinks.imports'),
        href: imports(),
    },
    {
        label: t('app.shell.footerLinks.settings'),
        href: profile(),
    },
]);

const showEnvironment = computed(
    () =>
        appMeta.value.environment && appMeta.value.environment !== 'production',
);
</script>

<template>
    <footer
        class="mt-8 border-t border-slate-200/80 bg-white/60 px-4 py-5 backdrop-blur-sm md:px-6 dark:border-slate-800 dark:bg-slate-950/40"
    >
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p
                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                    >
                        {{ t('app.name') }}
                    </p>
                    <Badge variant="secondary" class="rounded-full">
                        {{
                            t('app.shell.footerVersion', {
                                version: displayedVersion,
                            })
                        }}
                    </Badge>
                    <Link
                        :href="changelogHref"
                        prefetch
                        class="inline-flex items-center gap-1 rounded-full border border-slate-200/80 bg-white px-3 py-1 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-950 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:text-slate-50"
                    >
                        <span>{{ t('app.userMenu.version.changelog') }}</span>
                        <ArrowUpRight class="size-3.5 opacity-70" />
                    </Link>
                    <Badge
                        v-if="showEnvironment"
                        variant="secondary"
                        class="rounded-full"
                    >
                        {{
                            t('app.shell.footerEnvironment', {
                                environment: appMeta.environment,
                            })
                        }}
                    </Badge>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ t('app.shell.footerTagline') }}
                </p>
            </div>

            <nav
                aria-label="Application footer links"
                class="flex flex-wrap items-center gap-2"
            >
                <Link
                    v-for="link in links"
                    :key="link.label"
                    :href="link.href"
                    prefetch
                    class="inline-flex items-center rounded-full border border-slate-200/80 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-950 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:text-slate-50"
                >
                    {{ link.label }}
                </Link>
            </nav>
        </div>
    </footer>
</template>
