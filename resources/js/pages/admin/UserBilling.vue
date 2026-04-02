<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin/index';
import { users as adminUsers } from '@/routes/admin/index';
import { show as showUserBilling } from '@/routes/admin/users/billing/index';
import { update as updateBillingSubscription } from '@/routes/admin/users/billing/subscription/index';
import {
    assign as assignBillingTransaction,
    store as storeBillingTransaction,
    update as updateBillingTransaction,
} from '@/routes/admin/users/billing/transactions/index';
import type {
    AdminBillingTransactionItem,
    AdminUserBillingPageProps,
    BreadcrumbItem,
} from '@/types';

const props = defineProps<AdminUserBillingPageProps>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: t('admin.sections.overview'), href: adminIndex() },
    { title: t('admin.sections.users'), href: adminUsers() },
    {
        title: props.user.full_name || props.user.email,
        href: showUserBilling({ user: props.user.uuid }),
    },
];

const selectedTransaction = ref<AdminBillingTransactionItem | null>(
    props.transactions[0] ?? null,
);

const donationForm = useForm({
    billing_plan_code: 'supporter',
    provider: 'manual',
    provider_transaction_id: '',
    provider_event_id: '',
    customer_email: props.user.email,
    customer_name: props.user.full_name,
    currency: 'EUR',
    amount: '',
    status: 'paid',
    paid_at: '',
    received_at: '',
    is_recurring: false,
    apply_support_window: true,
    admin_notes: '',
});

const supportForm = useForm({
    status: props.user.support_status,
    billing_plan_code: props.user.support_plan_code ?? 'free',
    is_supporter: props.user.is_supporter,
    started_at: props.user.support_started_at
        ? props.user.support_started_at.slice(0, 16)
        : '',
    ends_at: props.user.support_window_ends_at
        ? props.user.support_window_ends_at.slice(0, 16)
        : '',
    next_reminder_at: props.user.next_support_reminder_at
        ? props.user.next_support_reminder_at.slice(0, 16)
        : '',
    admin_notes: props.user.admin_notes ?? '',
});

const deleteSubscriptionForm = useForm({});

const transactionForm = useForm({
    provider: selectedTransaction.value?.provider ?? 'manual',
    provider_transaction_id:
        selectedTransaction.value?.provider_transaction_id ?? '',
    provider_event_id: selectedTransaction.value?.provider_event_id ?? '',
    customer_email:
        selectedTransaction.value?.customer_email ?? props.user.email,
    customer_name:
        selectedTransaction.value?.customer_name ?? props.user.full_name,
    currency: selectedTransaction.value?.currency ?? 'EUR',
    amount: selectedTransaction.value?.amount ?? '',
    status: selectedTransaction.value?.status ?? 'paid',
    paid_at: selectedTransaction.value?.paid_at
        ? selectedTransaction.value.paid_at.slice(0, 16)
        : '',
    received_at: selectedTransaction.value?.received_at
        ? selectedTransaction.value.received_at.slice(0, 16)
        : '',
    is_recurring: selectedTransaction.value?.is_recurring ?? false,
    admin_notes: selectedTransaction.value?.admin_notes ?? '',
});

const assignForm = useForm({});

const supportSummary = computed(() => [
    {
        label: t('admin.users.billing.summary.accessPlan'),
        value: props.user.plan_code ?? 'free',
    },
    {
        label: t('admin.users.billing.summary.supportState'),
        value: props.user.support_state_label,
    },
    {
        label: t('admin.users.billing.summary.lastContribution'),
        value: formatDateTime(props.user.last_contribution_at),
    },
    {
        label: t('admin.users.billing.summary.nextReminder'),
        value: formatDateTime(props.user.next_support_reminder_at),
    },
]);

