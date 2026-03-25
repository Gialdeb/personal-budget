<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CircleCheckBig,
    SearchX,
    Shield,
    UserRoundCog,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ban as banUser,
    reactivate as reactivateUser,
    suspend as suspendUser,
} from '@/actions/App/Http/Controllers/Admin/UserStatusController';
import AdminUserFilters from '@/components/admin/users/AdminUserFilters.vue';
import AdminUsersTable from '@/components/admin/users/AdminUsersTable.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { impersonate, index, users as adminUsersRoute } from '@/routes/admin';
import { update as updateUserRoles } from '@/routes/admin/users/roles';
import type {
    AdminUserItem,
    AdminUsersPageProps,
    BreadcrumbItem,
} from '@/types';

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

type UserAction = 'ban' | 'suspend' | 'reactivate';

const props = defineProps<AdminUsersPageProps>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('admin.sections.overview'),
        href: index(),
    },
    {
        title: t('admin.sections.users'),
        href: adminUsersRoute(),
    },
];

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

const search = ref(props.filters.search);
const role = ref(props.filters.role);
const status = ref(props.filters.status);
const plan = ref(props.filters.plan);
const selectedUser = ref<AdminUserItem | null>(null);
const currentAction = ref<UserAction | null>(null);
const roleDialogOpen = ref(false);
const statusDialogOpen = ref(false);
const feedback = ref<FeedbackState | null>(null);
let filterTimeout: ReturnType<typeof setTimeout> | null = null;
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

const statusForm = useForm({
    reason: '',
});

const rolesForm = useForm<{
    roles: Array<'user' | 'staff'>;
}>({
    roles: ['user'],
});

const summaryCards = computed(() => [
    {
        label: t('admin.users.summary.total'),
        value: props.users.total,
        tone: 'text-slate-950 dark:text-slate-50',
    },
    {
        label: t('admin.users.summary.active'),
        value: props.users.data.filter((item) => item.status === 'active')
            .length,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: t('admin.users.summary.staff'),
        value: props.users.data.filter((item) => item.roles.includes('staff'))
            .length,
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        label: t('admin.users.summary.impersonable'),
        value: props.users.data.filter((item) => item.is_impersonable).length,
        tone: 'text-amber-700 dark:text-amber-300',
    },
]);

const listSummary = computed(() => {
    if (props.users.total === 0) {
        return t('admin.users.list.emptySummary');
    }

    return t('admin.users.list.summary', {
        from: props.users.from ?? 0,
        to: props.users.to ?? 0,
        total: props.users.total,
    });
});

const actionTitle = computed(() => {
    if (currentAction.value === 'ban') {
        return t('admin.users.dialogs.ban.title');
    }

    if (currentAction.value === 'suspend') {
        return t('admin.users.dialogs.suspend.title');
    }

    return t('admin.users.dialogs.reactivate.title');
});

const actionDescription = computed(() => {
    if (!selectedUser.value) {
        return '';
    }

    if (currentAction.value === 'ban') {
        return t('admin.users.dialogs.ban.description', {
            user: selectedUser.value.full_name || selectedUser.value.email,
        });
    }

    if (currentAction.value === 'suspend') {
        return t('admin.users.dialogs.suspend.description', {
            user: selectedUser.value.full_name || selectedUser.value.email,
        });
    }

    return t('admin.users.dialogs.reactivate.description', {
        user: selectedUser.value.full_name || selectedUser.value.email,
    });
});

const actionSubmitLabel = computed(() => {
    if (currentAction.value === 'ban') {
        return t('admin.users.actions.ban');
    }

    if (currentAction.value === 'suspend') {
        return t('admin.users.actions.suspend');
    }

    return t('admin.users.actions.reactivate');
});

const roleOptions = computed(() => [
    { value: 'user' as const, label: t('admin.users.roles.user') },
    { value: 'staff' as const, label: t('admin.users.roles.staff') },
]);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        role.value = filters.role;
        status.value = filters.status;
        plan.value = filters.plan;
    },
    { deep: true },
);

watch(
    flash,
    (currentFlash) => {
        if (currentFlash.success) {
            feedback.value = {
                variant: 'default',
                title: t('admin.users.feedback.successTitle'),
                message: currentFlash.success,
            };
        }
    },
    { immediate: true, deep: true },
);

watch(
    pageErrors,
    (errors) => {
        const message = errors.user ?? errors.roles ?? errors.reason;

        if (message) {
            feedback.value = {
                variant: 'destructive',
                title: t('admin.users.feedback.errorTitle'),
                message,
            };
        }
    },
    { immediate: true, deep: true },
);

