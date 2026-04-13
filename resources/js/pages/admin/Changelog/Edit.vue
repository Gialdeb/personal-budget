<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import RichContentEditor from '@/components/admin/editorial/RichContentEditor.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as adminIndex } from '@/routes/admin';
import {
    edit as editChangelogRelease,
    index as changelogIndex,
    store as storeChangelogRelease,
    update as updateChangelogRelease,
} from '@/routes/admin/changelog/index';
import type {
    AdminChangelogEditPageProps,
    BreadcrumbItem,
    ChangelogLocaleOption,
} from '@/types';

type TranslationForm = {
    locale: string;
    title: string;
    summary: string;
    excerpt: string;
};

type SectionTranslationForm = {
    locale: string;
    label: string;
};

type ItemTranslationForm = {
    locale: string;
    title: string;
    body: string;
};

type ItemForm = {
    sort_order: number;
    screenshot_key: string | null;
    link_url: string | null;
    link_label: string | null;
    item_type: string | null;
    platform: string | null;
    translations: ItemTranslationForm[];
};

type SectionForm = {
    key: string;
    sort_order: number;
    translations: SectionTranslationForm[];
    items: ItemForm[];
};

const props = defineProps<AdminChangelogEditPageProps>();
const page = usePage();

const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            success?: string | null;
            error?: string | null;
        },
);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: adminIndex() },
    { title: 'Changelog', href: changelogIndex() },
    {
        title: props.release?.version_label ?? 'Nuova release',
        href: props.release
            ? editChangelogRelease({ changelogRelease: props.release.uuid })
            : changelogIndex(),
    },
];

function emptyReleaseTranslation(locale: string): TranslationForm {
    return {
        locale,
        title: '',
        summary: '',
        excerpt: '',
    };
}

function emptySectionTranslation(locale: string): SectionTranslationForm {
    return {
        locale,
        label: '',
    };
}

function emptyItemTranslation(locale: string): ItemTranslationForm {
    return {
        locale,
        title: '',
        body: '<p></p>',
    };
}

function emptyItem(locales: ChangelogLocaleOption[], sortOrder = 1): ItemForm {
    return {
        sort_order: sortOrder,
        screenshot_key: null,
        link_url: null,
        link_label: null,
        item_type: null,
        platform: null,
        translations: locales.map((locale) =>
            emptyItemTranslation(locale.code),
        ),
    };
}

function emptySection(
    locales: ChangelogLocaleOption[],
    sortOrder = 1,
): SectionForm {
    return {
        key: '',
        sort_order: sortOrder,
        translations: locales.map((locale) =>
            emptySectionTranslation(locale.code),
        ),
        items: [emptyItem(locales)],
    };
}

function buildFormState() {
    if (!props.release) {
        return {
            version_label: props.versionSuggestions.patch.beta,
            channel: 'beta',
            is_published: false,
            is_pinned: false,
            published_at: '',
            sort_order: '',
            translations: props.supportedLocales.map((locale) =>
                emptyReleaseTranslation(locale.code),
            ),
            sections: [emptySection(props.supportedLocales)],
        };
    }

    return {
        version_label: props.release.version_label,
        channel: props.release.channel,
        is_published: props.release.is_published,
        is_pinned: props.release.is_pinned,
        published_at: props.release.published_at ?? '',
        sort_order:
            props.release.sort_order === null
                ? ''
                : String(props.release.sort_order),
        translations: props.supportedLocales.map((locale) => {
            const translation = props.release?.translations.find(
                (item) => item.locale === locale.code,
            );

            return {
                locale: locale.code,
                title: translation?.title ?? '',
                summary: translation?.summary ?? '',
                excerpt: translation?.excerpt ?? '',
            };
        }),
        sections: props.release.sections.map((section) => ({
            key: section.key,
            sort_order: section.sort_order,
            translations: props.supportedLocales.map((locale) => {
                const translation = section.translations.find(
                    (item) => item.locale === locale.code,
                );

                return {
                    locale: locale.code,
                    label: translation?.label ?? '',
                };
            }),
            items: section.items.map((item) => ({
                sort_order: item.sort_order,
                screenshot_key: item.screenshot_key,
                link_url: item.link_url,
                link_label: item.link_label,
                item_type: item.item_type,
                platform: item.platform,
                translations: props.supportedLocales.map((locale) => {
                    const translation = item.translations.find(
                        (entry) => entry.locale === locale.code,
                    );

                    return {
                        locale: locale.code,
                        title: translation?.title ?? '',
                        body: translation?.body ?? '<p></p>',
                    };
                }),
            })),
        })),
    };
}

