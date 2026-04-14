<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import MobileSearchableSelect from '@/components/MobileSearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EntrySearchFilterOption, EntrySearchState } from '@/types';

const props = defineProps<{
    open: boolean;
    modelValue: EntrySearchState;
    accountOptions: EntrySearchFilterOption[];
    categoryOptions: EntrySearchFilterOption[];
    showRecurringStatus: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: EntrySearchState];
    reset: [];
    apply: [];
    close: [];
}>();

const { t } = useI18n();

const directionOptions = computed(() => [
    { value: '__all__', label: t('entrySearch.advanced.allDirections') },
    { value: 'income', label: t('transactions.recurring.filters.incomes') },
    { value: 'expense', label: t('transactions.recurring.filters.expenses') },
]);

const recurringStatusOptions = computed(() => [
    { value: '__all__', label: t('entrySearch.advanced.allRecurringStatuses') },
    {
        value: 'active',
        label: t('entrySearch.advanced.recurringStatuses.active'),
    },
    {
        value: 'paused',
        label: t('entrySearch.advanced.recurringStatuses.paused'),
    },
]);

function update(patch: Partial<EntrySearchState>): void {
    emit('update:modelValue', {
        ...props.modelValue,
        ...patch,
    });
}

const categorySelectOptions = computed(() =>
    props.categoryOptions.map((option) => ({
        value: option.value,
        label: option.label,
        full_path: option.full_path ?? option.label,
        icon: option.icon ?? null,
        color: option.color ?? null,
        ancestor_uuids: option.ancestor_uuids ?? [],
        is_selectable: option.is_selectable ?? true,
    })),
);
</script>

<template>
    <div
        v-if="open"
        class="rounded-[28px] border border-slate-200/80 bg-white/88 p-4 shadow-[0_28px_90px_-48px_rgba(15,23,42,0.38)] backdrop-blur md:p-5 dark:border-white/10 dark:bg-slate-950/76"
    >
        <div
            class="flex flex-col gap-3 border-b border-slate-200/70 pb-4 md:flex-row md:items-start md:justify-between dark:border-white/10"
        >
            <div>
                <h3
                    class="text-base font-semibold text-slate-950 dark:text-slate-50"
                >
                    {{ t('entrySearch.advanced.title') }}
                </h3>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                >
                    {{ t('entrySearch.advanced.description') }}
                </p>
            </div>

            <Button
                variant="ghost"
                class="self-start rounded-full px-3 text-sm"
                @click="emit('close')"
            >
                {{ t('entrySearch.actions.closeFilters') }}
            </Button>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label>{{ t('entrySearch.advanced.account') }}</Label>
                    <Select
                        :model-value="modelValue.accountUuid ?? '__all__'"
                        @update:model-value="
                            update({
                                accountUuid:
                                    String($event) === '__all__'
                                        ? null
                                        : String($event),
                            })
                        "
                    >
                        <SelectTrigger class="h-11 rounded-2xl">
                            <SelectValue
                                :placeholder="t('entrySearch.advanced.account')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">
                                {{ t('entrySearch.advanced.allAccounts') }}
                            </SelectItem>
                            <SelectItem
                                v-for="option in accountOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label>{{ t('entrySearch.advanced.category') }}</Label>
                    <MobileSearchableSelect
                        :model-value="modelValue.categoryUuid ?? ''"
                        :options="categorySelectOptions"
                        :placeholder="t('entrySearch.advanced.category')"
                        :search-placeholder="
                            t('entrySearch.advanced.searchCategories')
                        "
                        :empty-label="
                            t('entrySearch.advanced.noCategoriesFound')
                        "
                        :clear-value="''"
                        clearable
                        hierarchical
                        :teleport="false"
                        :mobile-title="t('entrySearch.advanced.category')"
                        :mobile-description="
                            t('entrySearch.advanced.categoryDescription')
                        "
                        trigger-class="h-11 rounded-2xl"
                        @update:model-value="
                            update({
                                categoryUuid:
                                    String($event || '') === ''
                                        ? null
                                        : String($event),
                            })
                        "
                    />
                </div>
            </div>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label>{{ t('entrySearch.advanced.direction') }}</Label>
                    <Select
                        :model-value="modelValue.direction ?? '__all__'"
                        @update:model-value="
                            update({
                                direction:
                                    String($event) === '__all__'
                                        ? null
                                        : String($event),
                            })
                        "
                    >
                        <SelectTrigger class="h-11 rounded-2xl">
                            <SelectValue
                                :placeholder="
                                    t('entrySearch.advanced.direction')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in directionOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div v-if="showRecurringStatus" class="grid gap-2">
                    <Label>{{
                        t('entrySearch.advanced.recurringStatus')
                    }}</Label>
                    <Select
                        :model-value="modelValue.recurringStatus ?? '__all__'"
                        @update:model-value="
                            update({
                                recurringStatus:
                                    String($event) === '__all__'
                                        ? null
                                        : String($event),
                            })
                        "
                    >
                        <SelectTrigger class="h-11 rounded-2xl">
                            <SelectValue
                                :placeholder="
                                    t('entrySearch.advanced.recurringStatus')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in recurringStatusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="grid gap-4 lg:col-span-2 lg:grid-cols-2">
                <div class="grid gap-2">
                    <Label>{{ t('entrySearch.advanced.amountMin') }}</Label>
                    <Input
                        :model-value="modelValue.amountMin"
                        type="number"
                        inputmode="decimal"
                        class="h-11 rounded-2xl"
                        @update:model-value="
                            update({
                                amountMin: String($event ?? ''),
                            })
                        "
                    />
                </div>

                <div class="grid gap-2">
                    <Label>{{ t('entrySearch.advanced.amountMax') }}</Label>
                    <Input
                        :model-value="modelValue.amountMax"
                        type="number"
                        inputmode="decimal"
                        class="h-11 rounded-2xl"
                        @update:model-value="
                            update({
                                amountMax: String($event ?? ''),
                            })
                        "
                    />
                </div>
            </div>

            <div class="grid gap-3 lg:col-span-2">
                <label
                    class="flex items-center gap-3 rounded-2xl border border-slate-200/80 px-4 py-3 dark:border-white/10"
                >
                    <Checkbox
                        :checked="modelValue.withNotes"
                        @update:checked="update({ withNotes: Boolean($event) })"
                    />
                    <span class="text-sm text-slate-700 dark:text-slate-200">
                        {{ t('entrySearch.advanced.withNotes') }}
                    </span>
                </label>

                <label
                    class="flex items-center gap-3 rounded-2xl border border-slate-200/80 px-4 py-3 dark:border-white/10"
                >
                    <Checkbox
                        :checked="modelValue.withReference"
                        @update:checked="
                            update({ withReference: Boolean($event) })
                        "
                    />
                    <span class="text-sm text-slate-700 dark:text-slate-200">
                        {{ t('entrySearch.advanced.withReference') }}
                    </span>
                </label>
            </div>

            <div
                class="flex flex-col-reverse gap-2 border-t border-slate-200/70 pt-4 sm:flex-row sm:items-center sm:justify-between lg:col-span-2 dark:border-white/10"
            >
                <Button
                    variant="ghost"
                    class="rounded-full"
                    @click="emit('reset')"
                >
                    {{ t('entrySearch.actions.reset') }}
                </Button>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button
                        variant="outline"
                        class="rounded-full"
                        @click="emit('close')"
                    >
                        {{ t('entrySearch.actions.cancel') }}
                    </Button>
                    <Button class="rounded-full" @click="emit('apply')">
                        {{ t('entrySearch.actions.applyFilters') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
