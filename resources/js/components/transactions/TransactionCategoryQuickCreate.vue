<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CategoryFormSheet from '@/components/categories/CategoryFormSheet.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store as storeCategory } from '@/routes/categories';
import { store as storeSharedCategory } from '@/routes/shared-categories';
import type {
    CategoryItem,
    MonthlyTransactionSheetEditorAccountOption,
    MonthlyTransactionSheetEditorCategoryOption,
} from '@/types';

const props = defineProps<{
    accountUuid: string;
    typeKey: string;
    categories: Record<string, MonthlyTransactionSheetEditorCategoryOption[]>;
    accounts: MonthlyTransactionSheetEditorAccountOption[];
    typeOptions: Array<{ value: string; label: string }>;
}>();

const categoryUuid = defineModel<string>({ default: '' });

const { t, locale } = useI18n();
const categoryQuickCreateChoiceOpen = ref(false);
const categoryQuickCreateOpen = ref(false);
const categoryQuickCreateKind = ref<'category' | 'subcategory'>('category');
const createdCategories = ref<
    Record<string, MonthlyTransactionSheetEditorCategoryOption[]>
>({});

function categoriesForSelectedAccount(accountUuid: string) {
    if (accountUuid === '') {
        return [];
    }

    return [
        ...(props.categories[accountUuid] ?? []),
        ...(createdCategories.value[accountUuid] ?? []),
    ];
}

function resolveAccountCategoryContributorUserIds(
    accountUuid: string,
): number[] {
    if (accountUuid === '') {
        return [];
    }

    return (
        props.accounts.find((account) => account.value === accountUuid)
            ?.category_contributor_user_ids ?? []
    );
}

const matchingCategories = computed(() => {
    const contributorUserIds = resolveAccountCategoryContributorUserIds(
        props.accountUuid,
    );

    return categoriesForSelectedAccount(props.accountUuid).filter(
        (category) =>
            (contributorUserIds.length === 0 ||
                contributorUserIds.includes(category.owner_user_id ?? -1)) &&
            category.type_key === props.typeKey,
    );
});

const quickCreateCategoryContext = computed(() => {
    const selectedCategory = categoriesForSelectedAccount(
        props.accountUuid,
    ).find(
        (category) =>
            category.value === categoryUuid.value &&
            category.type_key === props.typeKey,
    );
    const matchingCategory =
        selectedCategory ??
        matchingCategories.value.find(
            (category) =>
                category.group_type !== null &&
                category.direction_type !== null,
        );

    return {
        directionType:
            matchingCategory?.direction_type ??
            (props.typeKey === 'income' ? 'income' : 'expense'),
        groupType: matchingCategory?.group_type ?? props.typeKey,
    };
});

const quickCreateParentOptions = computed<CategoryItem[]>(() => {
    const { directionType, groupType } = quickCreateCategoryContext.value;

    return matchingCategories.value
        .filter(
            (category) =>
                category.direction_type === directionType &&
                category.group_type === groupType,
        )
        .map((category) => ({
            uuid: category.uuid,
            parent_uuid: category.ancestor_uuids.at(-1) ?? null,
            account_uuid: category.account_uuid ?? null,
            account_name: null,
            name: category.label,
            slug: category.slug ?? '',
            icon: category.icon ?? null,
            color: category.color ?? null,
            direction_type: category.direction_type ?? '',
            direction_label: category.direction_type ?? '',
            group_type: category.group_type ?? '',
            group_label: category.group_type ?? '',
            sort_order: category.sort_order ?? 0,
            is_active: category.is_active,
            is_selectable: category.is_selectable ?? false,
            is_system: false,
            scope_kind: 'personal',
            is_personal: true,
            is_shared: false,
            foundation_key: null,
            depth: category.ancestor_uuids.length,
            subtree_height: 0,
            full_path: category.full_path ?? category.label,
            children_count: 0,
            usage_count: 0,
            is_deletable: false,
            ancestor_uuids: category.ancestor_uuids,
            descendant_uuids: [],
        }));
});

const quickCreateDirectionOptions = computed(() =>
    props.typeOptions.filter(
        (option) =>
            option.value === quickCreateCategoryContext.value.directionType,
    ),
);

const quickCreateGroupOptions = computed(() =>
    props.typeOptions.filter(
        (option) => option.value === quickCreateCategoryContext.value.groupType,
    ),
);

const quickCreateSuggestedParentUuid = computed(() =>
    categoryQuickCreateKind.value === 'subcategory' &&
    quickCreateParentOptions.value.some(
        (category) => category.uuid === categoryUuid.value,
    )
        ? categoryUuid.value
        : null,
);

