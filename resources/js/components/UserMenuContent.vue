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
import { edit } from '@/routes/profile';
import type { AppMeta, User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
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

defineProps<Props>();

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
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem v-if="user.is_admin" :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="adminIndex()"
                prefetch
            >
                <Shield class="mr-2 h-4 w-4" />
                {{ t('app.userMenu.admin') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ t('app.userMenu.settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <LocaleSwitcher />
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="user.is_impersonated ? leaveImpersonation() : logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{
                user.is_impersonated
                    ? t('app.userMenu.leaveImpersonation')
                    : t('app.userMenu.logout')
            }}
        </Link>
    </DropdownMenuItem>
    <DropdownMenuSeparator />
    <div
        class="flex items-center gap-2 px-2 py-2 text-xs text-slate-500 dark:text-slate-400"
        :aria-label="
            t('app.userMenu.version.ariaLabel', { version: displayedVersion })
        "
        data-testid="user_menu_version"
    >
        <button
            type="button"
            class="group inline-flex items-center gap-1.5 rounded-md px-1 py-0.5 text-left transition hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:outline-none dark:hover:text-slate-50"
            :aria-label="
                t('app.userMenu.version.copy', { version: displayedVersion })
            "
            @click="copyVersion"
        >
            <span class="font-medium text-slate-700 dark:text-slate-200">
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
            class="inline-flex items-center gap-1 rounded-md px-1 py-0.5 font-medium text-slate-700 transition hover:text-slate-950 focus-visible:ring-2 focus-visible:ring-sky-500/50 focus-visible:outline-none dark:text-slate-200 dark:hover:text-slate-50"
        >
            <span>{{ t('app.userMenu.version.changelog') }}</span>
            <ExternalLink class="size-3.5 opacity-70" />
        </Link>
    </div>
</template>