watch(feedback, (value) => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
        feedbackTimeout = null;
    }

    if (!value) {
        return;
    }

    feedbackTimeout = setTimeout(() => {
        feedback.value = null;
        feedbackTimeout = null;
    }, 4000);
});

watch([search, role, status, plan], () => {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }

    filterTimeout = setTimeout(() => {
        router.get(
            adminUsersRoute.url({
                query: {
                    search:
                        search.value.trim() === '' ? null : search.value.trim(),
                    role: role.value === 'all' ? null : role.value,
                    status: status.value === 'all' ? null : status.value,
                    plan: plan.value === 'all' ? null : plan.value,
                },
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }, 250);
});

onUnmounted(() => {
    if (filterTimeout) {
        clearTimeout(filterTimeout);
    }

    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
    }
});

function resetFilters(): void {
    search.value = '';
    role.value = 'all';
    status.value = 'all';
    plan.value = 'all';
}

function openStatusDialog(action: UserAction, user: AdminUserItem): void {
    selectedUser.value = user;
    currentAction.value = action;
    statusForm.reset();
    statusForm.clearErrors();
    statusDialogOpen.value = true;
}

function openRoleDialog(user: AdminUserItem): void {
    selectedUser.value = user;
    rolesForm.clearErrors();
    rolesForm.roles = user.roles.filter(
        (item): item is 'user' | 'staff' => item === 'user' || item === 'staff',
    );

    if (rolesForm.roles.length === 0) {
        rolesForm.roles = ['user'];
    }

    roleDialogOpen.value = true;
}

function submitStatusAction(): void {
    if (!selectedUser.value || !currentAction.value) {
        return;
    }

    const actionUrl = {
        ban: banUser(selectedUser.value).url,
        suspend: suspendUser(selectedUser.value).url,
        reactivate: reactivateUser(selectedUser.value).url,
    }[currentAction.value];

    statusForm.patch(actionUrl, {
        preserveScroll: true,
        onSuccess: () => {
            statusDialogOpen.value = false;
            statusForm.reset();
        },
    });
}

function submitRoles(): void {
    if (!selectedUser.value) {
        return;
    }

    rolesForm.patch(updateUserRoles(selectedUser.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            roleDialogOpen.value = false;
        },
    });
}

