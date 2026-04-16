<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, LogOut, Settings, Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
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
                    <Link
                        :href="settingsIndex()"
                        class="flex items-center gap-2 rounded-xl border border-sidebar-border/70 px-3 py-2 text-sm font-medium transition hover:bg-sidebar-accent"
                        data-test="sidebar-menu-button"
                        @click="closeMobileSidebar"
                    >
                        <Settings class="size-4" />
                        <span>{{ t('app.userMenu.settings') }}</span>
                    </Link>

                    <Link
                        v-if="user.is_admin"
                        :href="
                            adminIndex({
                                query: {
                                    mobile: 'launcher',
                                },
                            })
                        "
                        class="flex items-center gap-2 rounded-xl border border-sidebar-border/70 px-3 py-2 text-sm font-medium transition hover:bg-sidebar-accent"
                        @click="closeMobileSidebar"
                    >
                        <Shield class="size-4" />
                        <span>{{ t('app.userMenu.admin') }}</span>
                    </Link>

                    <Link
                        :href="
                            user.is_impersonated
                                ? leaveImpersonation()
                                : logout()
                        "
                        as="button"
                        class="flex w-full items-center gap-2 rounded-xl border border-sidebar-border/70 px-3 py-2 text-left text-sm font-medium transition hover:bg-sidebar-accent"
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
                    </Link>
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