const form = useForm(buildFormState());
const currentLocale = ref(props.supportedLocales[0]?.code ?? 'it');

const feedback = computed(() => {
    if (flash.value.error) {
        return {
            variant: 'destructive' as const,
            title: 'Operazione non riuscita',
            message: flash.value.error,
        };
    }

    if (flash.value.success) {
        return {
            variant: 'default' as const,
            title: 'Release salvata',
            message: flash.value.success,
        };
    }

    return null;
});

function releaseTranslation(locale: string): TranslationForm {
    const translation = form.translations.find(
        (item) => item.locale === locale,
    );

    if (!translation) {
        throw new Error(`Missing release translation for ${locale}`);
    }

    return translation;
}

function sectionTranslation(
    sectionIndex: number,
    locale: string,
): SectionTranslationForm {
    const translation = form.sections[sectionIndex]?.translations.find(
        (item) => item.locale === locale,
    );

    if (!translation) {
        throw new Error(`Missing section translation for ${locale}`);
    }

    return translation;
}

function itemTranslation(
    sectionIndex: number,
    itemIndex: number,
    locale: string,
): ItemTranslationForm {
    const translation = form.sections[sectionIndex]?.items[
        itemIndex
    ]?.translations.find((item) => item.locale === locale);

    if (!translation) {
        throw new Error(`Missing item translation for ${locale}`);
    }

    return translation;
}

function addSection(): void {
    form.sections.push(
        emptySection(props.supportedLocales, form.sections.length + 1),
    );
}

function removeSection(sectionIndex: number): void {
    form.sections.splice(sectionIndex, 1);
}

function addItem(sectionIndex: number): void {
    form.sections[sectionIndex].items.push(
        emptyItem(
            props.supportedLocales,
            form.sections[sectionIndex].items.length + 1,
        ),
    );
}

function removeItem(sectionIndex: number, itemIndex: number): void {
    form.sections[sectionIndex].items.splice(itemIndex, 1);
}

