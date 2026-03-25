<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { revoke, restore } from '@/routes/sharing/account-memberships';
import { invitations as invitationsRoute, members as membersRoute } from '@/routes/sharing/accounts';
import { store as storeInvitation } from '@/routes/sharing/accounts/invitations';
import type {
    AccountItem,
    AccountSharingInvitation,
    AccountSharingMember,
} from '@/types';

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

const props = defineProps<{
    accounts: AccountItem[];
    account: AccountItem | null;
    selectedAccountUuid: string | null;
}>();

const emit = defineEmits<{
    'update:selectedAccountUuid': [value: string];
}>();

const { t } = useI18n();
const page = usePage();

const members = ref<AccountSharingMember[]>([]);
const invitations = ref<AccountSharingInvitation[]>([]);
const isLoading = ref(false);
const isSubmittingInvite = ref(false);
const activeMembershipUuid = ref<string | null>(null);
const inviteEmail = ref('');
const inviteRole = ref<'viewer' | 'editor'>('viewer');
const inviteErrors = ref<Record<string, string>>({});
const panelError = ref<string | null>(null);
const feedback = ref<FeedbackState | null>(null);

const pendingInvitations = computed(() =>
    invitations.value.filter((invitation) => invitation.status === 'pending'),
);

const accountOptions = computed(() =>
    props.accounts.map((account) => ({
        value: account.uuid,
        label: formatAccountLabel(account),
    })),
);

const selectedAccountLabel = computed(() =>
    props.account ? formatAccountLabel(props.account) : null,
);

const roleOptions = computed(() => [
    { value: 'viewer', label: t('accounts.sharing.form.roles.viewer') },
    { value: 'editor', label: t('accounts.sharing.form.roles.editor') },
]);

type JsonPayload = {
    data?: unknown;
    errors?: Record<string, string[]>;
    message?: string;
};

watch(
    () => props.account?.uuid ?? null,
    async (accountUuid) => {
        members.value = [];
        invitations.value = [];
        inviteErrors.value = {};
        panelError.value = null;
        feedback.value = null;

        if (!accountUuid) {
            return;
        }

        await loadSharingData(accountUuid);
    },
    { immediate: true },
);

function readCsrfToken(): string {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return token ?? '';
}

