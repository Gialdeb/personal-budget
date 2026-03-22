<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { normalizeLocale } from '@/i18n';
import { update as updateLocale } from '@/routes/settings/locale';
import type { LocaleOption, LocaleSharedData } from '@/types';

const page = usePage();
const { locale, t } = useI18n();
const isSaving = ref(false);

const localeData = computed(
    () => (page.props.locale ?? {}) as Partial<LocaleSharedData>,
);

const availableLocales = computed<LocaleOption[]>(() => {
    return localeData.value.available ?? [];
});

const selectedLocale = computed(() => normalizeLocale(locale.value));

function getLocaleLabel(option: LocaleOption): string {
    return t(`app.language.options.${option.code}`, option.label);
}

function getLocaleFlag(code: string): string {
    if (code === 'it') {
        return '🇮🇹';
    }

    if (code === 'en') {
        return '🇬🇧';
    }

    return '🌐';
}

function changeLocale(nextLocale: unknown): void {
    if (typeof nextLocale !== 'string') {
        return;
    }

    if (
        isSaving.value ||
        nextLocale === selectedLocale.value ||
        !availableLocales.value.some((option) => option.code === nextLocale)
    ) {
        return;
    }

    const previousLocale = selectedLocale.value;

    locale.value = nextLocale;
    isSaving.value = true;

    router.patch(
        updateLocale.url(),
        { locale: nextLocale },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                locale.value = previousLocale;
                router.reload({
                    only: ['locale'],
                });
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
}
</script>

<template>
    <DropdownMenuSeparator />
    <DropdownMenuLabel
        class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
    >
        {{ t('app.language.label') }}
    </DropdownMenuLabel>
    <DropdownMenuRadioGroup
        :model-value="selectedLocale"
        @update:model-value="changeLocale"
    >
        <DropdownMenuRadioItem
            v-for="option in availableLocales"
            :key="option.code"
            :value="option.code"
            :disabled="isSaving"
        >
            <span class="mr-2 text-sm leading-none">
                {{ getLocaleFlag(option.code) }}
            </span>
            {{ getLocaleLabel(option) }}
        </DropdownMenuRadioItem>
    </DropdownMenuRadioGroup>
    <DropdownMenuLabel
        v-if="isSaving"
        class="px-2 py-1.5 text-xs font-normal text-muted-foreground"
    >
        {{ t('app.userMenu.languageSaving') }}
    </DropdownMenuLabel>
</template>
