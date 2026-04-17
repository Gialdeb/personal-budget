<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Landmark, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BankFormSheet from '@/components/banks/BankFormSheet.vue';
import BankSearchSelect from '@/components/banks/BankSearchSelect.vue';
import AppToastStack from '@/components/ui/AppToastStack.vue';
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
import { useToastFeedback } from '@/composables/useToastFeedback';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { destroy, edit, store, toggleActive } from '@/routes/banks';
import type { BanksPageProps, BreadcrumbItem, UserBankItem } from '@/types';

const props = defineProps<BanksPageProps>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('settings.sections.banks'),
        href: edit(),
    },
];

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);

const formOpen = ref(false);
const editingBank = ref<UserBankItem | null>(null);
const deletingBank = ref<UserBankItem | null>(null);
const catalogBankUuid = ref<string>('');
const { feedback, showFeedback } = useToastFeedback();

const addCatalogForm = useForm({
    mode: 'catalog',
    bank_uuid: '',
    is_active: true,
    create_base_account: true,
});

const flashSuccess = computed(() => flash.value.success ?? undefined);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

watch(
    flashSuccess,
    (message) => {
        if (message) {
            showFeedback({
                variant: 'default',
                title: t('settings.banks.feedback.successTitle'),
                message,
            });
        }
    },
    { immediate: true },
);

watch(
    pageErrors,
    (errors) => {
        const message = errors.delete ?? errors.name ?? errors.bank_uuid;

        if (message) {
            showFeedback({
                variant: 'destructive',
                title: t('settings.banks.feedback.unavailableTitle'),
                message,
            });
        }
    },
    { immediate: true, deep: true },
);

const summaryCards = computed(() => [
    {
        label: t('settings.banks.summary.total'),
        value: props.banks.summary.total_count,
        tone: 'text-slate-950 dark:text-slate-50',
    },
    {
        label: t('settings.banks.summary.active'),
        value: props.banks.summary.active_count,
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        label: t('settings.banks.summary.custom'),
        value: props.banks.summary.custom_count,
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        label: t('settings.banks.summary.used'),
        value: props.banks.summary.used_count,
        tone: 'text-amber-700 dark:text-amber-300',
    },
]);

const catalogAvailable = computed(() => props.catalog.available);
const catalogSearchOptions = computed(() =>
    catalogAvailable.value.map((option) => ({
        value: option.uuid,
        name: option.name,
        display_name: option.display_name,
        slug: option.slug,
        country_code: option.country_code,
        logo_url: option.logo_url,
    })),
);
const customBanks = computed(() =>
    props.banks.data.filter((item) => item.is_custom),
);
const catalogBanks = computed(() =>
    props.banks.data.filter((item) => !item.is_custom),
);

const deleteReasons = computed(() => {
    if (!deletingBank.value) {
        return [];
    }

    const reasons: string[] = [];

    if (deletingBank.value.accounts_count > 0) {
        reasons.push(
            deletingBank.value.accounts_count === 1
                ? t('settings.banks.deleteReasons.accountOne')
                : t('settings.banks.deleteReasons.accountMany', {
                      count: deletingBank.value.accounts_count,
                  }),
        );
    }

    return reasons;
});

function bankLabel(bank: Pick<UserBankItem, 'display_name' | 'name'>): string {
    return bank.display_name ?? bank.name;
}

function openCreateBank(): void {
    editingBank.value = null;
    formOpen.value = true;
}

function consumeCreateBankQuery(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const url = new URL(window.location.href);

    if (url.searchParams.get('create') !== '1') {
        return false;
    }

    url.searchParams.delete('create');
    window.history.replaceState(window.history.state, '', url);

    return true;
}

function openEditBank(bank: UserBankItem): void {
    editingBank.value = bank;
    formOpen.value = true;
}

function handleSaved(message: string): void {
    showFeedback({
        variant: 'default',
        title: t('settings.banks.feedback.saveTitle'),
        message,
    });
}