function requestHeaders(json = false): HeadersInit {
    return {
        Accept: 'application/json',
        ...(json ? { 'Content-Type': 'application/json' } : {}),
        'X-CSRF-TOKEN': readCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}

async function parseJsonPayload(response: Response): Promise<JsonPayload | null> {
    const contentType = response.headers.get('content-type') ?? '';

    if (!contentType.includes('application/json')) {
        return null;
    }

    try {
        return (await response.json()) as JsonPayload;
    } catch {
        return null;
    }
}

function asMembers(data: unknown): AccountSharingMember[] {
    return Array.isArray(data) ? (data as AccountSharingMember[]) : [];
}

function asInvitations(data: unknown): AccountSharingInvitation[] {
    return Array.isArray(data) ? (data as AccountSharingInvitation[]) : [];
}

async function loadSharingData(accountUuid: string): Promise<void> {
    isLoading.value = true;

    try {
        const [membersResponse, invitationsResponse] = await Promise.all([
            fetch(membersRoute.url(accountUuid), {
                headers: requestHeaders(),
                credentials: 'same-origin',
            }),
            fetch(invitationsRoute.url(accountUuid), {
                headers: requestHeaders(),
                credentials: 'same-origin',
            }),
        ]);

        const [membersPayload, invitationsPayload] = await Promise.all([
            parseJsonPayload(membersResponse),
            parseJsonPayload(invitationsResponse),
        ]);

        if (!membersResponse.ok || !invitationsResponse.ok) {
            panelError.value =
                membersPayload?.message ??
                invitationsPayload?.message ??
                t('accounts.sharing.feedback.loadError');

            return;
        }

        members.value = asMembers(membersPayload?.data);
        invitations.value = asInvitations(invitationsPayload?.data);
    } catch {
        panelError.value = t('accounts.sharing.feedback.loadError');
    } finally {
        isLoading.value = false;
    }
}

async function submitInvitation(): Promise<void> {
    if (!props.account || isSubmittingInvite.value) {
        return;
    }

    isSubmittingInvite.value = true;
    inviteErrors.value = {};
    panelError.value = null;
    feedback.value = null;

    try {
        const response = await fetch(storeInvitation.url(props.account.uuid), {
            method: 'POST',
            headers: requestHeaders(true),
            credentials: 'same-origin',
            body: JSON.stringify({
                email: inviteEmail.value,
                role: inviteRole.value,
            }),
        });

        const payload = await parseJsonPayload(response);

        if (!response.ok) {
            inviteErrors.value = Object.fromEntries(
                Object.entries(payload?.errors ?? {}).map(([key, value]) => [
                    key,
                    value[0] ?? '',
                ]),
            );
            panelError.value =
                payload?.message ?? t('accounts.sharing.feedback.inviteError');

            return;
        }

        inviteEmail.value = '';
        inviteRole.value = 'viewer';
        feedback.value = {
            variant: 'default',
            title: t('accounts.sharing.feedback.inviteSuccessTitle'),
            message:
                payload?.message ??
                t('accounts.sharing.feedback.inviteSuccess'),
        };

        await loadSharingData(props.account.uuid);
    } catch {
        panelError.value = t('accounts.sharing.feedback.inviteError');
    } finally {
        isSubmittingInvite.value = false;
    }
}

async function updateMembership(
    membership: AccountSharingMember,
    action: 'revoke' | 'restore',
): Promise<void> {
    if (!props.account || activeMembershipUuid.value) {
        return;
    }

    activeMembershipUuid.value = membership.uuid;
    panelError.value = null;
    feedback.value = null;

    const routeDefinition =
        action === 'revoke' ? revoke.url(membership.uuid) : restore.url(membership.uuid);

    try {
        const response = await fetch(routeDefinition, {
            method: 'POST',
            headers: requestHeaders(true),
            credentials: 'same-origin',
            body: action === 'revoke' ? JSON.stringify({ reason: null }) : JSON.stringify({}),
        });

        const payload = await parseJsonPayload(response);

        if (!response.ok) {
            panelError.value =
                payload?.message ??
                Object.values(payload?.errors ?? {}).flat()[0] ??
                t('accounts.sharing.feedback.actionError');

            return;
        }

        feedback.value = {
            variant: 'default',
            title: t('accounts.sharing.feedback.membershipUpdatedTitle'),
            message:
                payload?.message ??
                t('accounts.sharing.feedback.membershipUpdated'),
        };

        await loadSharingData(props.account.uuid);
    } catch {
        panelError.value = t('accounts.sharing.feedback.actionError');
    } finally {
        activeMembershipUuid.value = null;
    }
}

function formatDate(value: string | null): string {
    if (!value) {
        return t('accounts.sharing.empty.notAvailable');
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const locale = String(
        (page.props.locale as { current?: string } | undefined)?.current ?? 'en',
    );

    return new Intl.DateTimeFormat(locale === 'it' ? 'it-IT' : 'en-US', {
        dateStyle: 'medium',
    }).format(date);
}

function formatAccountLabel(account: AccountItem): string {
    const bankName =
        account.bank_name ?? t('accounts.sharing.accountPicker.bankFallback');

    return `${bankName} · ${account.name}`;
}

function updateSelectedAccount(value: string): void {
    emit('update:selectedAccountUuid', value);
}
</script>

<template>
    <section
        class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] sm:p-6 dark:border-slate-800 dark:bg-slate-950/80"
    >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-1.5">
                <h2 class="text-base font-semibold text-slate-950 dark:text-slate-50">
                    {{ t('accounts.sharing.title') }}
                </h2>
                <p class="max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                    {{ t('accounts.sharing.description') }}
                </p>
            </div>
            <Badge variant="secondary" class="rounded-full">
                {{ t('accounts.sharing.ownerOnly') }}
            </Badge>
        </div>

        <div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <section
                class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
            >
                <div class="space-y-1">
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ t('accounts.sharing.accountPicker.title') }}
                    </p>
                    <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">
                        {{ t('accounts.sharing.accountPicker.description') }}
                    </p>
                </div>

                <div class="mt-4 space-y-2">
                    <Label for="account-sharing-selector">
                        {{ t('accounts.sharing.accountPicker.label') }}
                    </Label>
                    <SearchableSelect
                        id="account-sharing-selector"
                        :model-value="selectedAccountUuid ?? ''"
                        :options="accountOptions"
                        :placeholder="t('accounts.sharing.accountPicker.placeholder')"
                        :search-placeholder="
                            t('accounts.sharing.accountPicker.searchPlaceholder')
                        "
                        :empty-label="t('accounts.sharing.accountPicker.empty')"
                        :disabled="accountOptions.length === 0"
                        trigger-class="h-12 rounded-2xl border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"
                        content-class="max-w-[36rem]"
                        @update:model-value="updateSelectedAccount"
                    />
                </div>
            </section>

            <section
                class="rounded-[1.5rem] border border-sky-200/70 bg-[linear-gradient(135deg,rgba(59,130,246,0.10),rgba(255,255,255,0.95))] p-5 dark:border-sky-500/20 dark:bg-[linear-gradient(135deg,rgba(14,116,144,0.20),rgba(2,6,23,0.92))]"
            >
                <template v-if="account">
                    <p class="text-xs font-semibold tracking-[0.18em] text-sky-700 uppercase dark:text-sky-300">
                        {{ t('accounts.sharing.accountPicker.selectedLabel') }}
                    </p>
                    <div class="mt-3 space-y-3">
                        <div>
                            <p class="text-lg font-semibold tracking-tight text-slate-950 dark:text-slate-50">
                                {{ account.name }}
                            </p>
                            <p class="text-sm text-slate-600 dark:text-slate-300">
                                {{ selectedAccountLabel }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Badge variant="secondary" class="rounded-full">
                                {{ account.account_type.name }}
                            </Badge>
                            <Badge variant="secondary" class="rounded-full">
                                {{ account.balance_nature_label }}
                            </Badge>
                            <Badge
                                class="rounded-full"
                                :class="
                                    account.is_active
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                "
                            >
                                {{
                                    account.is_active
                                        ? t('accounts.list.active')
                                        : t('accounts.list.inactive')
                                }}
                            </Badge>
                        </div>
                    </div>
                </template>

                <div
                    v-else
                    class="rounded-[1.25rem] border border-dashed border-slate-300 bg-white/60 px-4 py-8 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950/50 dark:text-slate-400"
                >
                    {{ t('accounts.sharing.accountPicker.empty') }}
                </div>
            </section>
        </div>

        <Alert
            v-if="feedback"
            :variant="feedback.variant"
            class="mt-5 rounded-[1.25rem] border"
        >
            <AlertTitle>{{ feedback.title }}</AlertTitle>
            <AlertDescription>{{ feedback.message }}</AlertDescription>
        </Alert>

        <Alert
            v-if="panelError"
            variant="destructive"
            class="mt-5 rounded-[1.25rem] border"
        >
            <AlertTitle>{{ t('accounts.sharing.feedback.errorTitle') }}</AlertTitle>
            <AlertDescription>{{ panelError }}</AlertDescription>
        </Alert>

        <div v-if="account" class="mt-6 space-y-6">
            <div
                class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
            >
                <div class="space-y-1">
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ t('accounts.sharing.form.title') }}
                    </p>
                    <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                        {{ t('accounts.sharing.form.description') }}
                    </p>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_180px_auto] lg:items-start">
                    <div class="space-y-2">
                        <Label for="account-sharing-email">
                            {{ t('accounts.sharing.form.emailLabel') }}
                        </Label>
                        <Input
                            id="account-sharing-email"
                            v-model="inviteEmail"
                            type="email"
                            :placeholder="t('accounts.sharing.form.emailPlaceholder')"
                            :disabled="isSubmittingInvite || !account"
                        />
                        <InputError :message="inviteErrors.email" />
                    </div>

                    <div class="space-y-2">
                        <Label for="account-sharing-role">
                            {{ t('accounts.sharing.form.roleLabel') }}
                        </Label>
                        <Select
                            v-model="inviteRole"
                            :disabled="isSubmittingInvite || !account"
                        >
                            <SelectTrigger id="account-sharing-role">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in roleOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="inviteErrors.role" />
                    </div>

                    <div class="flex items-end">
                        <Button
                            class="w-full rounded-2xl sm:w-auto"
                            :disabled="isSubmittingInvite || !account"
                            @click="submitInvitation"
                        >
                            {{ t('accounts.sharing.form.submit') }}
                        </Button>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
                <section
                    class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                {{ t('accounts.sharing.members.title') }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ t('accounts.sharing.members.description') }}
                            </p>
                        </div>
                        <Badge variant="secondary" class="rounded-full">
                            {{ members.length }}
                        </Badge>
                    </div>

                    <div v-if="isLoading" class="mt-4 space-y-3">
                        <Skeleton class="h-20 rounded-[1rem]" />
                        <Skeleton class="h-20 rounded-[1rem]" />
                    </div>

                    <div
                        v-else-if="members.length === 0"
                        class="mt-4 rounded-[1rem] border border-dashed border-slate-300 bg-white/70 px-4 py-8 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-400"
                    >
                        {{ t('accounts.sharing.members.empty') }}
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <article
                            v-for="membership in members"
                            :key="membership.uuid"
                            class="rounded-[1rem] border border-slate-200/80 bg-white/85 p-4 dark:border-slate-800 dark:bg-slate-950/75"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-2">
                                    <div>
                                        <p class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50">
                                            {{ membership.user?.name ?? membership.user?.email ?? t('accounts.sharing.empty.notAvailable') }}
                                        </p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ membership.user?.email ?? t('accounts.sharing.empty.notAvailable') }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge variant="secondary" class="rounded-full">
                                            {{ membership.role_label ?? membership.role ?? t('accounts.sharing.empty.notAvailable') }}
                                        </Badge>
                                        <Badge variant="secondary" class="rounded-full">
                                            {{ membership.status_label ?? membership.status ?? t('accounts.sharing.empty.notAvailable') }}
                                        </Badge>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ t('accounts.sharing.members.joinedAt', { date: formatDate(membership.joined_at) }) }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 gap-2">
                                    <Button
                                        v-if="membership.status === 'active' && membership.role !== 'owner'"
                                        variant="outline"
                                        size="sm"
                                        class="rounded-full"
                                        :disabled="activeMembershipUuid === membership.uuid"
                                        @click="updateMembership(membership, 'revoke')"
                                    >
                                        {{ t('accounts.sharing.actions.revoke') }}
                                    </Button>
                                    <Button
                                        v-if="membership.status === 'left' || membership.status === 'revoked'"
                                        variant="outline"
                                        size="sm"
                                        class="rounded-full"
                                        :disabled="activeMembershipUuid === membership.uuid"
                                        @click="updateMembership(membership, 'restore')"
                                    >
                                        {{ t('accounts.sharing.actions.restore') }}
                                    </Button>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section
                    class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                {{ t('accounts.sharing.invitations.title') }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ t('accounts.sharing.invitations.description') }}
                            </p>
                        </div>
                        <Badge variant="secondary" class="rounded-full">
                            {{ pendingInvitations.length }}
                        </Badge>
                    </div>

                    <div v-if="isLoading" class="mt-4 space-y-3">
                        <Skeleton class="h-20 rounded-[1rem]" />
                        <Skeleton class="h-20 rounded-[1rem]" />
                    </div>

                    <div
                        v-else-if="pendingInvitations.length === 0"
                        class="mt-4 rounded-[1rem] border border-dashed border-slate-300 bg-white/70 px-4 py-8 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-400"
                    >
                        {{ t('accounts.sharing.invitations.empty') }}
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <article
                            v-for="invitation in pendingInvitations"
                            :key="invitation.uuid"
                            class="rounded-[1rem] border border-slate-200/80 bg-white/85 p-4 dark:border-slate-800 dark:bg-slate-950/75"
                        >
                            <div class="space-y-2">
                                <div>
                                    <p class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50">
                                        {{ invitation.email }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ t('accounts.sharing.invitations.sentAt', { date: formatDate(invitation.created_at) }) }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary" class="rounded-full">
                                        {{ invitation.role_label ?? invitation.role ?? t('accounts.sharing.empty.notAvailable') }}
                                    </Badge>
                                    <Badge variant="secondary" class="rounded-full">
                                        {{ invitation.status_label ?? invitation.status ?? t('accounts.sharing.empty.notAvailable') }}
                                    </Badge>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{
                                        invitation.expires_at
                                            ? t('accounts.sharing.invitations.expiresAt', {
                                                  date: formatDate(invitation.expires_at),
                                              })
                                            : t('accounts.sharing.invitations.noExpiry')
                                    }}
                                </p>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>

        <div
            v-else
            class="mt-6 rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50/80 px-5 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
        >
            {{ t('accounts.sharing.accountPicker.empty') }}
        </div>
    </section>
</template>