function formatDateTime(value: string | null): string {
    if (!value) {
        return t('admin.users.billing.empty.noValue');
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function editTransaction(transaction: AdminBillingTransactionItem): void {
    selectedTransaction.value = transaction;
    transactionForm.defaults({
        provider: transaction.provider,
        provider_transaction_id: transaction.provider_transaction_id ?? '',
        provider_event_id: transaction.provider_event_id ?? '',
        customer_email: transaction.customer_email ?? props.user.email,
        customer_name: transaction.customer_name ?? props.user.full_name,
        currency: transaction.currency,
        amount: transaction.amount,
        status: transaction.status,
        paid_at: transaction.paid_at ? transaction.paid_at.slice(0, 16) : '',
        received_at: transaction.received_at
            ? transaction.received_at.slice(0, 16)
            : '',
        is_recurring: transaction.is_recurring,
        admin_notes: transaction.admin_notes ?? '',
    });
    transactionForm.reset();
}

function submitDonation(): void {
    donationForm.post(storeBillingTransaction({ user: props.user.uuid }).url, {
        preserveScroll: true,
    });
}

function submitSupport(): void {
    supportForm
        .transform((data) => ({
            ...data,
            _method: 'patch',
        }))
        .post(updateBillingSubscription({ user: props.user.uuid }).url, {
            preserveScroll: true,
        });
}

function clearSubscription(): void {
    supportForm.status = 'free';
    supportForm.billing_plan_code = 'free';
    supportForm.is_supporter = false;
    supportForm.started_at = '';
    supportForm.ends_at = '';
    supportForm.next_reminder_at = '';

    submitSupport();
}

function deleteSubscription(): void {
    if (
        !window.confirm(
            t('admin.users.billing.confirmations.deleteSubscription'),
        )
    ) {
        return;
    }

    deleteSubscriptionForm
        .transform(() => ({
            _method: 'delete',
        }))
        .post(updateBillingSubscription({ user: props.user.uuid }).url, {
            preserveScroll: true,
        });
}

function submitTransactionUpdate(): void {
    if (!selectedTransaction.value) {
        return;
    }

    transactionForm
        .transform((data) => ({
            ...data,
            _method: 'patch',
        }))
        .post(
            updateBillingTransaction({
                user: props.user.uuid,
                billingTransaction: selectedTransaction.value.id,
            }).url,
            {
                preserveScroll: true,
            },
        );
}

function assignPendingTransaction(transactionId: number): void {
    assignForm
        .transform(() => ({
            _method: 'patch',
        }))
        .post(
            assignBillingTransaction({
                user: props.user.uuid,
                billingTransaction: transactionId,
            }).url,
            {
                preserveScroll: true,
            },
        );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('admin.users.billing.title')" />

        <AdminLayout>
            <section class="space-y-6">
                <section
                    class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
                >
                    <div
                        class="border-b border-slate-200/70 bg-gradient-to-r from-emerald-500/10 via-sky-500/10 to-amber-500/10 px-5 py-5 sm:px-6 sm:py-6 md:px-8 md:py-7 dark:border-slate-800"
                    >
                        <div
                            class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"
                        >
                            <div class="min-w-0 space-y-3">
                                <Badge
                                    class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] tracking-[0.2em] text-emerald-900 uppercase dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100"
                                >
                                    {{ t('admin.shell.eyebrow') }}
                                </Badge>
                                <Heading
                                    variant="small"
                                    :title="t('admin.users.billing.title')"
                                    :description="
                                        t('admin.users.billing.description', {
                                            user:
                                                props.user.full_name ||
                                                props.user.email,
                                        })
                                    "
                                />
                            </div>

                            <Button
                                variant="outline"
                                class="w-full rounded-xl sm:w-auto"
                                as-child
                            >
                                <Link :href="adminUsers()">
                                    {{
                                        t(
                                            'admin.users.billing.actions.backToUsers',
                                        )
                                    }}
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 px-4 py-4 sm:gap-4 sm:px-6 sm:py-6 md:grid-cols-2 md:px-8 md:py-8 xl:grid-cols-4"
                    >
                        <div
                            class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/90 p-4 md:col-span-2 md:rounded-[1.5rem] md:p-5 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t('admin.users.billing.summary.managedUser')
                                }}
                            </p>
                            <p
                                class="mt-3 text-base font-semibold tracking-tight break-words text-slate-950 sm:text-lg dark:text-slate-50"
                            >
                                {{
                                    props.user.full_name ||
                                    t('admin.users.billing.empty.noValue')
                                }}
                            </p>
                            <p
                                class="mt-1 text-sm break-all text-slate-600 dark:text-slate-300"
                            >
                                {{ props.user.email }}
                            </p>
                            <p
                                class="mt-1 font-mono text-[11px] break-all text-slate-500 dark:text-slate-400"
                            >
                                {{ props.user.uuid }}
                            </p>
                        </div>
                        <div
                            v-for="item in supportSummary"
                            :key="item.label"
                            class="rounded-[1.35rem] border border-slate-200/80 bg-white/90 p-4 md:rounded-[1.5rem] md:p-5 dark:border-slate-800 dark:bg-slate-950/70"
                        >
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ item.label }}
                            </p>
                            <p
                                class="mt-2 text-base font-semibold tracking-tight break-words text-slate-950 sm:text-lg dark:text-slate-50"
                            >
                                {{ item.value }}
                            </p>
                        </div>
                    </div>
                </section>

                <div
                    class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]"
                >
                    <Card
                        class="rounded-[1.75rem] border-slate-200/80 dark:border-slate-800"
                    >
                        <CardHeader>
                            <CardTitle>{{
                                t('admin.users.billing.sections.history')
                            }}</CardTitle>
                            <CardDescription>{{
                                t(
                                    'admin.users.billing.sectionDescriptions.history',
                                )
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-if="props.transactions.length === 0"
                                class="rounded-2xl border border-dashed border-slate-300/80 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                            >
                                {{ t('admin.users.billing.empty.history') }}
                            </div>

                            <div v-else class="grid gap-3 md:hidden">
                                <article
                                    v-for="transaction in props.transactions"
                                    :key="`${transaction.id}-mobile`"
                                    class="rounded-[1.25rem] border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/60"
                                    data-test="admin-user-billing-history-card"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0 space-y-1">
                                            <p
                                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                #{{ transaction.id }} ·
                                                {{ transaction.amount }}
                                                {{ transaction.currency }}
                                            </p>
                                            <p
                                                class="text-xs tracking-[0.16em] text-slate-500 uppercase dark:text-slate-400"
                                            >
                                                {{ transaction.provider }}
                                            </p>
                                        </div>
                                        <Badge
                                            class="rounded-full border px-2.5 py-1 text-[10px] uppercase"
                                        >
                                            {{ transaction.status }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="mt-3 text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            formatDateTime(transaction.paid_at)
                                        }}
                                    </p>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="mt-4 w-full rounded-xl"
                                        @click="editTransaction(transaction)"
                                    >
                                        {{
                                            t(
                                                'admin.users.billing.actions.editTransaction',
                                            )
                                        }}
                                    </Button>
                                </article>
                            </div>

                            <Table
                                v-if="props.transactions.length > 0"
                                class="hidden md:table"
                            >
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>ID</TableHead>
                                        <TableHead>{{
                                            t(
                                                'admin.users.billing.table.provider',
                                            )
                                        }}</TableHead>
                                        <TableHead>{{
                                            t(
                                                'admin.users.billing.table.amount',
                                            )
                                        }}</TableHead>
                                        <TableHead>{{
                                            t(
                                                'admin.users.billing.table.status',
                                            )
                                        }}</TableHead>
                                        <TableHead>{{
                                            t(
                                                'admin.users.billing.table.paidAt',
                                            )
                                        }}</TableHead>
                                        <TableHead class="text-right">{{
                                            t('admin.users.table.actions')
                                        }}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="transaction in props.transactions"
                                        :key="transaction.id"
                                    >
                                        <TableCell
                                            >#{{ transaction.id }}</TableCell
                                        >
                                        <TableCell>{{
                                            transaction.provider
                                        }}</TableCell>
                                        <TableCell
                                            >{{ transaction.amount }}
                                            {{
                                                transaction.currency
                                            }}</TableCell
                                        >
                                        <TableCell>{{
                                            transaction.status
                                        }}</TableCell>
                                        <TableCell>{{
                                            formatDateTime(transaction.paid_at)
                                        }}</TableCell>
                                        <TableCell class="text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="rounded-xl"
                                                @click="
                                                    editTransaction(transaction)
                                                "
                                            >
                                                {{
                                                    t(
                                                        'admin.users.billing.actions.editTransaction',
                                                    )
                                                }}
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <div class="space-y-6">
                        <Card
                            class="rounded-[1.75rem] border-slate-200/80 dark:border-slate-800"
                        >
                            <CardHeader>
                                <CardTitle>{{
                                    t(
                                        'admin.users.billing.sections.supportWindow',
                                    )
                                }}</CardTitle>
                                <CardDescription>{{
                                    t(
                                        'admin.users.billing.sectionDescriptions.supportWindow',
                                    )
                                }}</CardDescription>
                            </CardHeader>
                            <CardContent as-child>
                                <form
                                    class="space-y-4"
                                    @submit.prevent="submitSupport"
                                >
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <Label>{{
                                                t(
                                                    'admin.users.billing.fields.supportStatus',
                                                )
                                            }}</Label>
                                            <Select
                                                v-model="supportForm.status"
                                            >
                                                <SelectTrigger
                                                    class="mt-2 rounded-xl"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="option in props.supportStates"
                                                        :key="option.value"
                                                        :value="option.value"
                                                    >
                                                        {{ option.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <Label>{{
                                                t(
                                                    'admin.users.billing.fields.plan',
                                                )
                                            }}</Label>
                                            <Select
                                                v-model="
                                                    supportForm.billing_plan_code
                                                "
                                            >
                                                <SelectTrigger
                                                    class="mt-2 rounded-xl"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="option in props.plans"
                                                        :key="option.value"
                                                        :value="option.value"
                                                    >
                                                        {{ option.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <Label>{{
                                                t(
                                                    'admin.users.billing.fields.supportStartedAt',
                                                )
                                            }}</Label>
                                            <Input
                                                v-model="supportForm.started_at"
                                                class="mt-2 rounded-xl"
                                                type="datetime-local"
                                            />
                                        </div>
                                        <div>
                                            <Label>{{
                                                t(
                                                    'admin.users.billing.fields.supportEndsAt',
                                                )
                                            }}</Label>
                                            <Input
                                                v-model="supportForm.ends_at"
                                                class="mt-2 rounded-xl"
                                                type="datetime-local"
                                            />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <Label>{{
                                                t(
                                                    'admin.users.billing.fields.nextReminderAt',
                                                )
                                            }}</Label>
                                            <Input
                                                v-model="
                                                    supportForm.next_reminder_at
                                                "
                                                class="mt-2 rounded-xl"
                                                type="datetime-local"
                                            />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <Label>{{
                                                t(
                                                    'admin.users.billing.fields.adminNotes',
                                                )
                                            }}</Label>
                                            <textarea
                                                v-model="
                                                    supportForm.admin_notes
                                                "
                                                rows="4"
                                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                            />
                                        </div>
                                    </div>

                                    <label
                                        class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        <input
                                            v-model="supportForm.is_supporter"
                                            type="checkbox"
                                            class="rounded border-slate-300 dark:border-slate-700"
                                        />
                                        {{
                                            t(
                                                'admin.users.billing.fields.isSupporter',
                                            )
                                        }}
                                    </label>

                                    <InputError
                                        :message="
                                            supportForm.errors.status ||
                                            supportForm.errors.billing_plan_code
                                        "
                                    />

                                    <div
                                        class="grid gap-2 sm:flex sm:flex-wrap sm:gap-3"
                                    >
                                        <Button
                                            type="submit"
                                            class="w-full rounded-xl sm:w-auto"
                                            :disabled="supportForm.processing"
                                        >
                                            {{
                                                t(
                                                    'admin.users.billing.actions.saveSupport',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="w-full rounded-xl sm:w-auto"
                                            :disabled="supportForm.processing"
                                            @click="clearSubscription"
                                        >
                                            {{
                                                t(
                                                    'admin.users.billing.actions.clearSubscription',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            class="w-full rounded-xl sm:w-auto"
                                            :disabled="
                                                supportForm.processing ||
                                                deleteSubscriptionForm.processing
                                            "
                                            @click="deleteSubscription"
                                        >
                                            {{
                                                t(
                                                    'admin.users.billing.actions.deleteSubscription',
                                                )
                                            }}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card
                            class="rounded-[1.75rem] border-slate-200/80 dark:border-slate-800"
                        >
                            <CardHeader>
                                <CardTitle>{{
                                    t(
                                        'admin.users.billing.sections.manualDonation',
                                    )
                                }}</CardTitle>
                                <CardDescription>{{
                                    t(
                                        'admin.users.billing.sectionDescriptions.manualDonation',
                                    )
                                }}</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.provider',
                                            )
                                        }}</Label>
                                        <Select v-model="donationForm.provider">
                                            <SelectTrigger
                                                class="mt-2 rounded-xl"
                                                ><SelectValue
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="option in props.providers"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label>{{
                                            t('admin.users.billing.fields.plan')
                                        }}</Label>
                                        <Select
                                            v-model="
                                                donationForm.billing_plan_code
                                            "
                                        >
                                            <SelectTrigger
                                                class="mt-2 rounded-xl"
                                                ><SelectValue
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="option in props.plans"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.amount',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="donationForm.amount"
                                            class="mt-2 rounded-xl"
                                            inputmode="decimal"
                                        />
                                    </div>
                                    <div>
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.currency',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="donationForm.currency"
                                            class="mt-2 rounded-xl"
                                            maxlength="3"
                                        />
                                    </div>
                                    <div>
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.paidAt',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="donationForm.paid_at"
                                            class="mt-2 rounded-xl"
                                            type="datetime-local"
                                        />
                                    </div>
                                    <div>
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.receivedAt',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="donationForm.received_at"
                                            class="mt-2 rounded-xl"
                                            type="datetime-local"
                                        />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.adminNotes',
                                            )
                                        }}</Label>
                                        <textarea
                                            v-model="donationForm.admin_notes"
                                            rows="3"
                                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="flex flex-wrap gap-4 text-sm text-slate-600 dark:text-slate-300"
                                >
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="donationForm.is_recurring"
                                            type="checkbox"
                                            class="rounded border-slate-300 dark:border-slate-700"
                                        />
                                        {{
                                            t(
                                                'admin.users.billing.fields.isRecurring',
                                            )
                                        }}
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="
                                                donationForm.apply_support_window
                                            "
                                            type="checkbox"
                                            class="rounded border-slate-300 dark:border-slate-700"
                                        />
                                        {{
                                            t(
                                                'admin.users.billing.fields.applySupportWindow',
                                            )
                                        }}
                                    </label>
                                </div>

                                <InputError
                                    :message="
                                        donationForm.errors.amount ||
                                        donationForm.errors.status
                                    "
                                />

                                <Button
                                    class="w-full rounded-xl sm:w-auto"
                                    :disabled="donationForm.processing"
                                    @click="submitDonation"
                                >
                                    {{
                                        t(
                                            'admin.users.billing.actions.saveDonation',
                                        )
                                    }}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <Card
                        class="rounded-[1.75rem] border-slate-200/80 dark:border-slate-800"
                    >
                        <CardHeader>
                            <CardTitle>{{
                                t(
                                    'admin.users.billing.sections.editTransaction',
                                )
                            }}</CardTitle>
                            <CardDescription>{{
                                t(
                                    'admin.users.billing.sectionDescriptions.editTransaction',
                                )
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-if="!selectedTransaction"
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'admin.users.billing.empty.selectTransaction',
                                    )
                                }}
                            </div>
                            <template v-else>
                                <p
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    #{{ selectedTransaction.id }}
                                </p>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.provider',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="transactionForm.provider"
                                            class="mt-2 rounded-xl"
                                        />
                                    </div>
                                    <div>
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.amount',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="transactionForm.amount"
                                            class="mt-2 rounded-xl"
                                            inputmode="decimal"
                                        />
                                    </div>
                                    <div>
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.paidAt',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="transactionForm.paid_at"
                                            class="mt-2 rounded-xl"
                                            type="datetime-local"
                                        />
                                    </div>
                                    <div>
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.receivedAt',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="
                                                transactionForm.received_at
                                            "
                                            class="mt-2 rounded-xl"
                                            type="datetime-local"
                                        />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <Label>{{
                                            t(
                                                'admin.users.billing.fields.adminNotes',
                                            )
                                        }}</Label>
                                        <textarea
                                            v-model="
                                                transactionForm.admin_notes
                                            "
                                            rows="3"
                                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                        />
                                    </div>
                                </div>

                                <Button
                                    class="w-full rounded-xl sm:w-auto"
                                    :disabled="transactionForm.processing"
                                    @click="submitTransactionUpdate"
                                >
                                    {{
                                        t(
                                            'admin.users.billing.actions.updateTransaction',
                                        )
                                    }}
                                </Button>
                            </template>
                        </CardContent>
                    </Card>

                    <Card
                        class="rounded-[1.75rem] border-slate-200/80 dark:border-slate-800"
                    >
                        <CardHeader>
                            <CardTitle>{{
                                t(
                                    'admin.users.billing.sections.assignTransaction',
                                )
                            }}</CardTitle>
                            <CardDescription>{{
                                t(
                                    'admin.users.billing.sectionDescriptions.assignTransaction',
                                )
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-if="props.availableTransactions.length === 0"
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    t(
                                        'admin.users.billing.empty.assignableTransactions',
                                    )
                                }}
                            </div>
                            <div
                                v-for="transaction in props.availableTransactions"
                                :key="transaction.id"
                                class="flex flex-col items-stretch gap-3 rounded-2xl border border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4 dark:border-slate-800"
                            >
                                <div class="min-w-0 space-y-1">
                                    <p
                                        class="font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        #{{ transaction.id }} ·
                                        {{ transaction.amount }}
                                        {{ transaction.currency }}
                                    </p>
                                    <p
                                        class="text-sm break-all text-slate-500 dark:text-slate-400"
                                    >
                                        {{ transaction.provider }} ·
                                        {{
                                            transaction.customer_email ??
                                            t(
                                                'admin.users.billing.empty.noValue',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    class="w-full rounded-xl sm:w-auto"
                                    @click="
                                        assignPendingTransaction(transaction.id)
                                    "
                                >
                                    {{
                                        t(
                                            'admin.users.billing.actions.assignTransaction',
                                        )
                                    }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
