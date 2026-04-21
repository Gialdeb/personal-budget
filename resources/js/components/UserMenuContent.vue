<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    ExternalLink,
    LogOut,
    Settings,
    Shield,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import ThemePreferenceControl from '@/components/ThemePreferenceControl.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { leave as leaveImpersonation } from '@/routes/admin/impersonate';
import { index as settingsIndex } from '@/routes/settings';
import type { AppMeta, User } from '@/types';

type Props = {
    user: User;
    adminHref?: string;
    settingsHref?: string;
};

const handleLogout = () => {
    router.flushAll();

    const exitRoute = props.user.is_impersonated
        ? leaveImpersonation()
        : logout();

    router.visit(exitRoute.url, {
        method: exitRoute.method,
    });
};

const { t } = useI18n();
const page = usePage();
const appMeta = computed(() => page.props.app as AppMeta);
const copiedVersion = ref(false);
const displayedVersion = computed(
    () => appMeta.value.changelog.latest_release_label ?? appMeta.value.version,
);
const changelogHref = computed(
    () =>
        appMeta.value.changelog.latest_release_url ??
        appMeta.value.changelog_url,
);

const props = defineProps<Props>();

async function copyVersion(): Promise<void> {
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(displayedVersion.value);
    copiedVersion.value = true;

    window.setTimeout(() => {
        copiedVersion.value = false;
    }, 1800);
}
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1 text-left text-sm">
            <UserInfo :user="user" :show-email="true" :compact="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem v-if="user.is_admin" :as-child="true">
            <Link
                class="app-touch-interactive block w-full cursor-pointer"
                :href="props.adminHref ?? adminIndex()"
                data-app-touch-target
                prefetch
            >
                <Shield class="mr-2 h-4 w-4" />
                {{ t('app.userMenu.admin') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="app-touch-interactive block w-full cursor-pointer"
                :href="props.settingsHref ?? settingsIndex()"
                data-app-touch-target
                prefetch
            >
                <Settings class="mr-2 h-4 w-4" />
                {{ t('app.userMenu.settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <ThemePreferenceControl />
    <LocaleSwitcher />
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <button
            type="button"
            class="app-touch-interactive block w-full cursor-pointer"
            data-app-touch-target
            @click="handleLogout"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{
                user.is_impersonated
                    ? t('app.userMenu.leaveImpersonation')
                    : t('app.userMenu.logout')
            }}
        </button>
    </DropdownMenuItem>
    <DropdownMenuSeparator />
    <div
        class="flex items-center gap-2 px-2 py-2 text-xs text-muted-foreground"
        :aria-label="
            t('app.userMenu.version.ariaLabel', { version: displayedVersion })
        "
        data-testid="user_menu_version"
    >
        <button
            type="button"
            class="app-touch-interactive group inline-flex items-center gap-1.5 rounded-md px-1.5 py-1 text-left transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
            data-app-touch-target
            :aria-label="
                t('app.userMenu.version.copy', { version: displayedVersion })
            "
            @click="copyVersion"
        >
            <span class="font-medium text-foreground">
                {{ displayedVersion }}
            </span>
            <Check
                v-if="copiedVersion"
                class="size-3.5 text-emerald-600 dark:text-emerald-400"
            />
            <Copy
                v-else
                class="size-3.5 opacity-70 transition group-hover:opacity-100"
            />
        </button>
        <span aria-hidden="true">·</span>
        <Link
            :href="changelogHref"
            prefetch
            class="app-touch-interactive inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-medium text-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
            data-app-touch-target
        >
            <span>{{ t('app.userMenu.version.changelog') }}</span>
            <ExternalLink class="size-3.5 opacity-70" />
        </Link>
    </div>
</template>
