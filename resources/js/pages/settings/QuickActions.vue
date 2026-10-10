<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import {
    Car,
    Check,
    Coffee,
    Fuel,
    GripVertical,
    Heart,
    Home,
    Pencil,
    Plus,
    ShoppingCart,
    Trash2,
    Utensils,
    Wallet,
    Zap,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    index,
    reorder,
    store,
    update,
    destroy,
    toggleActive,
} from '@/actions/App/Http/Controllers/Settings/TransactionTemplateController';
import { storeTrackedItemOption } from '@/actions/App/Http/Controllers/TransactionsController';
import InputError from '@/components/InputError.vue';
import MobileAmountInput from '@/components/MobileAmountInput.vue';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import SharedExpenseFields from '@/components/shared-expenses/SharedExpenseFields.vue';
import MobileQuickChoices from '@/components/transactions/MobileQuickChoices.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { useUiCopy } from '@/i18n/ui-copy';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import {
    transactionTemplateIconChoices,
    transactionTemplateIcon,
    transactionTemplateColorStyle,
} from '@/lib/transaction-template-appearance';
import { templateReferenceMatches } from '@/lib/transaction-template-references';
import { transactionTemplateSplit } from '@/lib/transaction-template-split';
import type { TransactionTemplatePrefill } from '@/types/transaction-templates';

const ui = useUiCopy();

type Template = TransactionTemplatePrefill & {
    is_active: boolean;
    sort_order: number;
    usage_count: number;
    category_name: string | null;
    tracked_item_name: string | null;
    tracked_item_logo_url: string | null;
};
type CategoryOption = {
    uuid: string;
    name: string;
    full_path: string;
    parent_uuid: string | null;
    account_uuid: string | null;
    direction: string | null;
    icon: string | null;
    color: string | null;
    group_key: string | null;
};
type ReferenceOption = {
    uuid: string;
    name: string;
    logo_url: string | null;
    is_shared_expense_counterpart: boolean;
    category_uuids: string[];
    group_keys: string[];
};
const props = defineProps<{
    templates: Template[];
    accounts: { uuid: string; name: string }[];
    categories: CategoryOption[];
    references: ReferenceOption[];
}>();
const page = usePage();
const { t } = useI18n();
const moneyFormatLocale = computed(() =>
    String(page.props.auth.user?.format_locale ?? 'it-IT'),
);
const moneyCurrencyCode = computed(() =>
    String(page.props.auth.user?.base_currency_code ?? 'EUR'),
);
const mobile = useMediaQuery('(max-width: 767px)');
const open = ref(false);
const editing = ref<string | null>(null);
const deletingTemplate = ref<Template | null>(null);
const deleting = ref(false);
const deleteError = ref('');

const iconChoices = computed(
    () =>
        [
            ...transactionTemplateIconChoices.filter(
                (option) => option.value === option.value.toLowerCase(),
            ),
            { value: 'Zap', label: ui('Varie'), component: Zap },
            { value: 'Fuel', label: ui('Carburante'), component: Fuel },
            {
                value: 'ShoppingCart',
                label: ui('Spesa'),
                component: ShoppingCart,
            },
            {
                value: 'Utensils',
                label: ui('Ristorazione'),
                component: Utensils,
            },
            { value: 'Home', label: ui('Casa'), component: Home },
            { value: 'Car', label: ui('Trasporti'), component: Car },
            { value: 'Coffee', label: ui('Bar'), component: Coffee },
            { value: 'Heart', label: ui('Salute'), component: Heart },
            { value: 'Wallet', label: ui('Portafoglio'), component: Wallet },
        ] as const,
);
const colorChoices = computed(
    () =>
        [
            {
                value: 'primary',
                label: ui('Primario'),
                swatchClass: 'bg-primary',
            },
            { value: 'sky', label: ui('Azzurro'), swatchClass: 'bg-sky-500' },
            {
                value: 'violet',
                label: ui('Viola'),
                swatchClass: 'bg-violet-500',
            },
            {
                value: 'emerald',
                label: ui('Smeraldo'),
                swatchClass: 'bg-emerald-500',
            },
            { value: 'rose', label: ui('Rosa'), swatchClass: 'bg-rose-500' },
            { value: 'amber', label: ui('Ambra'), swatchClass: 'bg-amber-500' },
        ] as const,
);
const colorBadgeClasses: Record<string, string> = {
    primary: 'text-primary bg-primary/10',
    sky: 'text-sky-600 bg-sky-500/10 dark:text-sky-400',
    violet: 'text-violet-600 bg-violet-500/10 dark:text-violet-400',
    emerald: 'text-emerald-600 bg-emerald-500/10 dark:text-emerald-400',
    rose: 'text-rose-600 bg-rose-500/10 dark:text-rose-400',
    amber: 'text-amber-600 bg-amber-500/10 dark:text-amber-400',
};