function addCatalogBank(): void {
    if (catalogBankUuid.value === '') {
        return;
    }

    addCatalogForm
        .transform(() => ({
            mode: 'catalog',
            bank_uuid: catalogBankUuid.value,
            is_active: true,
            create_base_account: addCatalogForm.create_base_account,
        }))
        .post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                catalogBankUuid.value = '';
                addCatalogForm.reset();
                showFeedback({
                    variant: 'default',
                    title: t('settings.banks.feedback.catalogTitle'),
                    message: t('settings.banks.feedback.catalogMessage'),
                });
            },
        });
}

function toggleBank(bank: UserBankItem): void {
    router.patch(
        toggleActive.url(bank.uuid),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showFeedback({
                    variant: 'default',
                    title: t('settings.banks.feedback.statusTitle'),
                    message: bank.is_active
                        ? t('settings.banks.feedback.deactivated')
                        : t('settings.banks.feedback.activated'),
                });
            },
        },
    );
}

function requestDelete(bank: UserBankItem): void {
    deletingBank.value = bank;
}

function closeDeleteDialog(): void {
    deletingBank.value = null;
}

function confirmDelete(): void {
    if (!deletingBank.value) {
        return;
    }

    router.delete(destroy.url(deletingBank.value.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            showFeedback({
                variant: 'default',
                title: t('settings.banks.feedback.deletedTitle'),
                message: t('settings.banks.feedback.deletedMessage'),
            });
            closeDeleteDialog();
        },
    });
}

