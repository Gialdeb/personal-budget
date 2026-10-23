<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, Tag } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import {
    index,
    store,
    update,
    destroy,
    importCandidates,
} from '@/actions/App/Http/Controllers/Admin/BrandController';
import CategoryManager from '@/components/admin/BrandCategoryManager.vue';
import BrandFormPreview from '@/components/admin/BrandFormPreview.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUiCopy } from '@/i18n/ui-copy';
import AdminLayout from '@/layouts/admin/Layout.vue';
import AppLayout from '@/layouts/AppLayout.vue';

type ImportCandidate = { uuid: string; domain: string; logo_url: string };
type Brand = {
    uuid: string;
    name: string;
    aliases: string[];
    domain: string;
    alternative_domains?: string[];
    category: string | null;
    logo_url: string | null;
    is_published: boolean;
};
const props = defineProps<{
    brands: {
        data: Brand[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    categories: string[];
    category_management: { uuid: string; name: string; brands_count: number }[];
    filters: {
        search: string;
        letter: string;
        sort: string;
        category: string;
        without_category: boolean;
    };
}>();
const ui = useUiCopy();
const search = ref(props.filters.search),
    letter = ref(props.filters.letter),
    sort = ref(props.filters.sort),
    categoryFilter = ref(
        props.filters.without_category
            ? '__none__'
            : (props.filters.category ?? ''),
    );
let timer: ReturnType<typeof setTimeout> | undefined;
watch([search, letter, sort, categoryFilter], () => {
    clearTimeout(timer);
    timer = setTimeout(
        () =>
            router.get(
                index.url(),
                {
                    search: search.value,
                    letter: letter.value,
                    sort: sort.value,
                    category:
                        categoryFilter.value === '__none__'
                            ? ''
                            : categoryFilter.value,
                    without_category:
                        categoryFilter.value === '__none__' ? 1 : 0,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            ),
        250,
    );
});
onBeforeUnmount(() => {
    clearTimeout(timer);

    if (uploadPreview.value) {
        URL.revokeObjectURL(uploadPreview.value);
    }
});
const open = ref(false),
    editing = ref<Brand | null>(null),
    deleting = ref<Brand | null>(null),
    aliasText = ref(''),
    mode = ref('website'),
    uploadPreview = ref('');
const form = useForm({
    name: '',
    aliases: [] as string[],
    domain: '',
    alternative_domains: [] as string[],
    category: '',
    is_published: true,
    logo: null as File | null,
    fetch_logo: false,
    remove_logo: false,
    source_logo_uuid: null as string | null,
});
const importOpen = ref(false);
const importSource = ref<ImportCandidate | null>(null);
const candidateSearch = ref('');
const candidates = ref<ImportCandidate[]>([]);
const candidatesPage = ref(1);
const candidatesHasMore = ref(false);
const candidatesLoading = ref(false);
const candidatesError = ref('');
let candidateRequest = 0;
let candidateTimer: ReturnType<typeof setTimeout> | undefined;
watch(candidateSearch, () => {
    clearTimeout(candidateTimer);
    candidateTimer = setTimeout(() => {
        if (importOpen.value) {
            loadCandidates(1);
        }
    }, 250);
});
onBeforeUnmount(() => {
    clearTimeout(candidateTimer);
    candidateRequest++;
});
async function loadCandidates(page = 1): Promise<void> {
    const request = ++candidateRequest;
    candidatesLoading.value = true;
    candidatesError.value = '';

    try {
        const response = await fetch(
            importCandidates.url({
                query: { search: candidateSearch.value, page },
            }),
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            if (request === candidateRequest) {
                candidatesError.value = ui(
                    'Impossibile caricare i loghi. Riprova.',
                );
            }

            return;
        }

        const result = await response.json();

        if (request !== candidateRequest) {
            return;
        }

        candidates.value = result.data;
        candidatesHasMore.value = result.has_more;
        candidatesPage.value = result.page;
    } catch {
        if (request === candidateRequest) {
            candidatesError.value = ui(
                'Impossibile caricare i loghi. Riprova.',
            );
        }
    } finally {
        if (request === candidateRequest) {
            candidatesLoading.value = false;
        }
    }
}
function openImports(): void {
    importOpen.value = true;
    candidateSearch.value = '';
    candidates.value = [];
    loadCandidates();
}
function chooseImport(candidate: ImportCandidate): void {
    importOpen.value = false;
    nextTick(() => {
        edit(null);
        importSource.value = candidate;
        form.source_logo_uuid = candidate.uuid;
        form.name = candidate.domain;
        form.domain = candidate.domain;
        mode.value = 'keep';
    });
}
const selectedBrands = ref<string[]>([]);
const categoryOptions = computed(() => props.categories);
watch(
    () => props.brands.data,
    () => {
        selectedBrands.value = [];
    },
);
function edit(brand: Brand | null): void {
    importSource.value = null;
    editing.value = brand;
    form.reset();
    form.clearErrors();
    form.name = brand?.name ?? '';
    form.domain = brand?.domain ?? '';
    form.alternative_domains = [...(brand?.alternative_domains ?? [])];
    form.category = brand?.category ?? '';
    form.is_published = brand?.is_published ?? true;
    aliasText.value = brand?.aliases.join('\n') ?? '';
    mode.value = brand ? 'keep' : 'website';
    open.value = true;
}
watch(
    () => form.domain,
    (domain) => {
        if (
            open.value &&
            !importSource.value &&
            mode.value === 'keep' &&
            domain !== editing.value?.domain
        ) {
            mode.value = 'website';
        }
    },
);
watch(mode, (value) => {
    if (value !== 'upload') {
        form.logo = null;

        if (uploadPreview.value) {
            URL.revokeObjectURL(uploadPreview.value);
            uploadPreview.value = '';
        }
    }
});
watch(open, (value) => {
    if (!value && uploadPreview.value) {
        URL.revokeObjectURL(uploadPreview.value);
        uploadPreview.value = '';
    }
});
function fileChanged(event: Event): void {
    if (uploadPreview.value) {
        URL.revokeObjectURL(uploadPreview.value);
    }

    form.logo = (event.target as HTMLInputElement).files?.[0] ?? null;
    uploadPreview.value = form.logo ? URL.createObjectURL(form.logo) : '';
}
function save(createAnother = false): void {
    if (form.processing) {
        return;
    }

    form.aliases = aliasText.value
        .split('\n')
        .map((value) => value.trim())
        .filter(Boolean);
    form.fetch_logo = mode.value === 'website';
    form.remove_logo = mode.value === 'remove';

    if (mode.value !== 'upload') {
        form.logo = null;
    }

    form.transform((data) => ({
        ...data,
        _method: editing.value ? 'patch' : 'post',
    })).post(editing.value ? update.url(editing.value.uuid) : store.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (createAnother) {
                edit(null);
                nextTick(() => document.getElementById('brand-name')?.focus());
            } else {
                open.value = false;
            }
        },
    });
}
function publish(brand: Brand): void {
    router.post(
        update.url(brand.uuid),
        { ...brand, _method: 'patch', is_published: !brand.is_published },
        { preserveScroll: true },
    );
}
const aliasErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('aliases'))
        .map(([, value]) => value)
        .join(' '),
);
</script>
<template>
    <AppLayout
        ><Head :title="ui('Catalogo marchi')" /><AdminLayout
            ><section class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        :title="ui('Catalogo marchi')"
                        :description="
                            ui(
                                'Marchi pubblici gestiti dagli amministratori. Separati dai riferimenti personali.',
                            )
                        "
                    />
                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" @click="openImports">{{
                            ui('Importa loghi esistenti')
                        }}</Button
                        ><Button @click="edit(null)"
                            ><Plus class="mr-2 size-4" aria-hidden="true" />{{
                                ui('Nuovo marchio')
                            }}</Button
                        >
                    </div>
                </div>
                <CategoryManager
                    :categories="category_management"
                    :selected="selectedBrands"
                    @view="
                        (name) => {
                            search = '';
                            letter = '';
                            categoryFilter = name;
                        }
                    "
                    @removed="
                        (name) => {
                            if (categoryFilter === name) categoryFilter = '';
                        }
                    "
                    @moved="selectedBrands = []"
                    @renamed="
                        (previous, name) => {
                            if (categoryFilter === previous)
                                categoryFilter = name;
                        }
                    "
                />
                <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                    <Input
                        v-model="search"
                        type="search"
                        :aria-label="ui('Cerca marchi')"
                        :placeholder="ui('Nome, alias o dominio')"
                    /><select
                        v-model="categoryFilter"
                        :aria-label="ui('Filtra per categoria')"
                        class="h-10 w-full min-w-0 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">{{ ui('Tutte le categorie') }}</option>
                        <option value="__none__">
                            {{ ui('Nessuna categoria') }}
                        </option>
                        <option
                            v-for="category in categories"
                            :key="category"
                            :value="category"
                        >
                            {{ category }}
                        </option></select
                    ><select
                        v-model="sort"
                        :aria-label="ui('Ordinamento')"
                        class="h-10 w-full max-w-full min-w-0 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="name_asc">A–Z</option>
                        <option value="name_desc">Z–A</option>
                        <option value="updated_desc">
                            {{ ui('Ultima modifica') }}
                        </option>
                    </select>
                </div>
                <div
                    role="group"
                    :aria-label="ui('Filtro alfabetico')"
                    class="flex flex-wrap gap-1"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        :aria-pressed="letter === ''"
                        @click="letter = ''"
                        >{{ ui('Tutti') }}</Button
                    ><Button
                        v-for="character in 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'"
                        :key="character"
                        :variant="letter === character ? 'default' : 'outline'"
                        size="sm"
                        :aria-pressed="letter === character"
                        @click="letter = character"
                        >{{ character }}</Button
                    >
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ brands.total }} {{ ui('marchi') }}
                </p>
                <div class="grid gap-3">
                    <article
                        v-for="brand in brands.data"
                        :key="brand.uuid"
                        class="flex flex-wrap items-center gap-4 rounded-2xl border bg-card p-4"
                    >
                        <input
                            v-model="selectedBrands"
                            type="checkbox"
                            :value="brand.uuid"
                            :aria-label="
                                ui('Seleziona marchio {name}', {
                                    name: brand.name,
                                })
                            "
                            class="size-4 shrink-0"
                        />
                        <img
                            v-if="brand.logo_url"
                            :src="brand.logo_url"
                            alt=""
                            class="size-12 rounded-xl object-contain"
                        /><Tag
                            v-else
                            class="size-10 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <h2 class="font-semibold break-words">
                                {{ brand.name }}
                            </h2>
                            <p class="text-sm break-all text-muted-foreground">
                                {{ brand.domain }}
                            </p>
                            <p
                                v-if="brand.aliases.length"
                                class="text-xs text-muted-foreground"
                            >
                                {{ brand.aliases.join(' · ') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ brand.category || ui('Nessuna categoria') }}
                            </p>
                            <span
                                class="text-xs"
                                :class="
                                    brand.is_published
                                        ? 'text-emerald-600'
                                        : 'text-muted-foreground'
                                "
                                >{{
                                    ui(
                                        brand.is_published
                                            ? 'Pubblicato'
                                            : 'Bozza',
                                    )
                                }}</span
                            >
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="publish(brand)"
                                >{{
                                    ui(
                                        brand.is_published
                                            ? 'Ritira'
                                            : 'Pubblica',
                                    )
                                }}</Button
                            ><Button
                                variant="outline"
                                size="sm"
                                @click="edit(brand)"
                                ><Pencil
                                    class="mr-2 size-4"
                                    aria-hidden="true"
                                />{{ ui('Modifica') }}</Button
                            ><Button
                                variant="outline"
                                size="sm"
                                @click="deleting = brand"
                                ><Trash2
                                    class="mr-2 size-4"
                                    aria-hidden="true"
                                />{{ ui('Rimuovi') }}</Button
                            >
                        </div>
                    </article>
                    <p
                        v-if="!brands.data.length"
                        class="rounded-2xl border p-6 text-muted-foreground"
                    >
                        {{ ui('Nessun marchio trovato') }}
                    </p>
                </div>
                <nav
                    class="flex flex-wrap gap-2"
                    :aria-label="ui('Paginazione')"
                >
                    <template v-for="(link, i) in brands.links" :key="i"
                        ><Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="rounded-md border px-3 py-2 text-sm"
                            :class="
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : ''
                            "
                            ><span v-html="link.label" /></Link
                    ></template>
                </nav>
                <Dialog v-model:open="open"
                    ><DialogContent
                        class="flex max-h-[85dvh] flex-col overflow-hidden sm:max-w-xl"
                        ><DialogHeader
                            ><DialogTitle>{{
                                ui(
                                    editing
                                        ? 'Modifica marchio'
                                        : 'Nuovo marchio',
                                )
                            }}</DialogTitle
                            ><DialogDescription>{{
                                ui(
                                    'Nessun riferimento personale viene importato nel catalogo.',
                                )
                            }}</DialogDescription></DialogHeader
                        >
                        <BrandFormPreview
                            :name="form.name"
                            :domain="form.domain"
                            :category="form.category"
                            :aliases="aliasText"
                            :published="form.is_published"
                            :mode="mode"
                            :stored-logo="editing?.logo_url ?? null"
                            :upload-logo="uploadPreview"
                            :source-logo="importSource?.logo_url ?? null"
                        />
                        <form
                            class="min-h-0 space-y-4 overflow-y-auto p-1"
                            @submit.prevent="save()"
                        >
                            <div class="grid gap-2">
                                <Label for="brand-name">{{
                                    ui('Nome marchio')
                                }}</Label
                                ><Input
                                    id="brand-name"
                                    v-model="form.name"
                                    required
                                    maxlength="150"
                                /><InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="brand-aliases">{{
                                    ui('Alias, uno per riga')
                                }}</Label
                                ><textarea
                                    id="brand-aliases"
                                    v-model="aliasText"
                                    rows="3"
                                    class="rounded-md border bg-background p-2 text-sm"
                                /><InputError :message="aliasErrors" />
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        ui(
                                            'Gli alias sono nomi alternativi, non domini.',
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="brand-domain">{{
                                    ui('Dominio')
                                }}</Label
                                ><Input
                                    id="brand-domain"
                                    v-model="form.domain"
                                    :readonly="!!importSource"
                                    :placeholder="ui('apple.com')"
                                    required
                                /><InputError :message="form.errors.domain" />
                            </div>
                            <div class="grid gap-2">
                                <Label>{{ ui('Domini alternativi') }}</Label>
                                <div
                                    v-for="(
                                        _, index
                                    ) in form.alternative_domains"
                                    :key="index"
                                    class="grid gap-1"
                                >
                                    <div class="flex gap-2">
                                        <Input
                                            v-model="
                                                form.alternative_domains[index]
                                            "
                                            :aria-label="`${ui('Dominio alternativo')} ${index + 1}`"
                                            :placeholder="ui('apple.it')"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="
                                                form.alternative_domains.splice(
                                                    index,
                                                    1,
                                                )
                                            "
                                            >{{ ui('Rimuovi') }}</Button
                                        >
                                    </div>
                                    <InputError
                                        :message="
                                            form.errors[
                                                `alternative_domains.${index}`
                                            ]
                                        "
                                    />
                                </div>
                                <InputError
                                    :message="form.errors.alternative_domains"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="form.alternative_domains.push('')"
                                    >{{ ui('Aggiungi dominio') }}</Button
                                >
                            </div>
                            <div class="grid gap-2">
                                <Label for="brand-category">{{
                                    ui('Categoria del marchio (facoltativa)')
                                }}</Label>
                                <div class="flex min-w-0 gap-2">
                                    <select
                                        id="brand-category"
                                        v-model="form.category"
                                        class="h-10 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="">
                                            {{ ui('Nessuna categoria') }}
                                        </option>
                                        <option
                                            v-for="category in categoryOptions"
                                            :key="category"
                                            :value="category"
                                        >
                                            {{ category }}
                                        </option>
                                    </select>
                                </div>
                                <InputError :message="form.errors.category" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="brand-logo-mode">{{
                                    ui('Logo')
                                }}</Label>
                                <p
                                    v-if="importSource"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{
                                        ui(
                                            'Logo selezionato dall’archivio. Nessun riferimento personale verrà importato.',
                                        )
                                    }}
                                </p>
                                <select
                                    v-else
                                    id="brand-logo-mode"
                                    v-model="mode"
                                    class="h-10 w-full max-w-full min-w-0 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="website">
                                        {{ ui('Logo dal sito') }}
                                    </option>
                                    <option value="keep">
                                        {{ ui('Mantieni logo') }}
                                    </option>
                                    <option value="upload">
                                        {{ ui('Carica immagine') }}
                                    </option>
                                    <option value="remove">
                                        {{ ui('Rimuovi logo') }}
                                    </option></select
                                ><input
                                    v-if="mode === 'upload'"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="w-full min-w-0 text-sm"
                                    :aria-label="ui('Carica logo')"
                                    required
                                    @change="fileChanged"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        ui(
                                            'PNG, JPEG, WebP · max 2 MB · 4096 × 4096 px',
                                        )
                                    }}
                                </p>
                                <InputError
                                    :message="
                                        form.errors.logo ||
                                        form.errors.fetch_logo ||
                                        form.errors.remove_logo
                                    "
                                /><InputError
                                    :message="form.errors.source_logo_uuid"
                                />
                            </div>
                            <label class="flex items-center gap-2 text-sm"
                                ><input
                                    v-model="form.is_published"
                                    type="checkbox"
                                />{{ ui('Pubblicato') }}</label
                            ><InputError :message="form.errors.is_published" />
                            <div
                                class="sticky bottom-0 flex flex-wrap gap-2 bg-background py-2"
                            >
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                    >{{ ui('Salva') }}</Button
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="form.processing"
                                    @click="save(true)"
                                    >{{ ui('Salva e crea nuovo') }}</Button
                                ><Button
                                    type="button"
                                    variant="outline"
                                    :disabled="form.processing"
                                    @click="open = false"
                                    >{{ ui('Annulla') }}</Button
                                >
                            </div>
                        </form></DialogContent
                    ></Dialog
                >
                <Dialog v-model:open="importOpen">
                    <DialogContent
                        class="flex max-h-[85dvh] flex-col overflow-hidden sm:max-w-2xl"
                    >
                        <DialogHeader>
                            <DialogTitle>{{
                                ui('Importa loghi esistenti')
                            }}</DialogTitle>
                            <DialogDescription>{{
                                ui(
                                    'Solo siti con un’immagine disponibile e non ancora presenti nel catalogo. Scegli un logo, poi conferma nome e categoria.',
                                )
                            }}</DialogDescription>
                        </DialogHeader>
                        <Input
                            v-model="candidateSearch"
                            type="search"
                            :aria-label="ui('Cerca dominio da importare')"
                            :placeholder="ui('Cerca dominio da importare')"
                        />
                        <div class="min-h-0 space-y-3 overflow-y-auto">
                            <p
                                v-if="candidatesLoading"
                                role="status"
                                class="text-sm text-muted-foreground"
                            >
                                {{ ui('Caricamento…') }}
                            </p>
                            <div v-else-if="candidatesError" class="space-y-2">
                                <InputError :message="candidatesError" /><Button
                                    variant="outline"
                                    @click="loadCandidates(candidatesPage)"
                                    >{{ ui('Riprova') }}</Button
                                >
                            </div>
                            <template v-else>
                                <p
                                    v-if="!candidates.length"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ ui('Nessun logo da importare') }}
                                </p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button
                                        v-for="candidate in candidates"
                                        :key="candidate.uuid"
                                        type="button"
                                        class="flex min-w-0 items-center gap-3 rounded-xl border p-3 text-left hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                                        @click="chooseImport(candidate)"
                                    >
                                        <img
                                            :src="candidate.logo_url"
                                            alt=""
                                            class="size-12 shrink-0 object-contain"
                                        />
                                        <span
                                            class="min-w-0 text-sm break-all"
                                            >{{ candidate.domain }}</span
                                        >
                                        <Plus
                                            class="ml-auto size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <Button
                                        variant="outline"
                                        :disabled="candidatesPage <= 1"
                                        @click="
                                            loadCandidates(candidatesPage - 1)
                                        "
                                        >{{ ui('Precedente') }}</Button
                                    >
                                    <Button
                                        variant="outline"
                                        :disabled="!candidatesHasMore"
                                        @click="
                                            loadCandidates(candidatesPage + 1)
                                        "
                                        >{{ ui('Successiva') }}</Button
                                    >
                                </div>
                            </template>
                        </div>
                    </DialogContent>
                </Dialog>
                <Dialog
                    :open="!!deleting"
                    @update:open="
                        (value) => {
                            if (!value) {
                                deleting = null;
                            }
                        }
                    "
                    ><DialogContent
                        ><DialogHeader
                            ><DialogTitle>{{
                                ui('Rimuovi marchio')
                            }}</DialogTitle
                            ><DialogDescription>{{
                                ui(
                                    'Il marchio verrà rimosso dal catalogo. I riferimenti personali restano invariati.',
                                )
                            }}</DialogDescription></DialogHeader
                        >
                        <p>{{ deleting?.name }}</p>
                        <div class="flex gap-2">
                            <Button
                                variant="destructive"
                                @click="
                                    router.delete(destroy.url(deleting!.uuid), {
                                        preserveScroll: true,
                                        onSuccess: () => (deleting = null),
                                    })
                                "
                                >{{ ui('Rimuovi') }}</Button
                            ><Button
                                variant="outline"
                                @click="deleting = null"
                                >{{ ui('Annulla') }}</Button
                            >
                        </div></DialogContent
                    ></Dialog
                >
            </section></AdminLayout
        ></AppLayout
    >
</template>
