<script setup lang="ts">
import {
    BadgeCheck,
    ChevronDown,
    ChevronUp,
    CircleOff,
    CreditCard,
    GripVertical,
    Landmark,
    Pencil,
    Star,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SensitiveValue from '@/components/SensitiveValue.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import type { AccountItem } from '@/types';

const props = defineProps<{
    accounts: AccountItem[];
    selectedAccountUuid?: string | null;
    emptyMessage?: string;
}>();
const emit = defineEmits<{
    select: [item: AccountItem];
    edit: [item: AccountItem];
    toggleActive: [item: AccountItem];
    delete: [item: AccountItem];
    reorder: [accounts: AccountItem[]];
    setDefault: [item: AccountItem];
}>();
const { t } = useI18n();
const draggedAccountUuid = ref<string | null>(null);
const dropTargetUuid = ref<string | null>(null);
const activePointerId = ref<number | null>(null);

const accountGroups = computed(() => {
    const groups = new Map<string, { name: string; accounts: AccountItem[] }>();

    for (const account of props.accounts) {
        const key = account.account_type_uuid;
        const group = groups.get(key) ?? {
            name:
                account.account_type?.name ?? t('accounts.list.notConfigured'),
            accounts: [],
        };
        group.accounts.push(account);
        groups.set(key, group);
    }

    return [...groups.entries()].map(([uuid, group]) => ({ uuid, ...group }));
});

function formatBalance(account: AccountItem): string {
    return account.current_balance === null
        ? t('accounts.list.notSet')
        : formatCurrency(account.current_balance, account.currency || 'EUR');
}
function balanceToneClass(value: number | null): string {
    return value === null || value === 0
        ? 'text-slate-700 dark:text-slate-200'
        : value > 0
          ? 'text-emerald-700 dark:text-emerald-300'
          : 'text-rose-700 dark:text-rose-300';
}
function isCreditCard(account: AccountItem): boolean {
    return account.account_type?.code === 'credit_card';
}
function clearDrag(): void {
    activePointerId.value = null;
    draggedAccountUuid.value = null;
    dropTargetUuid.value = null;
}

function startPointerDrag(event: PointerEvent, account: AccountItem): void {
    if (event.button !== 0) {
        return;
    }

    event.preventDefault();
    activePointerId.value = event.pointerId;
    draggedAccountUuid.value = account.uuid;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function accountAtPointer(event: PointerEvent): AccountItem | undefined {
    const accountUuid = document
        .elementFromPoint(event.clientX, event.clientY)
        ?.closest<HTMLElement>('[data-account-uuid]')?.dataset.accountUuid;

    return props.accounts.find((account) => account.uuid === accountUuid);
}

function movePointerDrag(event: PointerEvent): void {
    if (activePointerId.value !== event.pointerId) {
        return;
    }

    event.preventDefault();
    const account = accountAtPointer(event);

    if (!account) {
        dropTargetUuid.value = null;

        return;
    }

    const dragged = props.accounts.find(
        (item) => item.uuid === draggedAccountUuid.value,
    );
    dropTargetUuid.value =
        dragged?.account_type_uuid === account.account_type_uuid
            ? account.uuid
            : null;
}
function reorderGroup(
    groupUuid: string,
    fromUuid: string,
    toUuid: string,
): void {
    const group = accountGroups.value.find((item) => item.uuid === groupUuid);

    if (!group) {
        return;
    }

    const ordered = [...group.accounts];
    const from = ordered.findIndex((item) => item.uuid === fromUuid);
    const to = ordered.findIndex((item) => item.uuid === toUuid);

    if (from === -1 || to === -1 || from === to) {
        return;
    }

    const [account] = ordered.splice(from, 1);
    ordered.splice(to, 0, account);
    emit('reorder', ordered);
}
function endPointerDrag(event: PointerEvent): void {
    if (activePointerId.value !== event.pointerId) {
        return;
    }

    const target = accountAtPointer(event);
    const dragged = props.accounts.find(
        (item) => item.uuid === draggedAccountUuid.value,
    );

    if (
        dragged &&
        target &&
        dragged.account_type_uuid === target.account_type_uuid
    ) {
        reorderGroup(target.account_type_uuid, dragged.uuid, target.uuid);
    }

    clearDrag();
}
function moveWithinGroup(account: AccountItem, offset: number): void {
    const group = accountGroups.value.find(
        (item) => item.uuid === account.account_type_uuid,
    );
    const index =
        group?.accounts.findIndex((item) => item.uuid === account.uuid) ?? -1;

    if (
        !group ||
        index + offset < 0 ||
        index + offset >= group.accounts.length
    ) {
        return;
    }

    reorderGroup(group.uuid, account.uuid, group.accounts[index + offset].uuid);
}
</script>

<template>
    <div v-if="accounts.length" class="space-y-6">
        <section
            v-for="group in accountGroups"
            :key="group.uuid"
            class="space-y-3"
        >
            <div class="flex items-center gap-3 px-1">
                <h3
                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                >
                    {{ group.name }}
                </h3>
                <Badge variant="secondary" class="rounded-full">{{
                    t('accounts.list.groupCount', {
                        count: group.accounts.length,
                    })
                }}</Badge>
            </div>
            <div
                class="overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white/95 shadow-[0_24px_60px_-52px_rgba(15,23,42,0.6)] dark:border-slate-800 dark:bg-slate-950/80"
            >
                <article
                    v-for="(account, index) in group.accounts"
                    :key="account.uuid"
                    :data-account-uuid="account.uuid"
                    class="flex min-w-0 flex-wrap items-start gap-3 border-t border-slate-200/70 px-3 py-3 transition first:border-t-0 hover:bg-slate-50/70 sm:flex-nowrap sm:items-center sm:px-4 dark:border-slate-800 dark:hover:bg-slate-900/60"
                    :class="[
                        selectedAccountUuid === account.uuid
                            ? 'bg-slate-50 dark:bg-slate-900/60'
                            : '',
                        draggedAccountUuid === account.uuid ? 'opacity-50' : '',
                        dropTargetUuid === account.uuid
                            ? 'border-t-2 border-t-sky-500'
                            : '',
                    ]"
                    @click="emit('select', account)"
                >
                    <button
                        type="button"
                        class="flex h-10 w-9 shrink-0 cursor-grab touch-none items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 active:cursor-grabbing sm:w-10 dark:hover:bg-slate-900 dark:hover:text-slate-200"
                        :aria-label="t('accounts.list.dragHandle')"
                        :title="t('accounts.list.dragHandle')"
                        @click.stop
                        @pointerdown="startPointerDrag($event, account)"
                        @pointermove="movePointerDrag"
                        @pointerup="endPointerDrag"
                        @pointercancel="clearDrag"
                    >
                        <GripVertical class="h-5 w-5" />
                    </button>
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <component
                            :is="isCreditCard(account) ? CreditCard : Landmark"
                            class="h-4 w-4"
                        />
                    </div>
                    <div class="min-w-0 flex-1 self-center">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <p
                                class="min-w-0 leading-5 font-semibold break-words text-slate-950 dark:text-slate-50"
                            >
                                {{ account.name }}
                            </p>
                            <Badge
                                v-if="account.is_default"
                                class="rounded-full bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                                >{{ t('accounts.list.default') }}</Badge
                            ><Badge
                                class="rounded-full"
                                :class="
                                    account.is_active
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                "
                                >{{
                                    account.is_active
                                        ? t('accounts.list.active')
                                        : t('accounts.list.inactive')
                                }}</Badge
                            >
                        </div>
                        <p
                            class="mt-0.5 text-xs break-words text-slate-500 dark:text-slate-400"
                        >
                            {{
                                account.bank_name ??
                                t('accounts.list.bankUnset')
                            }}
                        </p>
                    </div>
                    <p
                        class="hidden shrink-0 text-right text-sm font-bold sm:block"
                        :class="balanceToneClass(account.current_balance)"
                    >
                        <SensitiveValue :value="formatBalance(account)" />
                    </p>
                    <div
                        class="flex w-full shrink-0 justify-end gap-1 border-t border-slate-200/70 pt-2 sm:w-auto sm:border-t-0 sm:pt-0 dark:border-slate-800"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 rounded-xl"
                            :class="
                                account.is_default
                                    ? 'text-amber-500 hover:text-amber-600'
                                    : ''
                            "
                            :disabled="account.is_default || !account.is_active"
                            :aria-label="
                                account.is_default
                                    ? t('accounts.list.defaultAccount')
                                    : t('accounts.list.setDefault')
                            "
                            :title="
                                account.is_default
                                    ? t('accounts.list.defaultAccount')
                                    : t('accounts.list.setDefault')
                            "
                            @click.stop="emit('setDefault', account)"
                            ><Star
                                class="h-4 w-4"
                                :fill="
                                    account.is_default ? 'currentColor' : 'none'
                                " /></Button
                        ><Button
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 rounded-xl"
                            :disabled="index === 0"
                            :aria-label="t('accounts.list.moveUp')"
                            :title="t('accounts.list.moveUp')"
                            @click.stop="moveWithinGroup(account, -1)"
                            ><ChevronUp class="h-4 w-4" /></Button
                        ><Button
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 rounded-xl"
                            :disabled="index === group.accounts.length - 1"
                            :aria-label="t('accounts.list.moveDown')"
                            :title="t('accounts.list.moveDown')"
                            @click.stop="moveWithinGroup(account, 1)"
                            ><ChevronDown class="h-4 w-4" /></Button
                        ><Button
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 rounded-xl"
                            :aria-label="t('accounts.list.edit')"
                            @click.stop="emit('edit', account)"
                            ><Pencil class="h-4 w-4" /></Button
                        ><Button
                            v-if="account.can_toggle_active"
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 rounded-xl"
                            @click.stop="emit('toggleActive', account)"
                            ><component
                                :is="account.is_active ? CircleOff : BadgeCheck"
                                class="h-4 w-4" /></Button
                        ><Button
                            v-if="account.is_deletable"
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 rounded-xl text-rose-600 hover:text-rose-700"
                            @click.stop="emit('delete', account)"
                            ><Trash2 class="h-4 w-4"
                        /></Button>
                    </div>
                </article>
            </div>
        </section>
    </div>
    <div
        v-else
        class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50/80 px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900/60"
    >
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
            {{ emptyMessage ?? t('accounts.list.empty') }}
        </p>
    </div>
</template>
