<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { show as showUserBilling } from '@/routes/admin/users/billing/index';
import type { AdminUserItem, PaginationLink } from '@/types';

const emit = defineEmits<{
    ban: [user: AdminUserItem];
    suspend: [user: AdminUserItem];
    reactivate: [user: AdminUserItem];
    updateRoles: [user: AdminUserItem];
    impersonate: [user: AdminUserItem];
}>();

const { t } = useI18n();

const props = defineProps<{
    users: AdminUserItem[];
    links: PaginationLink[];
    summary: string;
    currentPage: number;
    lastPage: number;
    loading?: boolean;
}>();

function roleTone(role: string): string {
    if (role === 'admin') {
        return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100';
    }

    if (role === 'staff') {
        return 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
}

function statusTone(status: string): string {
    if (status === 'active') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100';
    }

    if (status === 'suspended') {
        return 'border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-100';
    }

    return 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-100';
}

function subscriptionTone(status: string): string {
    if (status === 'active' || status === 'trialing') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100';
    }

    if (status === 'past_due') {
        return 'border-orange-200 bg-orange-50 text-orange-900 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-100';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace(/&amp;/g, '&');
}

function formatDate(value: string | null): string {
    if (!value) {
        return t('admin.users.support.labels.noContribution');
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    }).format(new Date(value));
}

function actionDisabledReason(user: AdminUserItem): string {
    if (user.roles.includes('admin')) {
        return t('admin.users.labels.protectedAdminUser');
    }

    if (!user.is_impersonable) {
        return t('admin.users.labels.noImpersonationConsent');
    }

    return t('admin.users.labels.limitedActions');
}

function isPreviousLink(link: PaginationLink): boolean {
    return link.label.includes('&laquo;') || link.label.includes('Previous');
}

function isNextLink(link: PaginationLink): boolean {
    return link.label.includes('&raquo;') || link.label.includes('Next');
}

function isNumericLink(link: PaginationLink): boolean {
    return /^\d+$/.test(paginationLabel(link.label).trim());
}

const previousLink = computed(
    () => props.links.find((link) => isPreviousLink(link)) ?? null,
);
const nextLink = computed(
    () => props.links.find((link) => isNextLink(link)) ?? null,
);
const pageLinks = computed(() =>
    props.links.filter((link) => isNumericLink(link)),
);
</script>

