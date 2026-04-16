<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CircleCheckBig,
    FolderTree,
    Network,
    Plus,
    ShieldAlert,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CategoryFormSheet from '@/components/categories/CategoryFormSheet.vue';
import CategoryTreeList from '@/components/categories/CategoryTreeList.vue';
import SearchableSelect from '@/components/transactions/SearchableSelect.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type {
    BreadcrumbItem,
    CategoryItem,
    SharedCategoryAccountCatalog,
    SharedCategoryPageProps,
} from '@/types';

type FeedbackState = {
    variant: 'default' | 'destructive';
    title: string;
    message: string;
};

const props = defineProps<SharedCategoryPageProps>();
const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('categories.sharedPage.title'),
        href: '/settings/shared-categories',
    },
];

const page = usePage();
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string | null },
);
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

const selectedAccountUuid = ref<string | null>(
    props.sharedCategories.accounts[0]?.uuid ?? null,
);
const selectedSourceCategoryUuid = ref('');
const formOpen = ref(false);
const editingCategory = ref<CategoryItem | null>(null);
const suggestedParentUuid = ref<string | null>(null);
const deletingCategory = ref<CategoryItem | null>(null);
const feedback = ref<FeedbackState | null>(null);
let feedbackTimeout: ReturnType<typeof setTimeout> | null = null;

const selectedAccount = computed<SharedCategoryAccountCatalog | null>(
    () =>
        props.sharedCategories.accounts.find(
            (account) => account.uuid === selectedAccountUuid.value,
        ) ?? null,
);
const selectedFlatCategories = computed(
    () => selectedAccount.value?.categories.flat ?? [],
);
const selectedTreeCategories = computed(
    () => selectedAccount.value?.categories.tree ?? [],
);
const selectedSourceCategories = computed(
    () => selectedAccount.value?.source_categories ?? [],
);
const selectedImportableSourceCategories = computed(() =>
    selectedSourceCategories.value.filter(
        (category) => category.is_selectable !== false,
    ),
);
const formStoreUrl = computed(() =>
    selectedAccount.value
        ? `/settings/shared-categories/${selectedAccount.value.uuid}`
        : undefined,
);
const buildUpdateUrl = computed(() =>
    selectedAccount.value
        ? (
              (accountUuid: string) => (uuid: string) =>
                  `/settings/shared-categories/${accountUuid}/${uuid}`
          )(selectedAccount.value.uuid)
        : undefined,
);

const summaryCards = computed(() => {
    const summary = selectedAccount.value?.categories.summary;

    return [
        {
            label: t('categories.summary.total'),
            value: summary?.total_count ?? 0,
            tone: 'text-slate-950 dark:text-slate-50',
        },
        {
            label: t('categories.summary.active'),
            value: summary?.active_count ?? 0,
            tone: 'text-emerald-700 dark:text-emerald-300',
        },
        {
            label: t('categories.summary.selectable'),
            value: summary?.selectable_count ?? 0,
            tone: 'text-sky-700 dark:text-sky-300',
        },
        {
            label: t('categories.summary.used'),
            value: summary?.used_count ?? 0,
            tone: 'text-amber-700 dark:text-amber-300',
        },
    ];
});

function accountDisplayLabel(account: SharedCategoryAccountCatalog): string {
    return account.bank_name ? `${account.bank_name} · ${account.name}` : account.name;
}

const deleteReasons = computed(() => {
    if (!deletingCategory.value) {
        return [];
    }

    const reasons: string[] = [];

    if (deletingCategory.value.children_count > 0) {
        reasons.push(
            deletingCategory.value.children_count === 1
                ? t('categories.deleteReasons.childOne')
                : t('categories.deleteReasons.childMany', {
                      count: deletingCategory.value.children_count,
                  }),
        );
    }

    if (deletingCategory.value.usage_count > 0) {
        reasons.push(
            deletingCategory.value.usage_count === 1
                ? t('categories.deleteReasons.usedOne')
                : t('categories.deleteReasons.usedMany', {
                      count: deletingCategory.value.usage_count,
                  }),
        );
    }

    return reasons;
});

watch(
    () => props.sharedCategories.accounts,
    (accounts) => {
        if (accounts.length === 0) {
            selectedAccountUuid.value = null;

            return;
        }

        if (
            !accounts.some(
                (account) => account.uuid === selectedAccountUuid.value,
            )
        ) {
            selectedAccountUuid.value = accounts[0]?.uuid ?? null;
        }
    },
    { immediate: true },
);

watch(selectedAccountUuid, () => {
    selectedSourceCategoryUuid.value = '';
});

