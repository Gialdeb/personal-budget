<script setup lang="ts">
import { computed } from 'vue';
import RichContentEditor from '@/components/admin/editorial/RichContentEditor.vue';
import KnowledgePublishPanel from '@/components/admin/knowledge/KnowledgePublishPanel.vue';
import KnowledgeTranslationTabs from '@/components/admin/knowledge/KnowledgeTranslationTabs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    AdminKnowledgeArticleFormState,
    AdminKnowledgeArticleSectionSummary,
    LocaleOption,
} from '@/types';

type ArticleFormLike = AdminKnowledgeArticleFormState & {
    errors: Record<string, string | undefined>;
};

const props = defineProps<{
    sections: AdminKnowledgeArticleSectionSummary[];
    supportedLocales: LocaleOption[];
    currentLocale: string;
    submitLabel: string;
    processing: boolean;
}>();
const form = defineModel<ArticleFormLike>('form', { required: true });

const emit = defineEmits<{
    submit: [];
    'update:currentLocale': [locale: string];
}>();

function translation(locale: string) {
    const item = form.value.translations.find((entry) => entry.locale === locale);

    if (!item) {
        throw new Error(`Missing article translation for ${locale}`);
    }

    return item;
}

function translationIndex(locale: string): number {
    return form.value.translations.findIndex((entry) => entry.locale === locale);
}

function fieldError(field: string): string | undefined {
    return form.value.errors[field];
}

const completion = computed(() =>
    Object.fromEntries(
        props.supportedLocales.map((locale) => {
            const item = translation(locale.code);

            return [
                locale.code,
                item.title.trim().length > 0 &&
                    item.body.replace(/<[^>]+>/g, '').trim().length > 0,
            ];
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
                        <CardTitle class="text-base">Metadati articolo</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-5 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="knowledge-article-section">Sezione</Label>
                            <select
                                id="knowledge-article-section"
                                v-model="form.section_id"
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                            >
                                <option :value="''">Seleziona una sezione</option>
                                <option
                                    v-for="section in sections"
                                    :key="section.id"
                                    :value="section.id"
                                >
                                    {{ section.title ?? section.slug }}
                                    {{ section.is_published ? '' : ' (bozza)' }}
                                </option>
                            </select>
                            <InputError :message="fieldError('section_id')" />
                        </div>

                        <div class="space-y-2">
                            <Label for="knowledge-article-slug">Slug</Label>
                            <Input
                                id="knowledge-article-slug"
                                v-model="form.slug"
                                autocomplete="off"
                                placeholder="connect-bank-account"
                            />
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
                            <Label :for="`article-title-${currentLocale}`">
                                Titolo {{ currentLocale.toUpperCase() }}
                            </Label>
                            <Input
                                :id="`article-title-${currentLocale}`"
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
                            <Label :for="`article-excerpt-${currentLocale}`">
                                Excerpt {{ currentLocale.toUpperCase() }}
                            </Label>
                            <textarea
                                :id="`article-excerpt-${currentLocale}`"
                                v-model="translation(currentLocale).excerpt"
                                rows="4"
                                class="min-h-24 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-800 outline-none transition focus:border-slate-400 focus:ring-0"
                            />
                            <InputError
                                :message="
                                    fieldError(
                                        `translations.${translationIndex(currentLocale)}.excerpt`,
                                    )
                                "
                            />
                        </div>

                        <div class="space-y-2">
                            <Label>
                                Body {{ currentLocale.toUpperCase() }}
                            </Label>
                            <RichContentEditor
                                :key="`knowledge-article-body-${currentLocale}`"
                                v-model="translation(currentLocale).body"
                                placeholder="Scrivi il contenuto dell'articolo"
                                upload-label="Immagine"
                            />
                            <InputError
                                :message="
                                    fieldError(
                                        `translations.${translationIndex(currentLocale)}.body`,
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
                    :published-at="form.published_at"
                    :show-published-at="true"
                    @update:is-published="form.is_published = $event"
                    @update:sort-order="form.sort_order = $event"
                    @update:published-at="form.published_at = $event"
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
