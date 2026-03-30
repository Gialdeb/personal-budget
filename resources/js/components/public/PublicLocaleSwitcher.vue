<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Globe, ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
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

function changeLocale(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    const nextLocale = target?.value;

    if (
        !nextLocale ||
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
    <label class="relative inline-flex items-center">
        <span class="sr-only">{{ t('auth.welcome.footer.language') }}</span>
        <Globe
            class="pointer-events-none absolute left-3 size-4 text-slate-400"
        />
        <select
            :value="selectedLocale"
            class="appearance-none rounded-2xl border border-[#e8ddd6] bg-white py-3 pr-10 pl-9 text-sm font-medium text-slate-700 shadow-sm transition outline-none focus:border-[#d9c4b8]"
            :disabled="isSaving"
            @change="changeLocale"
        >
            <option
                v-for="option in availableLocales"
                :key="option.code"
                :value="option.code"
            >
                {{ getLocaleLabel(option) }}
            </option>
        </select>
        <ChevronDown
            class="pointer-events-none absolute right-3 size-4 text-slate-400"
        />
    </label>
</template>