<template>
    <section
        class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white/95 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
    >
        <div
            class="flex flex-col gap-2 border-b border-slate-200/70 px-6 py-5 dark:border-slate-800"
        >
            <h2
                class="text-base font-semibold tracking-tight text-slate-950 dark:text-slate-50"
            >
                {{ summary }}
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{
                    loading
                        ? t('admin.users.list.loading')
                        : t('admin.users.list.description')
                }}
            </p>
        </div>

        <div class="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ t('admin.users.table.user') }}</TableHead>
                        <TableHead>{{
                            t('admin.users.table.roles')
                        }}</TableHead>
                        <TableHead>{{
                            t('admin.users.table.status')
                        }}</TableHead>
                        <TableHead>{{
                            t('admin.users.table.subscriptionStatus')
                        }}</TableHead>
                        <TableHead>{{
                            t('admin.users.table.support')
                        }}</TableHead>
                        <TableHead>{{ t('admin.users.table.plan') }}</TableHead>
                        <TableHead>{{
                            t('admin.users.table.emailVerification')
                        }}</TableHead>
                        <TableHead>{{
                            t('admin.users.table.impersonationConsent')
                        }}</TableHead>
                        <TableHead class="text-right">{{
                            t('admin.users.table.actions')
                        }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="user in users" :key="user.id">
                        <TableCell class="min-w-64">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <p
                                        class="font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{ user.full_name || user.email }}
                                    </p>
                                    <Badge
                                        v-if="user.roles.includes('admin')"
                                        class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-medium tracking-[0.12em] text-amber-900 uppercase dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                                    >
                                        {{
                                            t(
                                                'admin.users.labels.protectedUser',
                                            )
                                        }}
                                    </Badge>
                                </div>
                                <p
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ user.email }}
                                </p>
                            </div>
                        </TableCell>

                        <TableCell>
                            <div class="flex flex-wrap gap-2">
                                <Badge
                                    v-for="role in user.roles"
                                    :key="role"
                                    class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                                    :class="roleTone(role)"
                                >
                                    {{ t(`admin.users.roles.${role}`) }}
                                </Badge>
                            </div>
                        </TableCell>

                        <TableCell>
                            <Badge
                                class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                                :class="statusTone(user.status)"
                            >
                                {{ user.status_label }}
                            </Badge>
                        </TableCell>

                        <TableCell>
                            <Badge
                                class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                                :class="
                                    subscriptionTone(user.subscription_status)
                                "
                            >
                                {{ user.subscription_status_label }}
                            </Badge>
                        </TableCell>

                        <TableCell>
                            <div class="space-y-2">
                                <Badge
                                    class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                                    :class="subscriptionTone(user.support_state)"
                                >
                                    {{ user.support_state_label }}
                                </Badge>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t('admin.users.support.labels.lastContribution')
                                    }}:
                                    {{ formatDate(user.last_contribution_at) }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t('admin.users.support.labels.nextReminder')
                                    }}:
                                    {{ formatDate(user.next_support_reminder_at) }}
                                </p>
                            </div>
                        </TableCell>

                        <TableCell>
                            <div class="space-y-2">
                                <Badge
                                    class="rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-[11px] font-medium tracking-[0.08em] text-violet-900 uppercase dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-100"
                                >
                                    {{
                                        user.plan_code
                                            ? t(
                                                  `admin.users.plans.${user.plan_code}`,
                                              )
                                            : t(
                                                  'admin.users.labels.planUnavailable',
                                              )
                                    }}
                                </Badge>
                            </div>
                        </TableCell>

                        <TableCell>
                            <Badge
                                class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                                :class="
                                    user.email_verified_at
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100'
                                        : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'
                                "
                            >
                                {{
                                    user.email_verified_at
                                        ? t('admin.users.labels.emailVerified')
                                        : t(
                                              'admin.users.labels.emailNotVerified',
                                          )
                                }}
                            </Badge>
                        </TableCell>

                        <TableCell>
                            <Badge
                                class="rounded-full border px-2.5 py-1 text-[11px] uppercase"
                                :class="
                                    user.is_impersonable
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100'
                                        : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'
                                "
                            >
                                {{
                                    user.is_impersonable
                                        ? t(
                                              'admin.users.labels.impersonationAllowed',
                                          )
                                        : t(
                                              'admin.users.labels.impersonationDenied',
                                          )
                                }}
                            </Badge>
                        </TableCell>

                        <TableCell class="min-w-64">
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="rounded-xl"
                                    as-child
                                >
                                    <Link :href="showUserBilling({ user: user.uuid }).url">
                                        {{ t('admin.users.actions.support') }}
                                    </Link>
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="rounded-xl"
                                    :disabled="!user.can_impersonate"
                                    @click="emit('impersonate', user)"
                                >
                                    {{ t('admin.users.actions.impersonate') }}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="rounded-xl"
                                    :disabled="!user.can_manage_roles"
                                    @click="emit('updateRoles', user)"
                                >
                                    {{ t('admin.users.actions.roles') }}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="rounded-xl"
                                    :disabled="!user.can_suspend"
                                    @click="emit('suspend', user)"
                                >
                                    {{ t('admin.users.actions.suspend') }}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="rounded-xl border-rose-200 text-rose-700 hover:bg-rose-50 hover:text-rose-800 dark:border-rose-500/20 dark:text-rose-200 dark:hover:bg-rose-500/10"
                                    :disabled="!user.can_ban"
                                    @click="emit('ban', user)"
                                >
                                    {{ t('admin.users.actions.ban') }}
                                </Button>
                                <Button
                                    size="sm"
                                    class="rounded-xl"
                                    :disabled="!user.can_reactivate"
                                    @click="emit('reactivate', user)"
                                >
                                    {{ t('admin.users.actions.reactivate') }}
                                </Button>
                                <p
                                    v-if="
                                        !user.can_ban ||
                                        !user.can_suspend ||
                                        !user.can_reactivate ||
                                        !user.can_manage_roles ||
                                        !user.can_impersonate
                                    "
                                    class="w-full text-right text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ actionDisabledReason(user) }}
                                </p>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="lastPage > 1"
            class="flex flex-col gap-4 border-t border-slate-200/70 px-6 py-5 dark:border-slate-800"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ t('admin.users.pagination.summary') }}
                </p>
                <p
                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                >
                    {{
                        t('admin.users.pagination.page', {
                            current: currentPage,
                            last: lastPage,
                        })
                    }}
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        class="rounded-xl"
                        :disabled="previousLink?.url === null"
                        as-child
                    >
                        <Link
                            v-if="previousLink?.url"
                            :href="previousLink.url"
                            preserve-scroll
                            preserve-state
                        >
                            {{ t('admin.users.pagination.previous') }}
                        </Link>
                        <span v-else>{{
                            t('admin.users.pagination.previous')
                        }}</span>
                    </Button>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2">
                    <Button
                        v-for="link in pageLinks"
                        :key="`${link.label}-${link.url ?? 'null'}`"
                        :variant="link.active ? 'default' : 'outline'"
                        class="min-w-10 rounded-xl"
                        :disabled="link.url === null"
                        as-child
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            preserve-state
                        >
                            {{ paginationLabel(link.label) }}
                        </Link>
                        <span v-else>{{ paginationLabel(link.label) }}</span>
                    </Button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        class="rounded-xl"
                        :disabled="nextLink?.url === null"
                        as-child
                    >
                        <Link
                            v-if="nextLink?.url"
                            :href="nextLink.url"
                            preserve-scroll
                            preserve-state
                        >
                            {{ t('admin.users.pagination.next') }}
                        </Link>
                        <span v-else>{{
                            t('admin.users.pagination.next')
                        }}</span>
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
