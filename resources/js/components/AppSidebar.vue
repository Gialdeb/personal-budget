<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Calculator, LayoutGrid, ScrollText } from 'lucide-vue-next';
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
import { budgetPlanning, dashboard } from '@/routes';
import { index as transactions } from '@/routes/transactions';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Panoramica',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Preventivazione',
        href: budgetPlanning(),
        icon: Calculator,
    },
    {
        title: 'Transazioni',
        href: transactions(),
        icon: ScrollText,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
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
