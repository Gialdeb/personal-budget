<script setup lang="ts">
import { computed } from 'vue';
import KnowledgePublishPanel from '@/components/admin/knowledge/KnowledgePublishPanel.vue';
import KnowledgeTranslationTabs from '@/components/admin/knowledge/KnowledgeTranslationTabs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AdminKnowledgeSectionFormState, LocaleOption } from '@/types';

type SectionFormLike = AdminKnowledgeSectionFormState & {
    errors: Record<string, string | undefined>;
};

const props = defineProps<{
    supportedLocales: LocaleOption[];
    currentLocale: string;
    submitLabel: string;
    processing: boolean;
}>();
const form = defineModel<SectionFormLike>('form', { required: true });

const emit = defineEmits<{
    submit: [];
    'update:currentLocale': [locale: string];
}>();

function translation(locale: string) {
    const item = form.value.translations.find(
        (entry) => entry.locale === locale,
    );

    if (!item) {
        throw new Error(`Missing section translation for ${locale}`);
    }

    return item;
}

function translationIndex(locale: string): number {
    return form.value.translations.findIndex(
        (entry) => entry.locale === locale,
    );
}

function fieldError(field: string): string | undefined {
    return form.value.errors[field];
}

const completion = computed(() =>
    Object.fromEntries(
        props.supportedLocales.map((locale) => {
            const item = translation(locale.code);

            return [locale.code, item.title.trim().length > 0];
        }),
    ),
);
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <Card class="rounded-[1.5rem] border-slate-200/80">
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Identità sezione</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="space-y-2">
                            <Label for="knowledge-section-slug">Slug</Label>
                            <Input
                                id="knowledge-section-slug"
                                v-model="form.slug"
                                autocomplete="off"
                                placeholder="getting-started"
                            />
                            <p class="text-xs leading-5 text-slate-500">
                                Modifica lo slug solo se vuoi cambiare
                                esplicitamente l’URL pubblico della sezione.
                            </p>
                            <InputError :message="fieldError('slug')" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-[1.5rem] border-slate-200/80">
                    <CardHeader class="space-y-4">
                        <CardTitle class="text-base">Traduzioni</CardTitle>
                        <KnowledgeTranslationTabs
                            :locales="supportedLocales"
                            :current-locale="currentLocale"
                            :completion="completion"
                            @update:current-locale="
                                emit('update:currentLocale', $event)
                            "
                        />
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="space-y-2">
                            <Label :for="`section-title-${currentLocale}`">
                                Titolo {{ currentLocale.toUpperCase() }}
                            </Label>
                            <Input
                                :id="`section-title-${currentLocale}`"
                                v-model="translation(currentLocale).title"
                                autocomplete="off"
                            />
                            <InputError
                                :message="
                                    fieldError(
                                        `translations.${translationIndex(currentLocale)}.title`,
                                    )
                                "
                            />
                        </div>

                        <div class="space-y-2">
                            <Label
                                :for="`section-description-${currentLocale}`"
                            >
                                Descrizione {{ currentLocale.toUpperCase() }}
                            </Label>
                            <textarea
                                :id="`section-description-${currentLocale}`"
                                v-model="translation(currentLocale).description"
                                rows="5"
                                class="min-h-28 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-800 transition outline-none focus:border-slate-400 focus:ring-0"
                            />
                            <InputError
                                :message="
                                    fieldError(
                                        `translations.${translationIndex(currentLocale)}.description`,
                                    )
                                "
                            />
                        </div>

                        <InputError :message="fieldError('translations')" />
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <KnowledgePublishPanel
                    :is-published="form.is_published"
                    :sort-order="form.sort_order"
                    @update:is-published="form.is_published = $event"
                    @update:sort-order="form.sort_order = $event"
                    @update:published-at="() => {}"
                />

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        type="submit"
                        class="h-11 rounded-2xl px-5"
                        :disabled="processing"
                    >
                        {{ processing ? 'Salvataggio...' : submitLabel }}
                    </Button>
                    <slot name="footerActions" />
                </div>
            </div>
        </div>
    </form>
</template>
