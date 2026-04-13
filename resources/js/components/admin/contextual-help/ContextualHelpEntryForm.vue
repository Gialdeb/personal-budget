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
    AdminContextualHelpFormState,
    AdminContextualHelpKnowledgeArticleOption,
    AdminContextualHelpPageKeyOption,
    LocaleOption,
} from '@/types';

type ContextualHelpFormLike = AdminContextualHelpFormState & {
    errors: Record<string, string | undefined>;
};

const props = defineProps<{
    pageKeyOptions: AdminContextualHelpPageKeyOption[];
    knowledgeArticles: AdminContextualHelpKnowledgeArticleOption[];
    supportedLocales: LocaleOption[];
    currentLocale: string;
    submitLabel: string;
    processing: boolean;
}>();

const form = defineModel<ContextualHelpFormLike>('form', { required: true });

const emit = defineEmits<{
    submit: [];
    'update:currentLocale': [locale: string];
}>();

function translation(locale: string) {
    const item = form.value.translations.find((entry) => entry.locale === locale);

    if (!item) {
        throw new Error(`Missing contextual help translation for ${locale}`);
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

const selectedPageKey = computed(() =>
    props.pageKeyOptions.find((option) => option.key === form.value.page_key) ?? null,
);
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <Card class="rounded-[1.5rem] border-slate-200/80">
                    <CardHeader>
                        <CardTitle class="text-base">Mappatura pagina</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-5">
                        <div class="space-y-2">
                            <Label for="contextual-help-page-key">Page key</Label>
                            <select
                                id="contextual-help-page-key"
                                v-model="form.page_key"
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                            >
                                <option value="">Seleziona una pagina supportata</option>
                                <option
                                    v-for="option in pageKeyOptions"
                                    :key="option.key"
                                    :value="option.key"
                                >
                                    {{ option.label }} ({{ option.key }})
                                </option>
                            </select>
                            <p
                                v-if="selectedPageKey"
                                class="text-sm leading-6 text-slate-600"
                            >
                                {{ selectedPageKey.description }}
                            </p>
                            <p class="text-xs leading-5 text-slate-500">
                                Usa sempre una <strong>page key stabile</strong>,
                                non un URL raw. Le chiavi disponibili sono:
                                {{ pageKeyOptions.map((option) => option.key).join(', ') }}.
                            </p>
                            <p
                                v-if="selectedPageKey"
                                class="text-xs leading-5 text-slate-500"
                            >
                                Route coperte: {{ selectedPageKey.route_names.join(', ') }}
                            </p>
                            <InputError :message="fieldError('page_key')" />
                        </div>

                        <div class="space-y-2">
                            <Label for="contextual-help-knowledge-article">Articolo Help Center collegato</Label>
                            <select
                                id="contextual-help-knowledge-article"
                                v-model="form.knowledge_article_id"
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                            >
                                <option :value="''">Nessun articolo collegato</option>
                                <option
                                    v-for="article in knowledgeArticles"
                                    :key="article.id"
                                    :value="article.id"
                                >
                                    {{ article.title ?? article.slug }}
                                    {{ article.is_published ? '' : ' (bozza)' }}
                                </option>
                            </select>
                            <p class="text-xs leading-5 text-slate-500">
                                Se presente, l’utente vedrà un link di approfondimento verso la guida completa.
                            </p>
                            <InputError :message="fieldError('knowledge_article_id')" />
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
                            @update:current-locale="emit('update:currentLocale', $event)"
                        />
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="space-y-2">
                            <Label :for="`contextual-help-title-${currentLocale}`">
                                Titolo {{ currentLocale.toUpperCase() }}
                            </Label>
                            <Input
                                :id="`contextual-help-title-${currentLocale}`"
                                v-model="translation(currentLocale).title"
                                autocomplete="off"
                            />
                            <InputError
                                :message="fieldError(`translations.${translationIndex(currentLocale)}.title`)"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label>Body {{ currentLocale.toUpperCase() }}</Label>
                            <RichContentEditor
                                :key="`contextual-help-body-${currentLocale}`"
                                v-model="translation(currentLocale).body"
                                placeholder="Scrivi una guida breve e contestuale per questa pagina"
                                upload-label="Immagine"
                            />
                            <InputError
                                :message="fieldError(`translations.${translationIndex(currentLocale)}.body`)"
                            />
                        </div>

                        <InputError :message="fieldError('translations')" />
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <KnowledgePublishPanel
                    title="Pubblicazione"
                    description="Se disattivi la pubblicazione, il pulsante contestuale non compare nella pagina collegata."
                    :is-published="form.is_published"
                    :sort-order="form.sort_order"
                    @update:is-published="form.is_published = $event"
                    @update:sort-order="form.sort_order = $event"
                />

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        type="submit"
                        class="h-11 rounded-2xl px-5"
                        :disabled="processing"
                    >
                        {{ processing ? 'Salvataggio...' : submitLabel }}
                    </Button>
                </div>
            </div>
        </div>
    </form>
</template>