const form = useForm({
    name: '',
    icon: 'Zap',
    color: 'primary',
    direction: 'expense' as 'income' | 'expense',
    account_uuid: '',
    category_uuid: '',
    tracked_item_uuid: '',
    description: '',
    notes: '',
    default_amount: '',
    shared_enabled: false,
    shared_counterparty_uuid: '',
    shared_payer: 'self' as 'self' | 'counterparty',
    shared_split_mode: 'equal' as 'equal' | 'percentage',
    my_share_basis_points: 5000,
    is_active: true,
});
const referenceCatalog = ref(props.references);
watch(
    () => props.references,
    (value) => {
        referenceCatalog.value = value;
    },
);
const newReferenceOpen = ref(false);
const newReferenceName = ref('');
const newReferenceWebsite = ref('');
const creatingReference = ref(false);
const iconSearch = ref('');
const visibleIcons = computed(() =>
    iconChoices.value
        .map((option) => ({
            ...option,
            label:
                'key' in option
                    ? t(`categories.form.iconOptions.${option.key}`)
                    : option.label,
        }))
        .filter((option) =>
            `${option.label} ${option.value}`
                .toLowerCase()
                .includes(iconSearch.value.toLowerCase()),
        ),
);
const selectedCategory = computed(() =>
    props.categories.find((item) => item.uuid === form.category_uuid),
);
const categoryContext = computed(() => {
    const uuids: string[] = [];
    let category = selectedCategory.value;

    while (category && !uuids.includes(category.uuid)) {
        uuids.push(category.uuid);
        category = props.categories.find(
            (item) => item.uuid === category?.parent_uuid,
        );
    }

    return uuids;
});
const compatibleReferences = computed(() =>
    referenceCatalog.value.filter((item) =>
        templateReferenceMatches(
            item,
            categoryContext.value,
            selectedCategory.value?.group_key ?? form.direction,
        ),
    ),
);
async function createReference() {
    creatingReference.value = true;
    form.clearErrors('tracked_item_uuid');

    try {
        const response = await fetch(storeTrackedItemOption.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                name: newReferenceName.value.trim(),
                website_url: newReferenceWebsite.value || null,
                account_uuid: form.account_uuid,
                category_uuid: form.category_uuid,
                type_key: selectedCategory.value?.group_key ?? form.direction,
            }),
        });
        const payload = await response.json();

        if (!response.ok) {
            const error = Object.values(payload.errors ?? {}).flat()[0];

            throw new Error(
                typeof error === 'string'
                    ? error
                    : ui('Impossibile creare il riferimento.'),
            );
        }

        const item = payload.item;
        const uuid = item.uuid ?? item.value;
        referenceCatalog.value = [
            ...referenceCatalog.value.filter(
                (reference) => reference.uuid !== uuid,
            ),
            {
                uuid,
                name: item.name ?? item.label,
                logo_url: item.logo_url,
                category_uuids: item.category_uuids ?? [form.category_uuid],
                group_keys: item.group_keys ?? [],
                is_shared_expense_counterpart:
                    item.is_shared_expense_counterpart ?? false,
            },
        ];
        form.tracked_item_uuid = uuid;
        newReferenceOpen.value = false;
        newReferenceName.value = '';
        newReferenceWebsite.value = '';
    } catch (error) {
        form.setError(
            'tracked_item_uuid',
            error instanceof Error
                ? error.message
                : ui('Impossibile creare il riferimento.'),
        );
    } finally {
        creatingReference.value = false;
    }
}
const accountOptions = computed(() =>
    props.accounts.map((item) => ({ value: item.uuid, label: item.name })),
);
const filteredCategories = computed(() =>
    props.categories.filter(
        (item) =>
            (item.direction === form.direction || item.direction === 'mixed') &&
            (!form.account_uuid ||
                !item.account_uuid ||
                item.account_uuid === form.account_uuid),
    ),
);
const quickCategories = computed(() =>
    filteredCategories.value
        .filter((item) => !item.parent_uuid)
        .slice(0, 8)
        .map((item) => ({
            value: item.uuid,
            label: item.name,
            icon: item.icon,
            color: item.color,
        })),
);
const categoryOptions = computed(() =>
    filteredCategories.value.map((item) => ({
        value: item.uuid,
        label: item.full_path,
        icon: item.icon,
        color: item.color,
    })),
);
const quickReferences = computed(() =>
    [
        ...compatibleReferences.value.filter(
            (item) => item.uuid === form.tracked_item_uuid,
        ),
        ...compatibleReferences.value.filter(
            (item) => item.uuid !== form.tracked_item_uuid,
        ),
    ]
        .slice(0, 8)
        .map((item) => ({
            value: item.uuid,
            label: item.name,
            logo_url: item.logo_url,
        })),
);
const referenceOptions = computed(() =>
    referenceCatalog.value.map((item) => ({
        value: item.uuid,
        label: item.name,
        logo_url: item.logo_url,
    })),
);
const selectedReferenceChoices = computed(() =>
    referenceOptions.value.filter(
        (item) => item.value === form.tracked_item_uuid,
    ),
);
function beginReferenceCreation(name: string): void {
    newReferenceName.value = name;
    newReferenceOpen.value = true;
}
const counterparties = computed(() =>
    referenceCatalog.value
        .filter((item) => item.is_shared_expense_counterpart)
        .map((item) => ({ value: item.uuid, label: item.name })),
);

