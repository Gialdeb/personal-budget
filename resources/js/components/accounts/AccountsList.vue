<script setup lang="ts">
import {
    BadgeCheck,
    CircleOff,
    CreditCard,
    Landmark,
    Pencil,
    Trash2,
} from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import type { AccountItem } from '@/types';

const { t } = useI18n();

defineProps<{
    accounts: AccountItem[];
    selectedAccountUuid?: string | null;
    emptyMessage?: string;
}>();

const emit = defineEmits<{
    select: [item: AccountItem];
    edit: [item: AccountItem];
    toggleActive: [item: AccountItem];
    delete: [item: AccountItem];
}>();

function formatBalance(value: number | null, currency: string): string {
    if (value === null) {
        return t('accounts.list.notSet');
    }

    return formatCurrency(value, currency);
}

function balanceToneClass(value: number | null): string {
    if (value === null || value === 0) {
        return 'text-slate-700 dark:text-slate-200';
    }

    if (value > 0) {
        return 'text-emerald-700 dark:text-emerald-300';
    }

    return 'text-rose-700 dark:text-rose-300';
}

function accountTypeCode(account: AccountItem): string {
    return account.account_type?.code ?? '';
}

function accountTypeName(account: AccountItem): string {
    return account.account_type?.name ?? t('accounts.list.notConfigured');
}

function balanceNatureLabel(account: AccountItem): string {
    return account.balance_nature_label ?? t('accounts.list.notConfigured');
}

function accountCurrency(account: AccountItem): string {
    return account.currency || 'EUR';
}
</script>

