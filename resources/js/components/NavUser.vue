<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, LogOut, Settings, Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ThemePreferenceControl from '@/components/ThemePreferenceControl.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { logout } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { leave as leaveImpersonation } from '@/routes/admin/impersonate';
import { index as settingsIndex } from '@/routes/settings';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { t } = useI18n();
const { isMobile, setOpenMobile, state } = useSidebar();

function closeMobileSidebar(): void {
    setOpenMobile(false);
}

function handleLogout(): void {
    closeMobileSidebar();
    router.flushAll();

    const exitRoute = user.value.is_impersonated
        ? leaveImpersonation()
        : logout();

    router.visit(exitRoute.url, {
        method: exitRoute.method,
    });
}

function visitMobileMenuItem(url: string): void {
    closeMobileSidebar();
    router.visit(url);
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <div v-if="isMobile" class="space-y-3">
                <div
                    class="rounded-2xl border border-sidebar-border/70 bg-sidebar-accent/35 p-3"
                >
                    <UserInfo :user="user" :show-email="true" :compact="true" />
                </div>

                <div class="space-y-2">
                    <div
                        class="rounded-2xl border border-sidebar-border/70 bg-sidebar-accent/35 p-3"
                    >
                        <ThemePreferenceControl
                            variant="inline"
                            tone="sidebar"
                        />
                    </div>

                    <button
                        type="button"
                        class="app-touch-interactive flex items-center gap-2 rounded-xl border border-sidebar-border/70 px-3 py-2 text-sm font-medium text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:bg-sidebar-accent focus-visible:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring/50 focus-visible:outline-none"
                        data-test="sidebar-menu-button"
                        data-app-touch-target
                        @click="visitMobileMenuItem(settingsIndex().url)"
                    >
                        <Settings class="size-4" />
                        <span>{{ t('app.userMenu.settings') }}</span>
                    </button>

                    <button
                        v-if="user.is_admin"
                        type="button"
                        class="app-touch-interactive flex items-center gap-2 rounded-xl border border-sidebar-border/70 px-3 py-2 text-sm font-medium text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:bg-sidebar-accent focus-visible:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring/50 focus-visible:outline-none"
                        data-app-touch-target
                        @click="
                            visitMobileMenuItem(
                                adminIndex({
                                    query: {
                                        mobile: 'launcher',
                                    },
                                }).url,
                            )
                        "
                    >
                        <Shield class="size-4" />
                        <span>{{ t('app.userMenu.admin') }}</span>
                    </button>

                    <button
                        type="button"
                        class="app-touch-interactive flex w-full items-center gap-2 rounded-xl border border-sidebar-border/70 px-3 py-2 text-left text-sm font-medium text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:bg-sidebar-accent focus-visible:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring/50 focus-visible:outline-none"
                        data-app-touch-target
                        @click="handleLogout"
                    >
                        <LogOut class="size-4" />
                        <span>
                            {{
                                user.is_impersonated
                                    ? t('app.userMenu.leaveImpersonation')
                                    : t('app.userMenu.logout')
                            }}
                        </span>
                    </button>
                </div>
            </div>

            <DropdownMenu v-else>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="user" />
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