watch([() => form.direction, () => form.account_uuid], () => {
    if (
        form.category_uuid &&
        !filteredCategories.value.some(
            (item) => item.uuid === form.category_uuid,
        )
    ) {
        form.category_uuid = '';
    }
});

const sharedAmount = computed(() => form.default_amount || '100');
const sharedCustom = computed({
    get: () => form.shared_split_mode === 'percentage',
    set: (value: boolean) => {
        form.shared_split_mode = value ? 'percentage' : 'equal';

        if (!value) {
            form.my_share_basis_points = 5000;
        }
    },
});
const myShareAmount = computed({
    get: () =>
        transactionTemplateSplit(sharedAmount.value, form.my_share_basis_points)
            ?.myShare ?? '0.00',
    set: (value: string) => {
        const total = Number(String(sharedAmount.value).replace(',', '.')) || 0;
        const mine = Number(String(value).replace(',', '.')) || 0;

        if (total > 0) {
            form.my_share_basis_points = Math.min(
                10000,
                Math.max(0, Math.round((mine / total) * 10000)),
            );
        }
    },
});
const counterpartyShareAmount = computed({
    get: () =>
        transactionTemplateSplit(sharedAmount.value, form.my_share_basis_points)
            ?.counterpartyShare ?? '0.00',
    set: (value: string) => {
        const total = Number(String(sharedAmount.value).replace(',', '.')) || 0;
        const other = Number(String(value).replace(',', '.')) || 0;

        if (total > 0) {
            form.my_share_basis_points = Math.min(
                10000,
                Math.max(0, Math.round(((total - other) / total) * 10000)),
            );
        }
    },
});
function edit(item?: Template) {
    newReferenceOpen.value = false;
    iconSearch.value = '';
    form.reset();
    form.clearErrors();
    editing.value = item?.uuid ?? null;

    if (item) {
        Object.assign(form, {
            name: item.name,
            icon: item.icon,
            color: item.color,
            direction: item.direction,
            account_uuid: item.account_uuid ?? '',
            category_uuid: item.category_uuid ?? '',
            tracked_item_uuid: item.tracked_item_uuid ?? '',
            description: item.description ?? '',
            notes: item.notes ?? '',
            default_amount: item.default_amount ?? '',
            shared_enabled: item.shared_enabled,
            shared_counterparty_uuid: item.shared_counterparty_uuid ?? '',
            shared_payer: item.shared_payer,
            shared_split_mode: item.shared_split_mode,
            my_share_basis_points: item.my_share_basis_points,
            is_active: item.is_active,
        });
    }

    open.value = true;
}
function save() {
    form.transform((data) => ({
        ...data,
        account_uuid: data.account_uuid || null,
        category_uuid: data.category_uuid || null,
        tracked_item_uuid: data.tracked_item_uuid || null,
        shared_counterparty_uuid: data.shared_counterparty_uuid || null,
        default_amount: data.default_amount || null,
    }));
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (editing.value) {
        form.patch(update.url(editing.value), options);
    } else {
        form.post(store.url(), options);
    }
}
function remove(item: Template): void {
    if (deleting.value) {
        return;
    }

    deletingTemplate.value = item;
    deleteError.value = '';
}