const quickCreateStoreUrl = computed(() => {
    const account = props.accounts.find(
        (option) => option.value === props.accountUuid,
    );

    return account?.uses_account_scoped_category_catalog
        ? storeSharedCategory.url(props.accountUuid)
        : storeCategory.url();
});

function openQuickCreate(): void {
    categoryQuickCreateChoiceOpen.value = true;
}

function openCategoryQuickCreate(kind: 'category' | 'subcategory'): void {
    categoryQuickCreateKind.value = kind;
    categoryQuickCreateChoiceOpen.value = false;
    categoryQuickCreateOpen.value = true;
}

function updateCategoryQuickCreateOpen(open: boolean): void {
    categoryQuickCreateOpen.value = open;

    if (!open) {
        categoryQuickCreateKind.value = 'category';
    }
}

function selectCreatedCategory(category: Record<string, unknown>): void {
    const uuid = String(category.uuid ?? '');

    if (uuid === '') {
        return;
    }

    const { directionType, groupType } = quickCreateCategoryContext.value;

    if (
        String(category.direction_type ?? '') !== directionType ||
        String(category.group_type ?? '') !== groupType
    ) {
        return;
    }

    const parentUuid = category.parent_uuid
        ? String(category.parent_uuid)
        : null;
    const parent = parentUuid
        ? categoriesForSelectedAccount(props.accountUuid).find(
              (option) => option.uuid === parentUuid,
          )
        : null;
    const option: MonthlyTransactionSheetEditorCategoryOption = {
        id: undefined,
        value: uuid,
        uuid,
        label: parent
            ? `${parent.label} › ${String(category.name ?? '')}`
            : String(category.name ?? ''),
        full_path: parent
            ? `${parent.full_path ?? parent.label} › ${String(category.name ?? '')}`
            : String(category.name ?? ''),
        slug: String(category.slug ?? ''),
        account_uuid: props.accountUuid,
        owner_user_id: Number(category.owner_user_id ?? 0),
        type_key: String(category.group_type ?? props.typeKey),
        direction_type: String(category.direction_type ?? ''),
        group_type: String(category.group_type ?? ''),
        icon: category.icon ? String(category.icon) : null,
        color: category.color ? String(category.color) : null,
        is_active: Boolean(category.is_active),
        is_selectable: Boolean(category.is_selectable),
        sort_order: Number(category.sort_order ?? 0),
        ancestor_ids: [],
        ancestor_uuids: parentUuid
            ? [...(parent?.ancestor_uuids ?? []), parentUuid]
            : [],
    };
    createdCategories.value = {
        ...createdCategories.value,
        [props.accountUuid]: [
            ...(createdCategories.value[props.accountUuid] ?? []).filter(
                (item) => item.value !== uuid,
            ),
            option,
        ].sort((first, second) =>
            (first.full_path ?? first.label).localeCompare(
                second.full_path ?? second.label,
                locale.value,
            ),
        ),
    };
    categoryUuid.value = uuid;
}
</script>

<template>
    <slot :open-quick-create="openQuickCreate" />

    <Dialog
        :open="categoryQuickCreateChoiceOpen"
        @update:open="categoryQuickCreateChoiceOpen = $event"
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    t('transactions.form.quickCategory.title')
                }}</DialogTitle>
                <DialogDescription>{{
                    t('transactions.form.quickCategory.description')
                }}</DialogDescription>
            </DialogHeader>
            <div class="grid gap-3 sm:grid-cols-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-auto min-h-24 justify-start rounded-2xl px-4 py-4 text-left"
                    @click="openCategoryQuickCreate('category')"
                >
                    {{ t('transactions.form.quickCategory.category') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="h-auto min-h-24 justify-start rounded-2xl px-4 py-4 text-left"
                    @click="openCategoryQuickCreate('subcategory')"
                >
                    {{ t('transactions.form.quickCategory.subcategory') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <CategoryFormSheet
        :open="categoryQuickCreateOpen"
        :parent-options="quickCreateParentOptions"
        :direction-options="quickCreateDirectionOptions"
        :group-options="quickCreateGroupOptions"
        :parent-direction-type="quickCreateCategoryContext.directionType"
        :parent-group-type="quickCreateCategoryContext.groupType"
        :suggested-parent-uuid="quickCreateSuggestedParentUuid"
        :initial-direction-type="quickCreateCategoryContext.directionType"
        :initial-group-type="quickCreateCategoryContext.groupType"
        :lock-classification-to-parent="true"
        :require-parent="categoryQuickCreateKind === 'subcategory'"
        :store-url="quickCreateStoreUrl"
        json-store
        @update:open="updateCategoryQuickCreateOpen"
        @created="selectCreatedCategory"
    />
</template>