<template>
    <div v-if="accounts.length" class="space-y-4">
        <div class="space-y-3 md:hidden">
            <article
                v-for="account in accounts"
                :key="account.uuid"
                class="rounded-[1.5rem] border bg-white/95 p-4 shadow-[0_24px_60px_-52px_rgba(15,23,42,0.6)] transition dark:bg-slate-950/80"
                :class="
                    selectedAccountUuid === account.uuid
                        ? 'border-slate-900 dark:border-slate-100'
                        : 'border-slate-200/80 dark:border-slate-800'
                "
                @click="emit('select', account)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-2">
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                <component
                                    :is="
                                        accountTypeCode(account) ===
                                            'credit_card'
                                            ? CreditCard
                                            : Landmark
                                    "
                                    class="h-4 w-4"
                                />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ account.name }}
                                </p>
                                <p
                                    class="truncate text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        account.bank_name ??
                                        t('accounts.list.bankUnset')
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-if="account.is_default"
                                class="rounded-full bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                            >
                                {{ t('accounts.list.default') }}
                            </Badge>
                            <Badge variant="secondary" class="rounded-full">
                                {{ accountTypeName(account) }}
                            </Badge>
                            <Badge variant="secondary" class="rounded-full">
                                {{ balanceNatureLabel(account) }}
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

                    <p
                        class="rounded-2xl px-3 py-2 text-right text-base font-bold tracking-tight"
                        :class="balanceToneClass(account.current_balance)"
                    >
                        {{
                            formatBalance(
                                account.current_balance,
                                accountCurrency(account),
                            )
                        }}
                    </p>
                </div>

                <div
                    class="mt-4 grid gap-3 rounded-2xl bg-slate-50/90 p-3 text-xs dark:bg-slate-900/70"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{
                            t('accounts.list.currency')
                        }}</span>
                        <span
                            class="font-medium text-slate-950 dark:text-slate-50"
                        >
                            {{ account.currency }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{
                            t('accounts.list.usage')
                        }}</span>
                        <span
                            class="font-medium text-slate-950 dark:text-slate-50"
                        >
                            {{ account.usage_count }}
                        </span>
                    </div>
                    <div
                        v-if="
                            accountTypeCode(account) === 'credit_card' &&
                            account.credit_card_settings
                        "
                        class="flex items-center justify-between gap-3"
                    >
                        <span class="text-slate-500 dark:text-slate-400">{{
                            t('accounts.list.limit')
                        }}</span>
                        <span
                            class="font-medium text-slate-950 dark:text-slate-50"
                        >
                            {{
                                account.credit_card_settings.credit_limit !==
                                null
                                    ? formatCurrency(
                                          account.credit_card_settings
                                              .credit_limit,
                                          accountCurrency(account),
                                      )
                                    : t('accounts.list.notSet')
                            }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <Button
                        variant="secondary"
                        class="h-10 rounded-2xl"
                        @click.stop="emit('edit', account)"
                    >
                        <Pencil class="h-4 w-4" />
                        {{ t('accounts.list.edit') }}
                    </Button>
                    <Button
                        v-if="account.can_toggle_active"
                        variant="secondary"
                        class="h-10 rounded-2xl"
                        @click.stop="emit('toggleActive', account)"
                    >
                        <component
                            :is="account.is_active ? CircleOff : BadgeCheck"
                            class="h-4 w-4"
                        />
                        {{
                            account.is_active
                                ? t('accounts.list.deactivate')
                                : t('accounts.list.activate')
                        }}
                    </Button>
                    <Button
                        v-if="account.is_deletable"
                        variant="destructive"
                        class="col-span-2 h-10 rounded-2xl"
                        @click.stop="emit('delete', account)"
                    >
                        <Trash2 class="h-4 w-4" />
                        {{ t('accounts.list.delete') }}
                    </Button>
                </div>
            </article>
        </div>

        <div
            class="hidden overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white/95 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] md:block dark:border-slate-800 dark:bg-slate-950/80"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50/90 dark:bg-slate-900/80">
                        <tr
                            class="text-left text-xs tracking-[0.12em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            <th class="px-5 py-4 font-medium">
                                {{ t('accounts.list.table.account') }}
                            </th>
                            <th class="px-5 py-4 font-medium">
                                {{ t('accounts.list.table.bank') }}
                            </th>
                            <th class="px-5 py-4 font-medium">
                                {{ t('accounts.list.table.type') }}
                            </th>
                            <th class="px-5 py-4 font-medium">
                                {{ t('accounts.list.table.nature') }}
                            </th>
                            <th class="px-5 py-4 font-medium">
                                {{ t('accounts.list.table.currentBalance') }}
                            </th>
                            <th class="px-5 py-4 font-medium">
                                {{ t('accounts.list.table.status') }}
                            </th>
                            <th class="px-5 py-4 font-medium">
                                {{ t('accounts.list.table.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="account in accounts"
                            :key="account.uuid"
                            class="border-t border-slate-200/70 transition hover:bg-slate-50/70 dark:border-slate-800 dark:hover:bg-slate-900/60"
                            :class="
                                selectedAccountUuid === account.uuid
                                    ? 'bg-slate-50 dark:bg-slate-900/60'
                                    : ''
                            "
                            @click="emit('select', account)"
                        >
                            <td class="px-5 py-4 align-top">
                                <div class="space-y-2">
                                    <div
                                        class="font-medium text-slate-950 dark:text-slate-50"
                                    >
                                        {{ account.name }}
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            v-if="account.is_default"
                                            class="rounded-full bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                                        >
                                            {{ t('accounts.list.default') }}
                                        </Badge>
                                        <Badge
                                            v-if="account.used"
                                            variant="secondary"
                                            class="rounded-full"
                                        >
                                            {{ t('accounts.list.used') }}
                                        </Badge>
                                        <Badge
                                            v-if="
                                                account.account_type.code ===
                                                    'credit_card' &&
                                                account.credit_card_settings
                                                    ?.auto_pay
                                            "
                                            variant="secondary"
                                            class="rounded-full"
                                        >
                                            {{ t('accounts.list.autoPay') }}
                                        </Badge>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-5 py-4 align-top text-slate-600 dark:text-slate-300"
                            >
                                {{
                                    account.bank_name ??
                                    t('accounts.list.notConfigured')
                                }}
                            </td>
                            <td class="px-5 py-4 align-top">
                                <div class="space-y-1">
                                    <div
                                        class="text-slate-950 dark:text-slate-50"
                                    >
                                        {{ accountTypeName(account) }}
                                    </div>
                                    <div
                                        v-if="
                                            accountTypeCode(account) ===
                                                'credit_card' &&
                                            account.credit_card_settings
                                        "
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            account.credit_card_settings
                                                .payment_day !== null
                                                ? t(
                                                      'accounts.list.paymentDay',
                                                      {
                                                          day: account
                                                              .credit_card_settings
                                                              .payment_day,
                                                      },
                                                  )
                                                : t('accounts.list.notSet')
                                        }}
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-5 py-4 align-top text-slate-600 dark:text-slate-300"
                            >
                                {{ balanceNatureLabel(account) }}
                            </td>
                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex rounded-2xl px-3 py-1.5 text-sm font-bold tracking-tight"
                                    :class="
                                        balanceToneClass(
                                            account.current_balance,
                                        )
                                    "
                                >
                                    {{
                                        formatBalance(
                                            account.current_balance,
                                            accountCurrency(account),
                                        )
                                    }}
                                </span>
                            </td>
                            <td class="px-5 py-4 align-top">
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
                            </td>
                            <td class="px-5 py-4 align-top">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        variant="secondary"
                                        class="h-9 rounded-xl"
                                        @click.stop="emit('edit', account)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                        {{ t('accounts.list.edit') }}
                                    </Button>
                                    <Button
                                        v-if="account.can_toggle_active"
                                        variant="secondary"
                                        class="h-9 rounded-xl"
                                        @click.stop="
                                            emit('toggleActive', account)
                                        "
                                    >
                                        <component
                                            :is="
                                                account.is_active
                                                    ? CircleOff
                                                    : BadgeCheck
                                            "
                                            class="h-4 w-4"
                                        />
                                    </Button>
                                    <Button
                                        v-if="account.is_deletable"
                                        variant="destructive"
                                        class="h-9 rounded-xl"
                                        @click.stop="emit('delete', account)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
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