function closeDeleteDialog(): void {
    if (!deleting.value) {
        deletingTemplate.value = null;
        deleteError.value = '';
    }
}

function confirmDelete(): void {
    if (!deletingTemplate.value || deleting.value) {
        return;
    }

    deleting.value = true;
    deleteError.value = '';
    router.delete(destroy.url(deletingTemplate.value.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            deletingTemplate.value = null;
        },
        onError: (errors) => {
            deleteError.value =
                Object.values(errors).flat().join(' ') ||
                ui('Impossibile eliminare l’azione rapida. Riprova.');
        },
        onFinish: () => {
            deleting.value = false;

            if (deletingTemplate.value && !deleteError.value) {
                deleteError.value = ui(
                    'Impossibile eliminare l’azione rapida. Riprova.',
                );
            }
        },
    });
}

const localTemplates = ref<Template[]>(props.templates);
watch(
    () => props.templates,
    (value) => {
        localTemplates.value = value;
    },
);
const draggedUuid = ref<string | null>(null);
const dropTargetUuid = ref<string | null>(null);
const activePointerId = ref<number | null>(null);

function templateAtPointer(event: PointerEvent): Template | undefined {
    const uuid = document
        .elementFromPoint(event.clientX, event.clientY)
        ?.closest<HTMLElement>('[data-template-uuid]')?.dataset.templateUuid;

    return localTemplates.value.find((item) => item.uuid === uuid);
}
function startDrag(event: PointerEvent, item: Template): void {
    if (event.button !== 0) {
        return;
    }

    event.preventDefault();
    activePointerId.value = event.pointerId;
    draggedUuid.value = item.uuid;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}
function moveDrag(event: PointerEvent): void {
    if (activePointerId.value !== event.pointerId) {
        return;
    }

    event.preventDefault();
    const target = templateAtPointer(event);
    dropTargetUuid.value =
        target && target.uuid !== draggedUuid.value ? target.uuid : null;
}
function clearDrag(): void {
    activePointerId.value = null;
    draggedUuid.value = null;
    dropTargetUuid.value = null;
}
function endDrag(event: PointerEvent): void {
    if (activePointerId.value !== event.pointerId) {
        return;
    }

    const target = templateAtPointer(event);

    if (target && draggedUuid.value && target.uuid !== draggedUuid.value) {
        reorderTemplates(draggedUuid.value, target.uuid);
    }

    clearDrag();
}
function reorderTemplates(fromUuid: string, toUuid: string): void {
    const ordered = [...localTemplates.value];
    const from = ordered.findIndex((item) => item.uuid === fromUuid);
    const to = ordered.findIndex((item) => item.uuid === toUuid);

    if (from === -1 || to === -1 || from === to) {
        return;
    }

    const [moved] = ordered.splice(from, 1);
    ordered.splice(to, 0, moved);
    const previous = localTemplates.value;
    localTemplates.value = ordered;

    router.patch(
        reorder.url(),
        { uuids: ordered.map((item) => item.uuid) },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                localTemplates.value = previous;
            },
        },
    );
}