function applySuggestion(versionLabel: string, channel: string): void {
    form.version_label = versionLabel;
    form.channel = channel;
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        sort_order:
            data.sort_order === '' || data.sort_order === null
                ? null
                : Number(data.sort_order),
    }));

    if (props.release) {
        form.put(
            updateChangelogRelease({ changelogRelease: props.release.uuid })
                .url,
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(storeChangelogRelease().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head
            :title="
                props.release ? props.release.version_label : 'Nuova release'
            "
        />

        <AdminLayout>
            <section class="space-y-6">
                <div
                    class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-8 shadow-[0_30px_90px_-50px_rgba(15,23,42,0.45)]"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="space-y-3">
                            <Badge
                                class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[11px] tracking-[0.2em] text-rose-700 uppercase"
                            >
                                Release editor
                            </Badge>
                            <Heading
                                variant="small"
                                :title="
                                    props.release
                                        ? `Modifica ${props.release.version_label}`
                                        : 'Nuova release changelog'
                                "
                                description="Versione, canale, traduzioni, sezioni e item vengono salvati lato admin. Il frontend pubblico leggerà solo questi dati."
                            />
                        </div>

                        <Button variant="outline" class="rounded-2xl" as-child>
                            <Link :href="changelogIndex().url">
                                Torna alla lista
                            </Link>
                        </Button>
                    </div>
                </div>

                <Alert v-if="feedback" :variant="feedback.variant">
                    <AlertTitle>{{ feedback.title }}</AlertTitle>
                    <AlertDescription>{{ feedback.message }}</AlertDescription>
                </Alert>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <div class="space-y-6">
                        <Card class="rounded-[1.5rem] border-slate-200/80">
                            <CardHeader>
                                <CardTitle class="text-base"
                                    >Versione e stato</CardTitle
                                >
                            </CardHeader>
                            <CardContent class="space-y-5">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="version_label"
                                            >Version label</Label
                                        >
                                        <Input
                                            id="version_label"
                                            v-model="form.version_label"
                                        />
                                        <InputError
                                            :message="form.errors.version_label"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="channel">Channel</Label>
                                        <select
                                            id="channel"
                                            v-model="form.channel"
                                            class="flex h-10 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm text-slate-900"
                                        >
                                            <option value="beta">beta</option>
                                            <option value="stable">
                                                stable
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="published_at"
                                            >Published at</Label
                                        >
                                        <Input
                                            id="published_at"
                                            v-model="form.published_at"
                                            type="datetime-local"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="sort_order"
                                            >Sort order</Label
                                        >
                                        <Input
                                            id="sort_order"
                                            v-model="form.sort_order"
                                            type="number"
                                        />
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-5">
                                    <label
                                        class="inline-flex items-center gap-3 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="form.is_published"
                                            type="checkbox"
                                        />
                                        Pubblicata
                                    </label>
                                    <label
                                        class="inline-flex items-center gap-3 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="form.is_pinned"
                                            type="checkbox"
                                        />
                                        Pinnata
                                    </label>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="rounded-[1.5rem] border-slate-200/80">
                            <CardHeader>
                                <CardTitle class="text-base"
                                    >Contenuti per lingua</CardTitle
                                >
                            </CardHeader>
                            <CardContent class="space-y-5">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="locale in props.supportedLocales"
                                        :key="locale.code"
                                        type="button"
                                        class="rounded-full border px-3 py-1.5 text-sm font-medium transition"
                                        :class="
                                            currentLocale === locale.code
                                                ? 'border-slate-900 bg-slate-900 text-white'
                                                : 'border-slate-200 bg-white text-slate-700'
                                        "
                                        @click="currentLocale = locale.code"
                                    >
                                        {{ locale.label }}
                                    </button>
                                </div>

                                <div class="space-y-4">
                                    <div class="space-y-2">
                                        <Label :for="`title-${currentLocale}`"
                                            >Titolo</Label
                                        >
                                        <Input
                                            :id="`title-${currentLocale}`"
                                            v-model="
                                                releaseTranslation(
                                                    currentLocale,
                                                ).title
                                            "
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label>Summary</Label>
                                        <RichContentEditor
                                            :key="`changelog-summary-${currentLocale}`"
                                            v-model="
                                                releaseTranslation(
                                                    currentLocale,
                                                ).summary
                                            "
                                            placeholder="Introduzione breve della release"
                                            upload-label="Immagine"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label :for="`excerpt-${currentLocale}`"
                                            >Excerpt</Label
                                        >
                                        <Input
                                            :id="`excerpt-${currentLocale}`"
                                            v-model="
                                                releaseTranslation(
                                                    currentLocale,
                                                ).excerpt
                                            "
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="rounded-[1.5rem] border-slate-200/80">
                            <CardHeader
                                class="flex flex-row items-center justify-between"
                            >
                                <CardTitle class="text-base"
                                    >Sezioni e item</CardTitle
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="rounded-2xl"
                                    @click="addSection"
                                >
                                    Aggiungi sezione
                                </Button>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <div
                                    v-for="(
                                        section, sectionIndex
                                    ) in form.sections"
                                    :key="sectionIndex"
                                    class="rounded-[1.5rem] border border-slate-200 bg-slate-50/60 p-4"
                                >
                                    <div
                                        class="flex flex-col gap-4 lg:flex-row"
                                    >
                                        <div
                                            class="grid flex-1 gap-4 md:grid-cols-2"
                                        >
                                            <div class="space-y-2">
                                                <Label>Key</Label>
                                                <Input
                                                    v-model="section.key"
                                                    placeholder="new / improved / fixed"
                                                />
                                            </div>
                                            <div class="space-y-2">
                                                <Label>Sort order</Label>
                                                <Input
                                                    v-model="section.sort_order"
                                                    type="number"
                                                />
                                            </div>
                                            <div
                                                class="space-y-2 md:col-span-2"
                                            >
                                                <Label
                                                    >Label ({{
                                                        currentLocale
                                                    }})</Label
                                                >
                                                <Input
                                                    v-model="
                                                        sectionTranslation(
                                                            sectionIndex,
                                                            currentLocale,
                                                        ).label
                                                    "
                                                />
                                            </div>
                                        </div>

                                        <div class="flex items-start">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="rounded-2xl"
                                                @click="
                                                    removeSection(sectionIndex)
                                                "
                                            >
                                                Rimuovi sezione
                                            </Button>
                                        </div>
                                    </div>

                                    <div class="mt-5 space-y-4">
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <p
                                                class="text-sm font-semibold text-slate-900"
                                            >
                                                Item
                                            </p>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                class="rounded-2xl"
                                                @click="addItem(sectionIndex)"
                                            >
                                                Aggiungi item
                                            </Button>
                                        </div>

                                        <div
                                            v-for="(
                                                item, itemIndex
                                            ) in section.items"
                                            :key="itemIndex"
                                            class="rounded-2xl border border-slate-200 bg-white p-4"
                                        >
                                            <div
                                                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                                            >
                                                <div class="space-y-2">
                                                    <Label>Sort order</Label>
                                                    <Input
                                                        v-model="
                                                            item.sort_order
                                                        "
                                                        type="number"
                                                    />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label
                                                        >Screenshot key</Label
                                                    >
                                                    <Input
                                                        :model-value="
                                                            item.screenshot_key ??
                                                            ''
                                                        "
                                                        @update:model-value="
                                                            item.screenshot_key =
                                                                String(
                                                                    $event,
                                                                ) || null
                                                        "
                                                    />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label>Item type</Label>
                                                    <Input
                                                        :model-value="
                                                            item.item_type ?? ''
                                                        "
                                                        @update:model-value="
                                                            item.item_type =
                                                                String(
                                                                    $event,
                                                                ) || null
                                                        "
                                                    />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label>Platform</Label>
                                                    <Input
                                                        :model-value="
                                                            item.platform ?? ''
                                                        "
                                                        @update:model-value="
                                                            item.platform =
                                                                String(
                                                                    $event,
                                                                ) || null
                                                        "
                                                    />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label>Link URL</Label>
                                                    <Input
                                                        :model-value="
                                                            item.link_url ?? ''
                                                        "
                                                        @update:model-value="
                                                            item.link_url =
                                                                String(
                                                                    $event,
                                                                ) || null
                                                        "
                                                    />
                                                </div>
                                                <div class="space-y-2">
                                                    <Label>Link label</Label>
                                                    <Input
                                                        :model-value="
                                                            item.link_label ??
                                                            ''
                                                        "
                                                        @update:model-value="
                                                            item.link_label =
                                                                String(
                                                                    $event,
                                                                ) || null
                                                        "
                                                    />
                                                </div>
                                            </div>

                                            <div class="mt-4 space-y-4">
                                                <div class="space-y-2">
                                                    <Label
                                                        >Titolo item ({{
                                                            currentLocale
                                                        }})</Label
                                                    >
                                                    <Input
                                                        v-model="
                                                            itemTranslation(
                                                                sectionIndex,
                                                                itemIndex,
                                                                currentLocale,
                                                            ).title
                                                        "
                                                    />
                                                </div>

                                                <div class="space-y-2">
                                                    <Label
                                                        >Body ({{
                                                            currentLocale
                                                        }})</Label
                                                    >
                                                    <RichContentEditor
                                                        :key="
                                                            `changelog-item-body-${sectionIndex}-${itemIndex}-${currentLocale}`
                                                        "
                                                        v-model="
                                                            itemTranslation(
                                                                sectionIndex,
                                                                itemIndex,
                                                                currentLocale,
                                                            ).body
                                                        "
                                                        placeholder="Dettaglio breve, paragrafi, elenchi e link."
                                                        upload-label="Immagine"
                                                    />
                                                </div>
                                            </div>

                                            <div class="mt-4 flex justify-end">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    class="rounded-2xl"
                                                    @click="
                                                        removeItem(
                                                            sectionIndex,
                                                            itemIndex,
                                                        )
                                                    "
                                                >
                                                    Rimuovi item
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div class="flex justify-end">
                            <Button
                                class="h-11 rounded-2xl px-6"
                                :disabled="form.processing"
                                @click="submit"
                            >
                                {{
                                    form.processing
                                        ? 'Salvataggio...'
                                        : 'Salva release'
                                }}
                            </Button>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <Card class="rounded-[1.5rem] border-slate-200/80">
                            <CardHeader>
                                <CardTitle class="text-base"
                                    >Suggerimenti versione</CardTitle
                                >
                            </CardHeader>
                            <CardContent class="space-y-3 text-sm">
                                <div
                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-slate-600"
                                >
                                    Ultima release:
                                    <span
                                        class="font-semibold text-slate-950"
                                        >{{
                                            props.latestRelease ?? 'nessuna'
                                        }}</span
                                    >
                                </div>
                                <div class="grid gap-2">
                                    <button
                                        type="button"
                                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-slate-300"
                                        @click="
                                            applySuggestion(
                                                props.versionSuggestions.patch
                                                    .beta,
                                                'beta',
                                            )
                                        "
                                    >
                                        Patch beta ·
                                        {{
                                            props.versionSuggestions.patch.beta
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-slate-300"
                                        @click="
                                            applySuggestion(
                                                props.versionSuggestions.patch
                                                    .stable,
                                                'stable',
                                            )
                                        "
                                    >
                                        Patch stable ·
                                        {{
                                            props.versionSuggestions.patch
                                                .stable
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-slate-300"
                                        @click="
                                            applySuggestion(
                                                props.versionSuggestions.minor
                                                    .beta,
                                                'beta',
                                            )
                                        "
                                    >
                                        Minor beta ·
                                        {{
                                            props.versionSuggestions.minor.beta
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-slate-300"
                                        @click="
                                            applySuggestion(
                                                props.versionSuggestions.major
                                                    .stable,
                                                'stable',
                                            )
                                        "
                                    >
                                        Major stable ·
                                        {{
                                            props.versionSuggestions.major
                                                .stable
                                        }}
                                    </button>
                                </div>
                            </CardContent>
                        </Card>

                        <Card class="rounded-[1.5rem] border-slate-200/80">
                            <CardHeader>
                                <CardTitle class="text-base"
                                    >Controlli editor</CardTitle
                                >
                            </CardHeader>
                            <CardContent
                                class="space-y-3 text-sm text-slate-600"
                            >
                                <p>
                                    Usa il canale per distinguere beta e stable.
                                </p>
                                <p>
                                    Le lingue mancanti restano evidenti perché
                                    il form mostra sempre tutte le locale
                                    supportate.
                                </p>
                                <p>
                                    Sezioni e item sono ordinati tramite
                                    `sort_order`, non per posizione casuale del
                                    frontend.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </section>
        </AdminLayout>
    </AppLayout>
</template>