watch(
    () => flash.value.success,
    (message) => {
        if (message) {
            feedback.value = {
                variant: 'default',
                title: t('categories.feedback.successTitle'),
                message,
            };
        }
    },
    { immediate: true },
);

watch(
    pageErrors,
    (errors) => {
        const message = errors.delete ?? errors.toggle;

        if (message) {
            feedback.value = {
                variant: 'destructive',
                title: t('categories.feedback.unavailableTitle'),
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

onUnmounted(() => {
    if (feedbackTimeout) {
        clearTimeout(feedbackTimeout);
    }
});

const isReadOnlyAccount = computed(() => !selectedAccount.value?.can_edit);

function openCreateCategory(): void {
    if (!selectedAccount.value?.can_edit) {
        return;
    }

    editingCategory.value = null;
    suggestedParentUuid.value = null;
    formOpen.value = true;
}

function consumeCreateSharedCategoryQuery(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const url = new URL(window.location.href);

    if (url.searchParams.get('create') !== '1') {
        return false;
    }

    url.searchParams.delete('create');

    window.history.replaceState(
        window.history.state,
        '',
        `${url.pathname}${url.search}${url.hash}`,
    );

    openCreateCategory();

    return true;
}

function openEditCategory(item: CategoryItem): void {
    if (!selectedAccount.value?.can_edit) {
        return;
    }

    editingCategory.value = item;
    suggestedParentUuid.value = item.parent_uuid;
    formOpen.value = true;
}

function openCreateChild(item: CategoryItem): void {
    if (!selectedAccount.value?.can_edit) {
        return;
    }

    if (item.depth >= 2) {
        return;
    }

    editingCategory.value = null;
    suggestedParentUuid.value = item.uuid;
    formOpen.value = true;
}

function closeDeleteDialog(): void {
    deletingCategory.value = null;
}

function handleSaved(message: string): void {
    feedback.value = {
        variant: 'default',
        title: t('categories.feedback.savedTitle'),
        message,
    };
}

function materializeSourceCategory(): void {
    if (
        !selectedAccount.value?.can_edit ||
        selectedAccountUuid.value === null ||
        selectedSourceCategoryUuid.value === ''
    ) {
        return;
    }

    router.post(
        `/settings/shared-categories/${selectedAccountUuid.value}/materialize-personal`,
        {
            source_category_uuid: selectedSourceCategoryUuid.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedSourceCategoryUuid.value = '';
            },
            onError: (errors) => {
                feedback.value = {
                    variant: 'destructive',
                    title: t('categories.feedback.updateFailedTitle'),
                    message:
                        String(errors.source_category_uuid ?? '') ||
                        t(
                            'categories.sharedPage.materialize.validation.unavailable',
                        ),
                };
            },
        },
    );
}

function toggleCategory(item: CategoryItem): void {
    if (!selectedAccount.value) {
        return;
    }

    router.patch(
        `/settings/shared-categories/${selectedAccount.value.uuid}/${item.uuid}/toggle-active`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                feedback.value = {
                    variant: 'default',
                    title: t('categories.feedback.statusUpdatedTitle'),
                    message: item.is_active
                        ? t('categories.feedback.deactivatedMessage')
                        : t('categories.feedback.activatedMessage'),
                };
            },
            onError: (errors) => {
                feedback.value = {
                    variant: 'destructive',
                    title: t('categories.feedback.updateFailedTitle'),
                    message:
                        String(errors.toggle ?? '') ||
                        t('categories.feedback.updateFailedMessage'),
                };
            },
        },
    );
}

function requestDelete(item: CategoryItem): void {
    if (!selectedAccount.value?.can_edit) {
        return;
    }

    deletingCategory.value = item;
}

function confirmDelete(): void {
    if (!selectedAccount.value || !deletingCategory.value) {
        return;
    }

    router.delete(
        `/settings/shared-categories/${selectedAccount.value.uuid}/${deletingCategory.value.uuid}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                feedback.value = {
                    variant: 'default',
                    title: t('categories.feedback.deletedTitle'),
                    message: t('categories.feedback.deletedMessage'),
                };
                closeDeleteDialog();
            },
            onError: (errors) => {
                feedback.value = {
                    variant: 'destructive',
                    title: t('categories.feedback.deleteFailedTitle'),
                    message:
                        String(errors.delete ?? '') ||
                        t('categories.feedback.deleteFailedMessage'),
                };
                closeDeleteDialog();
            },
        },
    );
}

onMounted(() => {
    consumeCreateSharedCategoryQuery();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('categories.sharedPage.pageTitle')" />

        <SettingsLayout>
            <section
                class="overflow-hidden rounded-4xl border border-slate-200/80 bg-white/95 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85"
            >
                <div
                    class="border-b border-slate-200/70 bg-[radial-gradient(circle_at_top_left,rgba(99,102,241,0.16),transparent_34%),radial-gradient(circle_at_top_right,rgba(16,185,129,0.14),transparent_30%),linear-gradient(135deg,rgba(15,23,42,0.03),rgba(255,255,255,0))] px-6 py-6 sm:px-8 sm:py-8 dark:border-slate-800"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="max-w-3xl space-y-4">
                            <div
                                class="inline-flex w-fit items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-indigo-700 uppercase dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300"
                            >
                                <Network class="h-3.5 w-3.5" />
                                {{ t('categories.sharedPage.badge') }}
                            </div>

                            <div class="space-y-2">
                                <h1
                                    class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-slate-50"
                                >
                                    {{ t('categories.sharedPage.title') }}
                                </h1>
                                <p
                                    class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-[15px] dark:text-slate-300"
                                >
                                    {{ t('categories.sharedPage.description') }}
                                </p>
                            </div>
                        </div>

                        <Button
                            v-if="selectedAccount?.can_edit"
                            class="h-11 rounded-2xl px-5"
                            @click="openCreateCategory"
                        >
                            <Plus class="h-4 w-4" />
                            {{ t('categories.actions.newShared') }}
                        </Button>
                    </div>
                </div>

                <div class="space-y-6 px-4 py-5 sm:px-6 sm:py-6">
                    <Alert
                        v-if="feedback"
                        :variant="feedback.variant"
                        class="rounded-3xl border"
                    >
                        <CheckCircle2
                            v-if="feedback.variant === 'default'"
                            class="h-4 w-4"
                        />
                        <ShieldAlert v-else class="h-4 w-4" />
                        <AlertTitle>{{ feedback.title }}</AlertTitle>
                        <AlertDescription>
                            <p>{{ feedback.message }}</p>
                        </AlertDescription>
                    </Alert>

                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="translate-y-3 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-3 opacity-0"
                    >
                        <div
                            v-if="feedback"
                            class="pointer-events-none fixed right-4 bottom-4 z-50 max-w-sm sm:right-6 sm:bottom-6"
                        >
                            <div
                                class="pointer-events-auto overflow-hidden rounded-3xl border shadow-2xl"
                                :class="
                                    feedback.variant === 'default'
                                        ? 'border-emerald-200 bg-emerald-500 text-white'
                                        : 'border-rose-200 bg-rose-600 text-white'
                                "
                            >
                                <div class="flex items-start gap-3 px-4 py-4">
                                    <div
                                        class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15"
                                    >
                                        <CircleCheckBig
                                            v-if="
                                                feedback.variant === 'default'
                                            "
                                            class="h-5 w-5"
                                        />
                                        <ShieldAlert v-else class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold">
                                            {{ feedback.title }}
                                        </p>
                                        <p class="mt-1 text-sm text-white/90">
                                            {{ feedback.message }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <div
                        v-if="sharedCategories.accounts.length === 0"
                        class="rounded-[2rem] border border-dashed border-slate-300 bg-slate-50/80 px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-900/60"
                    >
                        <div
                            class="mx-auto flex max-w-md flex-col items-center gap-4"
                        >
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-950"
                            >
                                <FolderTree class="h-6 w-6" />
                            </div>
                            <div class="space-y-2">
                                <h2
                                    class="text-lg font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ t('categories.sharedPage.emptyTitle') }}
                                </h2>
                                <p
                                    class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        t(
                                            'categories.sharedPage.emptyDescription',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <template v-else>
                        <section
                            class="overflow-hidden rounded-[1.5rem] border border-slate-200/80 bg-white/90 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/75"
                        >
                            <div
                                class="divide-y divide-slate-200/80 xl:grid xl:grid-cols-[minmax(300px,360px)_minmax(340px,420px)] xl:divide-x xl:divide-y-0 xl:divide-slate-200/80 dark:divide-slate-800"
                            >
                                <div class="p-4 sm:p-5">
                                    <div class="space-y-4">
                                        <div class="space-y-2">
                                            <Label>{{
                                                t(
                                                    'categories.sharedPage.selectorLabel',
                                                )
                                            }}</Label>
                                            <Select
                                                v-model="selectedAccountUuid"
                                            >
                                                <SelectTrigger
                                                    class="h-12 w-full rounded-2xl"
                                                >
                                                    <SelectValue
                                                        :placeholder="
                                                            t(
                                                                'categories.sharedPage.selectorPlaceholder',
                                                            )
                                                        "
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="account in sharedCategories.accounts"
                                                        :key="account.uuid"
                                                        :value="account.uuid"
                                                    >
                                                        {{
                                                            accountDisplayLabel(
                                                                account,
                                                            )
                                                        }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <p
                                            class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                        >
                                            {{
                                                t(
                                                    'categories.sharedPage.selectorHint',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="selectedAccount?.can_edit"
                                    class="p-4 sm:p-5"
                                >
                                    <div class="flex h-full flex-col">
                                        <div class="space-y-4">
                                            <div
                                                class="flex flex-wrap items-start justify-between gap-3"
                                            >
                                                <div class="space-y-1">
                                                    <p
                                                        class="text-xs font-semibold tracking-[0.18em] text-slate-500 uppercase dark:text-slate-400"
                                                    >
                                                        {{
                                                            t(
                                                                'categories.sharedPage.materialize.label',
                                                            )
                                                        }}
                                                    </p>
                                                    <h3
                                                        class="text-base font-semibold text-slate-950 dark:text-slate-50"
                                                    >
                                                        {{
                                                            t(
                                                                'categories.sharedPage.materialize.title',
                                                            )
                                                        }}
                                                    </h3>
                                                </div>

                                                <Badge
                                                    variant="secondary"
                                                    class="w-fit rounded-full border border-slate-200/80 bg-white/85 px-2.5 py-1 text-[11px] font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-300"
                                                >
                                                    {{
                                                        t(
                                                            'categories.sharedPage.materialize.availableCount',
                                                            {
                                                                count: selectedImportableSourceCategories.length,
                                                            },
                                                        )
                                                    }}
                                                </Badge>
                                            </div>

                                            <p
                                                class="text-sm leading-6 text-slate-600 dark:text-slate-300"
                                            >
                                                {{
                                                    t(
                                                        'categories.sharedPage.materialize.hint',
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            v-if="
                                                selectedImportableSourceCategories.length >
                                                0
                                            "
                                            class="mt-5 flex flex-1 flex-col gap-3"
                                        >
                                            <div class="space-y-2">
                                                <Label
                                                    class="text-[11px] font-medium tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                                >
                                                    {{
                                                        t(
                                                            'categories.sharedPage.materialize.label',
                                                        )
                                                    }}
                                                </Label>
                                                <SearchableSelect
                                                    v-model="
                                                        selectedSourceCategoryUuid
                                                    "
                                                    :options="
                                                        selectedSourceCategories
                                                    "
                                                    :placeholder="
                                                        t(
                                                            'categories.sharedPage.materialize.placeholder',
                                                        )
                                                    "
                                                    :search-placeholder="
                                                        t(
                                                            'categories.sharedPage.materialize.searchPlaceholder',
                                                        )
                                                    "
                                                    :empty-label="
                                                        t(
                                                            'categories.sharedPage.materialize.noResults',
                                                        )
                                                    "
                                                    trigger-class="min-h-12 rounded-2xl border-slate-200 bg-white/90 text-left shadow-none dark:border-slate-700 dark:bg-slate-950/80"
                                                    content-class="z-[240]"
                                                    hierarchical
                                                />
                                            </div>

                                            <Button
                                                type="button"
                                                variant="secondary"
                                                class="mt-auto h-11 w-full rounded-2xl px-4"
                                                :disabled="
                                                    selectedSourceCategoryUuid ===
                                                    ''
                                                "
                                                @click="
                                                    materializeSourceCategory
                                                "
                                            >
                                                <Plus class="h-4 w-4" />
                                                {{
                                                    t(
                                                        'categories.sharedPage.materialize.action',
                                                    )
                                                }}
                                            </Button>
                                        </div>

                                        <div
                                            v-else
                                            class="mt-5 rounded-2xl border border-dashed border-slate-300/80 bg-white/70 px-4 py-3 text-sm leading-6 text-slate-600 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-300"
                                        >
                                            {{
                                                t(
                                                    'categories.sharedPage.materialize.empty',
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="selectedAccount"
                            class="rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-5 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] dark:border-slate-800 dark:bg-slate-950/75"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0 space-y-2">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2
                                            class="break-words text-lg font-semibold text-slate-950 dark:text-slate-50"
                                        >
                                            {{ selectedAccount.name }}
                                        </h2>
                                        <Badge
                                            class="rounded-full"
                                            :class="
                                                selectedAccount.is_owned
                                                    ? 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                                    : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                                            "
                                        >
                                            {{
                                                selectedAccount.is_owned
                                                    ? t(
                                                          'categories.sharedPage.accountBadgeOwned',
                                                      )
                                                    : t(
                                                          'categories.sharedPage.accountBadgeInvited',
                                                      )
                                            }}
                                        </Badge>
                                        <Badge
                                            class="rounded-full"
                                            :class="
                                                selectedAccount.can_edit
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                            "
                                        >
                                            {{
                                                selectedAccount.can_edit
                                                    ? t(
                                                          'categories.sharedPage.accountEditable',
                                                      )
                                                    : t(
                                                          'categories.sharedPage.accountReadOnly',
                                                      )
                                            }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="break-words text-sm text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            accountDisplayLabel(
                                                selectedAccount,
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        v-if="selectedAccount.membership_role"
                                        variant="secondary"
                                        class="rounded-full"
                                    >
                                        {{
                                            t(
                                                'categories.sharedPage.accountRole',
                                                {
                                                    role: selectedAccount.membership_role,
                                                },
                                            )
                                        }}
                                    </Badge>
                                </div>
                            </div>
                        </section>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <article
                                v-for="card in summaryCards"
                                :key="card.label"
                                class="rounded-3xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70"
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

                        <section class="space-y-4">
                            <div
                                class="flex flex-col gap-3 rounded-[1.75rem] border border-slate-200/80 bg-white/90 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950/75"
                            >
                                <div class="space-y-1">
                                    <p
                                        class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ t('categories.tree.title') }}
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ selectedAccount?.name }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        variant="secondary"
                                        class="rounded-full"
                                    >
                                        {{
                                            t(
                                                'categories.tree.badges.hierarchical',
                                            )
                                        }}
                                    </Badge>
                                    <Badge
                                        variant="secondary"
                                        class="rounded-full"
                                    >
                                        {{
                                            t('categories.tree.badges.fullPath')
                                        }}
                                    </Badge>
                                </div>
                            </div>

                            <CategoryTreeList
                                :items="selectedTreeCategories"
                                :empty-message="
                                    t('categories.sharedPage.emptyTree')
                                "
                                :read-only="isReadOnlyAccount"
                                :show-slug="false"
                                :max-parent-depth-for-children="1"
                                @edit="openEditCategory"
                                @create-child="openCreateChild"
                                @toggle-active="toggleCategory"
                                @delete="requestDelete"
                            />
                        </section>
                    </template>
                </div>
            </section>

            <CategoryFormSheet
                v-model:open="formOpen"
                :category="editingCategory"
                :suggested-parent-uuid="suggestedParentUuid"
                :parent-options="selectedFlatCategories"
                :direction-options="options.direction_types"
                :group-options="options.group_types"
                :store-url="formStoreUrl"
                :build-update-url="buildUpdateUrl"
                :create-success-message="t('categories.feedback.createSuccess')"
                :update-success-message="t('categories.feedback.updateSuccess')"
                :show-slug-field="false"
                :lock-classification-to-parent="true"
                @saved="handleSaved"
            />

            <Dialog
                :open="deletingCategory !== null"
                @update:open="!$event && closeDeleteDialog()"
            >
                <DialogContent class="max-w-lg rounded-3xl">
                    <DialogHeader>
                        <DialogTitle>{{
                            t('categories.deleteDialog.title')
                        }}</DialogTitle>
                        <DialogDescription>
                            <span v-if="deletingCategory">
                                {{ t('categories.deleteDialog.confirmPrefix') }}
                                <strong>{{ deletingCategory.name }}</strong>
                                {{ t('categories.deleteDialog.confirmSuffix') }}
                            </span>
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="deletingCategory" class="space-y-4">
                        <div
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div class="flex items-start gap-3">
                                <Trash2 class="mt-0.5 h-4 w-4 text-rose-500" />
                                <div class="space-y-2">
                                    <p
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ deletingCategory.full_path }}
                                    </p>
                                    <p
                                        class="text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            t(
                                                'categories.deleteDialog.blockedMessage',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-if="deleteReasons.length" class="space-y-2">
                            <p
                                class="text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{
                                    t(
                                        'categories.deleteDialog.blockedReasonsTitle',
                                    )
                                }}
                            </p>
                            <ul
                                class="space-y-2 text-sm text-slate-600 dark:text-slate-300"
                            >
                                <li
                                    v-for="reason in deleteReasons"
                                    :key="reason"
                                    class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900"
                                >
                                    {{ reason }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button variant="ghost" @click="closeDeleteDialog">
                            {{ t('categories.deleteDialog.cancelAction') }}
                        </Button>
                        <Button variant="destructive" @click="confirmDelete">
                            {{ t('categories.deleteDialog.confirmAction') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