onMounted(() => {
    if (new URL(window.location.href).searchParams.get('create') === '1') {
        edit();
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: ui('Azioni rapide'), href: index() }]">
        <Head :title="ui('Azioni rapide')" />
        <SettingsLayout>
            <section class="min-w-0 space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0 flex-1 basis-48">
                        <h2 class="text-xl font-semibold">
                            {{ ui('Azioni rapide') }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                ui(
                                    'Template personali per il normale form Transazione.',
                                )
                            }}
                        </p>
                    </div>
                    <Button @click="edit()"
                        ><Plus class="size-4" /><span>{{
                            ui('Nuova')
                        }}</span></Button
                    >
                </div>
                <p
                    v-if="!localTemplates.length"
                    class="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground"
                >
                    {{ ui('Crea un template, ad esempio Benzina o Spesa.') }}
                </p>
                <article
                    v-for="item in localTemplates"
                    :key="item.uuid"
                    :data-template-uuid="item.uuid"
                    class="grid min-w-0 grid-cols-[2rem_2.5rem_minmax(0,1fr)] items-start gap-3 rounded-2xl border p-3 transition-colors sm:flex sm:flex-wrap sm:items-center"
                    :class="[
                        !item.is_active && 'opacity-60',
                        draggedUuid === item.uuid && 'opacity-50',
                        dropTargetUuid === item.uuid &&
                            'border-primary ring-1 ring-primary',
                    ]"
                >
                    <button
                        type="button"
                        class="flex h-10 w-8 shrink-0 cursor-grab touch-none items-center justify-center rounded-xl text-muted-foreground transition hover:bg-muted active:cursor-grabbing"
                        :aria-label="ui('Trascina per riordinare')"
                        :title="ui('Trascina per riordinare')"
                        @pointerdown="startDrag($event, item)"
                        @pointermove="moveDrag"
                        @pointerup="endDrag"
                        @pointercancel="clearDrag"
                    >
                        <GripVertical class="size-5" />
                    </button>
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                        :class="
                            colorBadgeClasses[item.color] ??
                            colorBadgeClasses.primary
                        "
                        :style="transactionTemplateColorStyle(item.color)"
                        ><component
                            :is="transactionTemplateIcon(item.icon)"
                            class="size-5"
                    /></span>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold break-words">
                            {{ item.name }}
                        </h3>
                        <p
                            class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground"
                        >
                            <span>{{
                                item.direction === 'income'
                                    ? ui('Entrata')
                                    : ui('Uscita')
                            }}</span>
                            <span v-if="item.category_name"
                                >· {{ item.category_name }}</span
                            >
                            <span v-if="item.tracked_item_name"
                                >· {{ item.tracked_item_name }}</span
                            >
                            <span
                                >·
                                {{
                                    ui('Utilizzi: {count}', {
                                        count: item.usage_count,
                                    })
                                }}</span
                            >
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <Badge
                                v-if="item.shared_enabled"
                                class="rounded-full bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                                >{{ ui('Condivisa') }}</Badge
                            ><Badge
                                v-if="!item.is_active"
                                variant="secondary"
                                class="rounded-full"
                                >{{ ui('Disattivata') }}</Badge
                            >
                        </div>
                        <p
                            v-for="message in item.issues"
                            :key="message"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ message }}
                        </p>
                    </div>
                    <div
                        class="col-span-3 flex w-full items-center justify-end gap-2 border-t pt-3 sm:w-auto sm:border-0 sm:pt-0"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            class="min-h-11 flex-1 sm:min-h-9 sm:flex-none"
                            @click="
                                router.patch(
                                    toggleActive.url(item.uuid),
                                    {},
                                    { preserveScroll: true },
                                )
                            "
                            >{{
                                item.is_active
                                    ? ui('Disattiva')
                                    : ui('Attiva azione')
                            }}</Button
                        ><Button
                            variant="ghost"
                            size="icon"
                            class="size-11 shrink-0"
                            :aria-label="
                                ui('Modifica {name}', { name: item.name })
                            "
                            @click="edit(item)"
                            ><Pencil class="size-4" /></Button
                        ><Button
                            variant="ghost"
                            size="icon"
                            class="size-11 shrink-0"
                            :aria-label="
                                ui('Elimina {name}', { name: item.name })
                            "
                            @click="remove(item)"
                            ><Trash2 class="size-4"
                        /></Button>
                    </div>
                </article>
            </section>
            <Sheet v-model:open="open"
                ><SheetContent
                    :side="mobile ? 'bottom' : 'right'"
                    class="flex h-[92dvh] min-w-0 flex-col overflow-hidden rounded-t-3xl md:h-full md:max-h-none md:w-[480px] md:rounded-none"
                >
                    <SheetHeader class="shrink-0 px-4 pt-5 pr-12 text-left"
                        ><SheetTitle>{{
                            editing
                                ? ui('Modifica azione rapida')
                                : ui('Nuova azione rapida')
                        }}</SheetTitle
                        ><SheetDescription>{{
                            ui(
                                'Solo precompilazione: nessun movimento viene registrato qui.',
                            )
                        }}</SheetDescription></SheetHeader
                    >
                    <form
                        class="flex min-h-0 flex-1 flex-col"
                        @submit.prevent="save"
                    >
                        <div
                            class="min-h-0 min-w-0 flex-1 space-y-5 overflow-x-hidden overflow-y-auto overscroll-contain p-4 [&_button[role=combobox]]:min-h-11 [&_input]:min-h-11 [&_input]:text-base [&_textarea]:text-base"
                        >
                            <div>
                                <Label for="template-name">{{
                                    ui('Nome')
                                }}</Label
                                ><Input
                                    id="template-name"
                                    v-model="form.name"
                                    :placeholder="ui('Benzina')"
                                /><InputError :message="form.errors.name" />
                            </div>
                            <div
                                class="grid grid-cols-2 gap-2"
                                role="group"
                                :aria-label="ui('Direzione')"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    :aria-pressed="form.direction === 'income'"
                                    :class="[
                                        'min-h-11 justify-center rounded-2xl text-sm font-semibold transition-colors',
                                        form.direction === 'income'
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : '',
                                    ]"
                                    @click="form.direction = 'income'"
                                    >{{ ui('Entrata') }}</Button
                                ><Button
                                    type="button"
                                    variant="outline"
                                    :aria-pressed="form.direction === 'expense'"
                                    :class="[
                                        'min-h-11 justify-center rounded-2xl text-sm font-semibold transition-colors',
                                        form.direction === 'expense'
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : '',
                                    ]"
                                    @click="form.direction = 'expense'"
                                    >{{ ui('Uscita') }}</Button
                                >
                            </div>
                            <div class="grid gap-2">
                                <Label>{{ ui('Icona') }}</Label>
                                <Input
                                    v-model="iconSearch"
                                    :aria-label="ui('Cerca icona')"
                                    :placeholder="ui('Cerca icona')"
                                />
                                <div
                                    class="grid max-h-48 grid-cols-5 gap-2 overflow-y-auto"
                                    role="group"
                                    :aria-label="ui('Icona')"
                                >
                                    <button
                                        v-for="option in visibleIcons"
                                        :key="option.value"
                                        type="button"
                                        :aria-pressed="
                                            form.icon === option.value
                                        "
                                        :aria-label="option.label"
                                        :title="option.label"
                                        class="flex min-h-11 items-center justify-center rounded-2xl border transition-colors"
                                        :class="
                                            form.icon === option.value
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border bg-background text-foreground hover:bg-muted'
                                        "
                                        @click="form.icon = option.value"
                                    >
                                        <component
                                            :is="option.component"
                                            class="size-5"
                                        />
                                    </button>
                                </div>
                                <InputError :message="form.errors.icon" />
                            </div>
                            <div class="grid gap-2">
                                <Label>{{ ui('Colore') }}</Label>
                                <div
                                    class="grid grid-cols-6 gap-2"
                                    role="group"
                                    :aria-label="ui('Colore')"
                                >
                                    <button
                                        v-for="option in colorChoices"
                                        :key="option.value"
                                        type="button"
                                        :aria-pressed="
                                            form.color === option.value
                                        "
                                        :aria-label="option.label"
                                        :title="option.label"
                                        class="flex size-10 items-center justify-center rounded-full border-2 transition-transform hover:scale-105"
                                        :class="[
                                            option.swatchClass,
                                            form.color === option.value
                                                ? 'border-foreground'
                                                : 'border-transparent',
                                        ]"
                                        @click="form.color = option.value"
                                    >
                                        <Check
                                            v-if="form.color === option.value"
                                            class="size-4 text-white"
                                        />
                                    </button>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Label for="template-custom-color">{{
                                        ui('Personalizzato')
                                    }}</Label>
                                    <input
                                        id="template-custom-color"
                                        type="color"
                                        :value="
                                            form.color.startsWith('#')
                                                ? form.color
                                                : '#6d28d9'
                                        "
                                        class="size-11 rounded-lg border p-1"
                                        @input="
                                            form.color = (
                                                $event.target as HTMLInputElement
                                            ).value
                                        "
                                    />
                                    <Input
                                        v-if="form.color.startsWith('#')"
                                        v-model="form.color"
                                        :aria-label="ui('Codice colore')"
                                        maxlength="7"
                                        class="min-w-0 flex-1"
                                    />
                                </div>
                                <InputError :message="form.errors.color" />
                            </div>
                            <div class="grid min-w-0 gap-2">
                                <Label>{{ ui('Conto') }}</Label
                                ><MobileSearchableSelect
                                    v-model="form.account_uuid"
                                    :options="accountOptions"
                                    :mobile-title="ui('Conto')"
                                    :placeholder="ui('Non preimpostato')"
                                    :search-placeholder="ui('Cerca conto')"
                                    clearable
                                /><InputError
                                    :message="form.errors.account_uuid"
                                />
                            </div>
                            <div class="grid min-w-0 gap-2">
                                <Label>{{
                                    ui('Categoria / sottocategoria')
                                }}</Label>
                                <MobileQuickChoices
                                    v-if="!form.category_uuid"
                                    v-model="form.category_uuid"
                                    :options="quickCategories"
                                    appearance="category"
                                />
                                <MobileSearchableSelect
                                    v-model="form.category_uuid"
                                    :options="categoryOptions"
                                    :mobile-title="ui('Categoria')"
                                    :placeholder="ui('Cerca altra categoria')"
                                    :search-placeholder="ui('Cerca categoria')"
                                    clearable
                                /><InputError
                                    :message="form.errors.category_uuid"
                                />
                            </div>
                            <div class="grid min-w-0 gap-2">
                                <Label>{{ ui('Riferimento') }}</Label>
                                <MobileQuickChoices
                                    v-if="
                                        !form.tracked_item_uuid &&
                                        quickReferences.length
                                    "
                                    v-model="form.tracked_item_uuid"
                                    :options="quickReferences"
                                    appearance="reference"
                                />
                                <div
                                    v-if="selectedReferenceChoices.length"
                                    class="flex min-w-0 items-center gap-2 rounded-2xl border border-primary/30 bg-primary/5 p-2"
                                >
                                    <MobileQuickChoices
                                        v-model="form.tracked_item_uuid"
                                        :options="selectedReferenceChoices"
                                        appearance="reference"
                                        class="min-w-0 flex-1"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-11 shrink-0"
                                        :aria-label="ui('Rimuovi riferimento')"
                                        @click="form.tracked_item_uuid = ''"
                                        ><X class="size-4"
                                    /></Button>
                                </div>
                                <MobileSearchableSelect
                                    v-if="
                                        !form.tracked_item_uuid ||
                                        !selectedReferenceChoices.length
                                    "
                                    v-model="form.tracked_item_uuid"
                                    :options="referenceOptions"
                                    :mobile-title="ui('Riferimento')"
                                    :placeholder="ui('Cerca altro riferimento')"
                                    :search-placeholder="
                                        ui('Cerca riferimento')
                                    "
                                    :creatable="
                                        !!form.account_uuid &&
                                        !!form.category_uuid
                                    "
                                    :create-label="ui('Crea nuovo riferimento')"
                                    @create-option="beginReferenceCreation"
                                    create-only-when-empty
                                    clearable
                                /><InputError
                                    :message="form.errors.tracked_item_uuid"
                                />
                                <p
                                    v-if="
                                        form.tracked_item_uuid &&
                                        !compatibleReferences.some(
                                            (item) =>
                                                item.uuid ===
                                                form.tracked_item_uuid,
                                        )
                                    "
                                    class="text-sm text-destructive"
                                    role="alert"
                                >
                                    {{
                                        ui(
                                            'Il riferimento selezionato non è compatibile con questa categoria. Sostituiscilo prima di salvare.',
                                        )
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="min-h-11 w-fit"
                                    :disabled="
                                        !form.account_uuid ||
                                        !form.category_uuid
                                    "
                                    @click="
                                        newReferenceOpen = !newReferenceOpen
                                    "
                                    ><Plus class="size-4" />{{
                                        ui('Nuovo riferimento')
                                    }}</Button
                                >
                                <p
                                    v-if="
                                        !form.account_uuid ||
                                        !form.category_uuid
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        ui(
                                            'Scegli conto e categoria per creare un riferimento associato.',
                                        )
                                    }}
                                </p>
                                <div
                                    v-if="newReferenceOpen"
                                    class="grid gap-3 rounded-2xl border bg-muted/30 p-3"
                                >
                                    <Label for="template-reference-name">{{
                                        ui('Nome riferimento')
                                    }}</Label
                                    ><Input
                                        id="template-reference-name"
                                        v-model="newReferenceName"
                                    />
                                    <Label for="template-reference-website">{{
                                        ui('Sito web per il logo (opzionale)')
                                    }}</Label
                                    ><Input
                                        id="template-reference-website"
                                        v-model="newReferenceWebsite"
                                        type="url"
                                        placeholder="https://www.example.it"
                                    />
                                    <Button
                                        type="button"
                                        :disabled="
                                            creatingReference ||
                                            !newReferenceName.trim()
                                        "
                                        @click="createReference"
                                        >{{ ui('Crea e seleziona') }}</Button
                                    >
                                </div>
                            </div>
                            <div>
                                <Label for="template-description">{{
                                    ui('Descrizione')
                                }}</Label
                                ><Input
                                    id="template-description"
                                    v-model="form.description"
                                /><InputError
                                    :message="form.errors.description"
                                />
                            </div>
                            <MobileAmountInput
                                id="template-amount"
                                v-model="form.default_amount"
                                :label="ui('Importo predefinito (facoltativo)')"
                                :mobile-title="ui('Importo predefinito')"
                                :format-locale="moneyFormatLocale"
                                :currency-code="moneyCurrencyCode"
                                :placeholder="
                                    ui('Vuoto: lo inserisci al momento')
                                "
                                class="rounded-2xl"
                                :error="form.errors.default_amount"
                            />
                            <div class="grid gap-2">
                                <Label for="template-notes">{{
                                    ui('Note')
                                }}</Label
                                ><textarea
                                    id="template-notes"
                                    v-model="form.notes"
                                    rows="3"
                                    class="min-h-24 w-full rounded-xl border border-input bg-background p-3 text-base outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                /><InputError :message="form.errors.notes" />
                            </div>
                            <SharedExpenseFields
                                v-model:enabled="form.shared_enabled"
                                v-model:counterparty="
                                    form.shared_counterparty_uuid
                                "
                                v-model:paid-by="form.shared_payer"
                                v-model:my-share="myShareAmount"
                                v-model:counterparty-share="
                                    counterpartyShareAmount
                                "
                                v-model:custom="sharedCustom"
                                :counterparties="counterparties"
                                :amount="sharedAmount"
                                :errors="{
                                    shared_expense_counterparty_uuid:
                                        form.errors.shared_counterparty_uuid,
                                }"
                            />
                            <label class="flex min-h-11 items-center gap-3"
                                ><Checkbox
                                    :model-value="form.is_active"
                                    @update:model-value="
                                        form.is_active = $event === true
                                    "
                                />{{ ui('Attiva') }}</label
                            >
                            <InputError
                                v-for="(message, key) in form.errors"
                                :key="key"
                                :message="message"
                            />
                        </div>
                        <div
                            class="border-t p-4 pb-[calc(1rem+env(safe-area-inset-bottom))]"
                        >
                            <Button
                                type="submit"
                                class="min-h-11 w-full"
                                :disabled="form.processing"
                                >{{ ui('Salva azione rapida') }}</Button
                            >
                        </div>
                    </form>
                </SheetContent></Sheet
            >
            <Dialog
                :open="deletingTemplate !== null"
                @update:open="!$event ? closeDeleteDialog() : null"
            >
                <DialogContent
                    :show-close-button="!deleting"
                    @escape-key-down="deleting && $event.preventDefault()"
                    @interact-outside="deleting && $event.preventDefault()"
                >
                    <DialogHeader>
                        <DialogTitle>{{
                            ui('Eliminare questa azione rapida?')
                        }}</DialogTitle>
                        <DialogDescription>
                            {{
                                ui(
                                    'L’azione rapida “{name}” verrà rimossa. Questa operazione non può essere annullata.',
                                    { name: deletingTemplate?.name ?? '' },
                                )
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <p
                        v-if="deleteError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ deleteError }}
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="deleting"
                            @click="closeDeleteDialog"
                            >{{ ui('Annulla') }}</Button
                        >
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="deleting"
                            :aria-busy="deleting"
                            @click="confirmDelete"
                            >{{
                                deleting ? ui('Eliminazione…') : ui('Elimina')
                            }}</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>