function toggleRole(
    roleValue: 'user' | 'staff',
    checked: boolean | 'indeterminate',
): void {
    if (checked !== true) {
        rolesForm.roles = rolesForm.roles.filter((item) => item !== roleValue);

        return;
    }

    if (!rolesForm.roles.includes(roleValue)) {
        rolesForm.roles = [...rolesForm.roles, roleValue];
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.users.title')" />

        <AdminLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-gradient-to-r from-sky-500/10 via-cyan-500/10 to-emerald-500/10 px-8 py-7 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] tracking-[0.2em] text-sky-900 uppercase dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100"
                            >
                                {{ t('admin.shell.eyebrow') }}
                            </Badge>
                            <Heading
                                variant="small"
                                :title="t('admin.users.title')"
                                :description="t('admin.users.description')"
                            />
                        </div>

                        <div
                            class="rounded-[1.5rem] border border-slate-200/80 bg-white/80 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300"
                        >
                            {{ t('admin.users.headerNote') }}
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-8 py-8">
                    <Alert
                        v-if="feedback"
                        :variant="feedback.variant"
                        class="rounded-[1.5rem]"
                    >
                        <CircleCheckBig
                            v-if="feedback.variant === 'default'"
                            class="h-4 w-4"
                        />
                        <AlertTriangle v-else class="h-4 w-4" />
                        <AlertTitle>{{ feedback.title }}</AlertTitle>
                        <AlertDescription>{{
                            feedback.message
                        }}</AlertDescription>
                    </Alert>

                    <div class="grid gap-4 xl:grid-cols-4">
                        <div
                            v-for="item in summaryCards"
                            :key="item.label"
                            class="rounded-[1.5rem] border border-slate-200/80 bg-white/90 p-5 shadow-none dark:border-slate-800 dark:bg-slate-950/70"
                        >
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ item.label }}
                            </p>
                            <p
                                class="mt-3 text-2xl font-semibold tracking-tight"
                                :class="item.tone"
                            >
                                {{ item.value }}
                            </p>
                        </div>
                    </div>

                    <AdminUserFilters
                        v-model:search="search"
                        v-model:role="role"
                        v-model:status="status"
                        v-model:plan="plan"
                        :role-options="props.options.roles"
                        :status-options="props.options.statuses"
                        :plan-options="props.options.plans"
                        @reset="resetFilters"
                    />

                    <div
                        v-if="props.users.data.length === 0"
                        class="flex min-h-72 flex-col items-center justify-center rounded-[1.75rem] border border-dashed border-slate-300/90 bg-slate-50/80 px-6 text-center dark:border-slate-700 dark:bg-slate-900/60"
                    >
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-[1.5rem] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950"
                        >
                            <SearchX
                                class="h-7 w-7 text-slate-700 dark:text-slate-200"
                            />
                        </div>
                        <Badge
                            class="mt-5 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] tracking-[0.2em] text-slate-700 uppercase dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200"
                        >
                            <Shield class="mr-1.5 h-3.5 w-3.5" />
                            {{ t('admin.badge') }}
                        </Badge>
                        <h1
                            class="mt-5 text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                        >
                            {{ t('admin.users.empty.title') }}
                        </h1>
                        <p
                            class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{ t('admin.users.empty.description') }}
                        </p>
                    </div>

                    <AdminUsersTable
                        v-else
                        :users="props.users.data"
                        :links="props.users.links"
                        :summary="listSummary"
                        :current-page="props.users.current_page"
                        :last-page="props.users.last_page"
                        @ban="openStatusDialog('ban', $event)"
                        @suspend="openStatusDialog('suspend', $event)"
                        @reactivate="openStatusDialog('reactivate', $event)"
                        @update-roles="openRoleDialog"
                        @impersonate="
                            router.get(impersonate({ id: $event.id }).url)
                        "
                    />
                </div>
            </section>

            <Dialog v-model:open="statusDialogOpen">
                <DialogContent class="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{{ actionTitle }}</DialogTitle>
                        <DialogDescription>
                            {{ actionDescription }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="admin-user-reason">{{
                            t('admin.users.dialogs.reasonLabel')
                        }}</Label>
                        <textarea
                            id="admin-user-reason"
                            v-model="statusForm.reason"
                            rows="4"
                            class="min-h-28 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-xs ring-0 transition outline-none focus:border-slate-400 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-600"
                            :placeholder="
                                t('admin.users.dialogs.reasonPlaceholder')
                            "
                        />
                        <InputError :message="statusForm.errors.reason" />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            class="rounded-xl"
                            @click="statusDialogOpen = false"
                        >
                            {{ t('app.common.cancel') }}
                        </Button>
                        <Button
                            class="rounded-xl"
                            :variant="
                                currentAction === 'ban'
                                    ? 'destructive'
                                    : 'default'
                            "
                            :disabled="statusForm.processing"
                            @click="submitStatusAction"
                        >
                            {{ actionSubmitLabel }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="roleDialogOpen">
                <DialogContent class="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{{
                            t('admin.users.dialogs.roles.title')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                t('admin.users.dialogs.roles.description', {
                                    user:
                                        selectedUser?.full_name ||
                                        selectedUser?.email ||
                                        '',
                                })
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <div
                            class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                >
                                    <UserRoundCog class="h-4 w-4" />
                                </div>
                                <div class="space-y-1">
                                    <p
                                        class="font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            selectedUser?.full_name ||
                                            selectedUser?.email
                                        }}
                                    </p>
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'admin.users.dialogs.roles.helper',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label
                                v-for="option in roleOptions"
                                :key="option.value"
                                class="flex items-start gap-3 rounded-2xl border border-slate-200/80 p-4 transition hover:border-slate-300 dark:border-slate-800 dark:hover:border-slate-700"
                            >
                                <Checkbox
                                    :checked="
                                        rolesForm.roles.includes(option.value)
                                    "
                                    @update:checked="
                                        toggleRole(option.value, $event)
                                    "
                                />
                                <div class="space-y-1">
                                    <p
                                        class="font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{ option.label }}
                                    </p>
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                `admin.users.rolesDescriptions.${option.value}`,
                                            )
                                        }}
                                    </p>
                                </div>
                            </label>
                        </div>

                        <InputError :message="rolesForm.errors.roles" />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            class="rounded-xl"
                            @click="roleDialogOpen = false"
                        >
                            {{ t('app.common.cancel') }}
                        </Button>
                        <Button
                            class="rounded-xl"
                            :disabled="rolesForm.processing"
                            @click="submitRoles"
                        >
                            {{ t('admin.users.actions.roles') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    </AppLayout>
</template>