watch(
    () => page.url,
    () => {
        if (consumeCreateBankQuery()) {
            openCreateBank();
        }
    },
    { immediate: true },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.sections.banks')" />

        <SettingsLayout>
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_34%),radial-gradient(circle_at_top_right,_rgba(59,130,246,0.14),_transparent_28%),linear-gradient(135deg,rgba(15,23,42,0.03),rgba(255,255,255,0))] px-6 py-6 sm:px-8 sm:py-8 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="max-w-3xl space-y-4">
                            <div
                                class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-emerald-700 uppercase dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <Building2 class="h-3.5 w-3.5" />
                                {{ t('settings.banks.badge') }}
                            </div>

                            <div class="space-y-2">
                                <h1
                                    class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-slate-50"
                                >
                                    {{ t('settings.banks.title') }}
                                </h1>
                                <p
                                    class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-[15px] dark:text-slate-300"
                                >
                                    {{ t('settings.banks.description') }}
                                </p>
                            </div>
                        </div>

                        <Button
                            class="h-11 rounded-2xl px-5"
                            @click="openCreateBank"
                        >
                            <Plus class="h-4 w-4" />
                            {{ t('settings.banks.create') }}
                        </Button>
                    </div>
                </div>

                <div class="space-y-6 px-4 py-5 sm:px-6 sm:py-6">
                    <AppToastStack :items="[feedback]" />
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article
                            v-for="card in summaryCards"
                            :key="card.label"
                            class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
                        >
                            <p
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ card.label }}
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold tracking-tight"
                                :class="card.tone"
                            >
                                {{ card.value }}
                            </p>
                        </article>
                    </div>

                    <section
                        class="rounded-[1.75rem] border border-slate-200/80 bg-white/95 p-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/80"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                        >
                            <div>
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('settings.banks.catalog.title') }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        t('settings.banks.catalog.description')
                                    }}
                                </p>
                            </div>

                            <div
                                class="grid gap-3 md:grid-cols-[minmax(0,280px)_1fr]"
                            >
                                <div>
                                    <Label
                                        class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            t(
                                                'settings.banks.catalog.selectLabel',
                                            )
                                        }}
                                    </Label>
                                    <BankSearchSelect
                                        v-model="catalogBankUuid"
                                        :options="catalogSearchOptions"
                                        :placeholder="
                                            t(
                                                'settings.banks.catalog.selectPlaceholder',
                                            )
                                        "
                                        :empty-label="
                                            t(
                                                'settings.banks.catalog.noOptions',
                                            )
                                        "
                                        :search-placeholder="
                                            t(
                                                'settings.banks.catalog.searchPlaceholder',
                                            )
                                        "
                                    />
                                </div>
                                <div class="grid gap-3">
                                    <label
                                        class="flex items-start gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/70"
                                    >
                                        <Checkbox
                                            :model-value="
                                                addCatalogForm.create_base_account
                                            "
                                            @update:model-value="
                                                addCatalogForm.create_base_account =
                                                    $event === true
                                            "
                                        />
                                        <div>
                                            <p
                                                class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                            >
                                                {{
                                                    t(
                                                        'settings.banks.catalog.createBaseAccount',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'settings.banks.catalog.createBaseAccountHelper',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </label>
                                    <Button
                                        class="h-11 rounded-2xl px-5"
                                        :disabled="
                                            catalogAvailable.length === 0
                                        "
                                        @click="addCatalogBank"
                                    >
                                        <Landmark class="h-4 w-4" />
                                        {{ t('settings.banks.catalog.add') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-6 xl:grid-cols-2">
                        <section class="space-y-4">
                            <div
                                class="flex items-center justify-between rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/75"
                            >
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            t(
                                                'settings.banks.catalogList.title',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'settings.banks.catalogList.description',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Badge variant="secondary" class="rounded-full">
                                    {{ catalogBanks.length }}
                                </Badge>
                            </div>

                            <article
                                v-if="catalogBanks.length === 0"
                                class="rounded-[1.5rem] border border-dashed border-slate-300/80 bg-slate-50/80 p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
                            >
                                {{ t('settings.banks.catalogList.empty') }}
                            </article>

                            <article
                                v-for="bank in catalogBanks"
                                :key="bank.uuid"
                                class="rounded-[1.5rem] border border-slate-200/80 bg-white/95 p-4 shadow-[0_24px_60px_-52px_rgba(15,23,42,0.6)] dark:border-slate-800 dark:bg-slate-950/80"
                            >
                                <div
                                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div class="space-y-3">
                                        <div>
                                            <p
                                                class="text-base font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ bankLabel(bank) }}
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    bank.catalog_bank
                                                        ?.country_code ??
                                                    t(
                                                        'settings.banks.labels.countryUnavailable',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Badge
                                                variant="secondary"
                                                class="rounded-full"
                                            >
                                                {{ bank.source_label }}
                                            </Badge>
                                            <Badge
                                                class="rounded-full"
                                                :class="
                                                    bank.is_active
                                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                                "
                                            >
                                                {{
                                                    bank.is_active
                                                        ? t(
                                                              'settings.banks.labels.active',
                                                          )
                                                        : t(
                                                              'settings.banks.labels.inactive',
                                                          )
                                                }}
                                            </Badge>
                                            <Badge
                                                variant="secondary"
                                                class="rounded-full"
                                            >
                                                {{ bank.accounts_count }}
                                                account
                                            </Badge>
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end"
                                    >
                                        <Button
                                            variant="secondary"
                                            class="h-10 rounded-2xl"
                                            @click="toggleBank(bank)"
                                        >
                                            {{
                                                bank.is_active
                                                    ? t(
                                                          'settings.banks.labels.inactive',
                                                      )
                                                    : t(
                                                          'settings.banks.labels.active',
                                                      )
                                            }}
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            class="h-10 rounded-2xl"
                                            @click="requestDelete(bank)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            {{
                                                t(
                                                    'settings.banks.labels.remove',
                                                )
                                            }}
                                        </Button>
                                    </div>
                                </div>
                            </article>
                        </section>

                        <section class="space-y-4">
                            <div
                                class="flex items-center justify-between rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/75"
                            >
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{
                                            t('settings.banks.customList.title')
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            t(
                                                'settings.banks.customList.description',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Badge variant="secondary" class="rounded-full">
                                    {{ customBanks.length }}
                                </Badge>
                            </div>

                            <article
                                v-if="customBanks.length === 0"
                                class="rounded-[1.5rem] border border-dashed border-slate-300/80 bg-slate-50/80 p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400"
                            >
                                {{ t('settings.banks.customList.empty') }}
                            </article>

                            <article
                                v-for="bank in customBanks"
                                :key="bank.uuid"
                                class="rounded-[1.5rem] border border-slate-200/80 bg-white/95 p-4 shadow-[0_24px_60px_-52px_rgba(15,23,42,0.6)] dark:border-slate-800 dark:bg-slate-950/80"
                            >
                                <div
                                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div class="space-y-3">
                                        <div>
                                            <p
                                                class="text-base font-semibold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ bankLabel(bank) }}
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                {{
                                                    t(
                                                        'settings.banks.labels.slug',
                                                    )
                                                }}: {{ bank.slug }}
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Badge
                                                variant="secondary"
                                                class="rounded-full"
                                            >
                                                {{ bank.source_label }}
                                            </Badge>
                                            <Badge
                                                class="rounded-full"
                                                :class="
                                                    bank.is_active
                                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                                "
                                            >
                                                {{
                                                    bank.is_active
                                                        ? t(
                                                              'settings.banks.labels.active',
                                                          )
                                                        : t(
                                                              'settings.banks.labels.inactive',
                                                          )
                                                }}
                                            </Badge>
                                            <Badge
                                                variant="secondary"
                                                class="rounded-full"
                                            >
                                                {{ bank.accounts_count }}
                                                account
                                            </Badge>
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end"
                                    >
                                        <Button
                                            variant="secondary"
                                            class="h-10 rounded-2xl"
                                            @click="openEditBank(bank)"
                                        >
                                            {{ t('app.common.edit') }}
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            class="h-10 rounded-2xl"
                                            @click="toggleBank(bank)"
                                        >
                                            {{
                                                bank.is_active
                                                    ? t(
                                                          'settings.banks.labels.inactive',
                                                      )
                                                    : t(
                                                          'settings.banks.labels.active',
                                                      )
                                            }}
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            class="col-span-2 h-10 rounded-2xl"
                                            @click="requestDelete(bank)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            {{
                                                t(
                                                    'settings.banks.labels.delete',
                                                )
                                            }}
                                        </Button>
                                    </div>
                                </div>
                            </article>

                            <div
                                v-if="customBanks.length === 0"
                                class="rounded-[1.75rem] border border-dashed border-slate-300 bg-slate-50/80 px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900/60"
                            >
                                <p
                                    class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                >
                                    {{
                                        t(
                                            'settings.banks.customList.emptyCompact',
                                        )
                                    }}
                                </p>
                            </div>
                        </section>
                    </div>
                </div>
            </section>

            <BankFormSheet
                v-model:open="formOpen"
                :bank="editingBank"
                @saved="handleSaved"
            />

            <Dialog
                :open="deletingBank !== null"
                @update:open="!$event ? closeDeleteDialog() : null"
            >
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader class="space-y-3">
                        <DialogTitle class="flex items-center gap-2">
                            <Trash2 class="h-4 w-4" />
                            {{ t('settings.banks.deleteDialog.title') }}
                        </DialogTitle>
                        <DialogDescription class="leading-6">
                            <template v-if="deletingBank?.is_deletable">
                                {{
                                    t('settings.banks.deleteDialog.removable', {
                                        name: deletingBank?.name,
                                    })
                                }}
                            </template>
                            <template v-else>
                                {{
                                    t('settings.banks.deleteDialog.blocked', {
                                        name: deletingBank?.name,
                                    })
                                }}
                            </template>
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="deleteReasons.length > 0"
                        class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                    >
                        <p class="font-medium">
                            {{ t('settings.banks.deleteDialog.blockedTitle') }}
                        </p>
                        <ul class="mt-2 space-y-1">
                            <li v-for="reason in deleteReasons" :key="reason">
                                {{ reason }}
                            </li>
                        </ul>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            @click="closeDeleteDialog"
                        >
                            {{ t('app.common.close') }}
                        </Button>
                        <Button
                            v-if="deletingBank?.is_deletable"
                            type="button"
                            variant="destructive"
                            class="rounded-xl"
                            @click="confirmDelete"
                        >
                            {{ t('settings.banks.deleteDialog.confirm') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
