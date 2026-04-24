<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    ChartColumn,
    Calculator,
    CalendarDays,
    LayoutGrid,
    ScrollText,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TransactionsMonthNavigator from '@/components/TransactionsMonthNavigator.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { budgetPlanning, dashboard, reports } from '@/routes';
import { index as recurringEntries } from '@/routes/recurring-entries';
import { index as transactions } from '@/routes/transactions';
import type { NavItem } from '@/types';

const { t } = useI18n();
const page = usePage();
const reportsEnabled = computed(
    () => page.props.features?.reports_enabled === true,
);

const mainNavItems = computed<NavItem[]>(() =>
    [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: t('nav.planning'),
            href: budgetPlanning(),
            icon: Calculator,
        },
        {
            title: t('nav.recurring'),
            href: recurringEntries(),
            icon: CalendarDays,
        },
        {
            title: t('nav.transactions'),
            href: transactions(),
            icon: ScrollText,
        },
        reportsEnabled.value
            ? {
                  title: t('nav.reports'),
                  href: reports(),
                  icon: ChartColumn,
              }
            : null,
    ].filter((item): item is NavItem => item !== null),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <button
                            type="button"
                            class="app-touch-interactive"
                            data-app-touch-target
                            @click="router.visit(dashboard().url)"
                        >
                            <AppLogo />
                        </button>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <TransactionsMonthNavigator />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
