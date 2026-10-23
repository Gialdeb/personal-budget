<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Loader2,
    MoreHorizontal,
    Pencil,
    Plus,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    store,
    update,
    destroy,
    move,
} from '@/actions/App/Http/Controllers/Admin/BrandCategoryController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useUiCopy } from '@/i18n/ui-copy';
import { normalizeReferenceSearch } from '@/lib/tracked-item-options';

type Category = { uuid: string; name: string; brands_count: number };
const props = defineProps<{ categories: Category[]; selected: string[] }>();
const emit = defineEmits<{
    moved: [];
    removed: [name: string];
    renamed: [previous: string, name: string];
    view: [name: string];
}>();
const ui = useUiCopy();
const open = ref(false);
const mode = ref<'list' | 'create' | 'rename' | 'delete' | 'move'>('list');
const current = ref<Category | null>(null);
const search = ref('');
const sort = ref('asc');
const pageSize = ref(10);
const page = ref(1);
const confirmed = ref(false);
const requestError = ref('');
const form = useForm({
    name: '',
    category_uuid: '',
    source_category_uuid: null as string | null,
    brand_uuids: [] as string[],
    delete_source: false,
});
const titles = computed(() => ({
    list: ui('Categorie del catalogo'),
    create: ui('Nuova categoria'),
    rename: ui('Rinomina categoria'),
    delete: ui('Elimina categoria'),
    move: ui('Sposta marchi'),
}));
const filtered = computed(() => {
    const query = normalizeReferenceSearch(search.value);

    return props.categories
        .filter((category) =>
            normalizeReferenceSearch(category.name).includes(query),
        )
        .sort(
            (first, second) =>
                (sort.value === 'asc' ? 1 : -1) *
                first.name.localeCompare(second.name),
        );
});
const pageCount = computed(() =>
    Math.max(1, Math.ceil(filtered.value.length / pageSize.value)),
);
const rows = computed(() =>
    filtered.value.slice(
        (page.value - 1) * pageSize.value,
        page.value * pageSize.value,
    ),
);
watch([search, sort, pageSize], () => {
    page.value = 1;
});
watch(pageCount, (count) => {
    page.value = Math.min(page.value, count);
});
const targets = computed(() =>
    props.categories.filter(
        (category) => category.uuid !== current.value?.uuid,
    ),
);
const transfersOnDelete = computed(
    () => mode.value === 'delete' && (current.value?.brands_count ?? 0) > 0,
);
function show(
    action: typeof mode.value,
    category: Category | null = null,
): void {
    if (form.processing) {
        return;
    }

    form.reset();
    form.clearErrors();
    requestError.value = '';
    confirmed.value = false;
    current.value = category;
    form.name = category?.name ?? '';
    form.source_category_uuid =
        action === 'move' ? (category?.uuid ?? null) : null;
    form.brand_uuids = category ? [] : [...props.selected];
    mode.value = action;
    open.value = true;
}
function viewBrands(category: Category): void {
    emit('view', category.name);
    open.value = false;
}
function submit(): void {
    if (
        form.processing ||
        (transfersOnDelete.value && (!confirmed.value || !form.category_uuid))
    ) {
        return;
    }

    requestError.value = '';
    form.delete_source = transfersOnDelete.value;

    if (form.delete_source) {
        form.source_category_uuid = current.value!.uuid;
    }

    const options = {
        preserveScroll: true,
        onError: () => {
            requestError.value = ui(
                'Impossibile completare l’operazione. Controlla i dati e riprova.',
            );
        },
        onSuccess: () => {
            if (mode.value === 'rename' && current.value) {
                emit('renamed', current.value.name, form.name);
            }

            if (mode.value === 'delete' && current.value) {
                emit('removed', current.value.name);
            }

            if (mode.value === 'move' || form.delete_source) {
                emit('moved');
            }

            if (mode.value === 'create') {
                search.value = '';
                page.value = 1;
            }

            mode.value = 'list';
            form.clearErrors();
        },
    };

    if (mode.value === 'create') {
        form.post(store.url(), options);
    } else if (mode.value === 'rename' && current.value) {
        form.patch(update.url(current.value.uuid), options);
    } else if (transfersOnDelete.value || mode.value === 'move') {
        form.post(move.url(), options);
    } else if (mode.value === 'delete' && current.value) {
        form.delete(destroy.url(current.value.uuid), options);
    }
}
</script>
<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button variant="outline" @click="show('list')">{{
            ui('Gestisci categorie')
        }}</Button>
        <Button v-if="selected.length" variant="outline" @click="show('move')"
            >{{ ui('Sposta selezionati') }} ({{ selected.length }})</Button
        >
        <Dialog
            :open="open"
            @update:open="
                (value) => {
                    if (!form.processing) open = value;
                }
            "
        >
            <DialogContent
                class="flex max-h-[90dvh] min-w-0 flex-col overflow-hidden"
                :class="mode === 'list' ? 'sm:max-w-4xl' : 'sm:max-w-lg'"
            >
                <DialogHeader class="shrink-0 text-left">
                    <DialogTitle>{{ titles[mode] }}</DialogTitle>
                    <DialogDescription>{{
                        ui(
                            'Categorie indipendenti dalle categorie delle transazioni.',
                        )
                    }}</DialogDescription>
                </DialogHeader>
                <div v-if="mode === 'list'" class="flex min-h-0 flex-col gap-4">
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <Input
                            v-model="search"
                            type="search"
                            :aria-label="ui('Cerca categorie')"
                            :placeholder="ui('Cerca categorie')"
                            class="min-w-0 flex-1 basis-40"
                        />
                        <select
                            v-model="sort"
                            :aria-label="ui('Ordinamento categorie')"
                            class="h-10 rounded-md border bg-background px-2 text-sm"
                        >
                            <option value="asc">A–Z</option>
                            <option value="desc">Z–A</option>
                        </select>
                        <Button class="shrink-0" @click="show('create')"
                            ><Plus class="mr-1 size-4" aria-hidden="true" />{{
                                ui('Nuova categoria')
                            }}</Button
                        >
                    </div>
                    <div class="min-h-0 overflow-y-auto rounded-xl border">
                        <Table
                            class="table-fixed"
                            :aria-label="ui('Categorie del catalogo')"
                        >
                            <caption class="sr-only">
                                {{
                                    ui('Categorie del catalogo')
                                }}
                            </caption>
                            <TableHeader
                                class="sticky top-0 z-10 bg-background"
                            >
                                <TableRow
                                    ><TableHead>{{
                                        ui('Nome categoria')
                                    }}</TableHead
                                    ><TableHead class="w-20 text-center">{{
                                        ui('Marchi')
                                    }}</TableHead
                                    ><TableHead class="w-24 text-right">{{
                                        ui('Azioni')
                                    }}</TableHead></TableRow
                                >
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="category in rows"
                                    :key="category.uuid"
                                >
                                    <TableCell
                                        class="py-2 font-medium break-words whitespace-normal"
                                        >{{ category.name }}</TableCell
                                    >
                                    <TableCell class="py-2 text-center"
                                        ><Button
                                            variant="link"
                                            size="sm"
                                            class="h-8 px-2 tabular-nums"
                                            :aria-label="
                                                ui(
                                                    'Visualizza {count} marchi della categoria {name}',
                                                    {
                                                        name: category.name,
                                                        count: category.brands_count,
                                                    },
                                                )
                                            "
                                            @click="viewBrands(category)"
                                            >{{ category.brands_count }}</Button
                                        ></TableCell
                                    >
                                    <TableCell class="px-2 py-2"
                                        ><div class="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-8"
                                                :aria-label="
                                                    ui('Rinomina categoria')
                                                "
                                                @click="
                                                    show('rename', category)
                                                "
                                                ><Pencil
                                                    class="size-4"
                                                    aria-hidden="true"
                                            /></Button>
                                            <DropdownMenu
                                                ><DropdownMenuTrigger as-child
                                                    ><Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="size-8"
                                                        :aria-label="
                                                            ui(
                                                                'Azioni categoria {name}',
                                                                {
                                                                    name: category.name,
                                                                },
                                                            )
                                                        "
                                                        ><MoreHorizontal
                                                            class="size-4"
                                                            aria-hidden="true" /></Button
                                                ></DropdownMenuTrigger>
                                                <DropdownMenuContent
                                                    class="z-[180]"
                                                    align="end"
                                                    ><DropdownMenuItem
                                                        :disabled="
                                                            !category.brands_count
                                                        "
                                                        @select="
                                                            show(
                                                                'move',
                                                                category,
                                                            )
                                                        "
                                                        >{{
                                                            ui(
                                                                'Riassegna marchi',
                                                            )
                                                        }}</DropdownMenuItem
                                                    ><DropdownMenuSeparator /><DropdownMenuItem
                                                        class="text-destructive focus:text-destructive"
                                                        @select="
                                                            show(
                                                                'delete',
                                                                category,
                                                            )
                                                        "
                                                        >{{
                                                            ui(
                                                                'Elimina categoria',
                                                            )
                                                        }}</DropdownMenuItem
                                                    ></DropdownMenuContent
                                                >
                                            </DropdownMenu>
                                        </div></TableCell
                                    >
                                </TableRow>
                                <TableRow v-if="!rows.length"
                                    ><TableCell
                                        colspan="3"
                                        class="p-8 text-center text-muted-foreground"
                                        >{{
                                            categories.length
                                                ? ui(
                                                      'Nessuna categoria corrisponde alla ricerca.',
                                                  )
                                                : ui(
                                                      'Nessuna categoria. Crea la prima per organizzare i marchi.',
                                                  )
                                        }}</TableCell
                                    ></TableRow
                                >
                            </TableBody>
                        </Table>
                    </div>
                    <div
                        class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t pt-3"
                    >
                        <label
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                            >{{ ui('Righe per pagina')
                            }}<select
                                v-model.number="pageSize"
                                class="h-9 rounded-md border bg-background px-2 text-foreground"
                            >
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                            </select></label
                        >
                        <nav
                            class="flex items-center gap-2"
                            :aria-label="ui('Paginazione categorie')"
                        >
                            <span
                                class="text-xs text-muted-foreground"
                                role="status"
                                >{{
                                    ui(
                                        'Pagina {page} di {pages} · {count} categorie',
                                        {
                                            page,
                                            pages: pageCount,
                                            count: filtered.length,
                                        },
                                    )
                                }}</span
                            >
                            <Button
                                variant="outline"
                                size="icon"
                                class="size-8"
                                :aria-label="ui('Pagina precedente')"
                                :disabled="page === 1"
                                @click="page--"
                                ><ChevronLeft class="size-4" aria-hidden="true"
                            /></Button>
                            <Button
                                variant="outline"
                                size="icon"
                                class="size-8"
                                :aria-label="ui('Pagina successiva')"
                                :disabled="page === pageCount"
                                @click="page++"
                                ><ChevronRight
                                    class="size-4"
                                    aria-hidden="true"
                            /></Button>
                        </nav>
                    </div>
                </div>
                <form
                    v-else
                    class="min-h-0 space-y-4 overflow-y-auto p-1"
                    :aria-busy="form.processing"
                    @submit.prevent="submit"
                >
                    <div
                        v-if="mode === 'create' || mode === 'rename'"
                        class="grid gap-2"
                    >
                        <Label for="catalog-category-name">{{
                            ui('Nome categoria')
                        }}</Label
                        ><Input
                            id="catalog-category-name"
                            v-model="form.name"
                            required
                            maxlength="100"
                            :disabled="form.processing"
                        />
                    </div>
                    <p v-if="mode === 'move'" class="text-sm">
                        {{
                            current
                                ? `${current.name} · ${current.brands_count}`
                                : selected.length
                        }}
                        {{ ui('marchi da spostare') }}
                    </p>
                    <template v-if="mode === 'delete'">
                        <p class="font-medium break-words">
                            {{ current?.name }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                transfersOnDelete
                                    ? ui(
                                          'La categoria contiene marchi. Trasferiscili tutti prima di eliminarla.',
                                      )
                                    : ui(
                                          'Confermi l’eliminazione della categoria vuota?',
                                      )
                            }}
                        </p>
                    </template>
                    <div
                        v-if="mode === 'move' || transfersOnDelete"
                        class="grid gap-2"
                    >
                        <Label for="catalog-category-target">{{
                            ui('Categoria di destinazione')
                        }}</Label>
                        <select
                            id="catalog-category-target"
                            v-model="form.category_uuid"
                            required
                            :disabled="form.processing"
                            class="h-10 w-full min-w-0 rounded-md border bg-background px-3 text-sm"
                        >
                            <option disabled value="">
                                {{ ui('Scegli categoria') }}
                            </option>
                            <option
                                v-for="category in targets"
                                :key="category.uuid"
                                :value="category.uuid"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <p
                            v-if="!targets.length"
                            class="text-sm text-muted-foreground"
                        >
                            {{
                                ui('Crea prima una categoria di destinazione.')
                            }}
                        </p>
                    </div>
                    <label
                        v-if="transfersOnDelete"
                        class="flex items-start gap-2 text-sm"
                        ><input
                            v-model="confirmed"
                            type="checkbox"
                            required
                            :disabled="form.processing"
                            class="mt-1 shrink-0"
                        />{{
                            ui(
                                'Confermo il trasferimento di tutti i marchi e l’eliminazione della categoria.',
                            )
                        }}</label
                    >
                    <p
                        v-if="requestError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ requestError }}
                    </p>
                    <InputError
                        v-for="(error, key) in form.errors"
                        :key="key"
                        :message="error"
                    />
                    <p
                        v-if="form.processing"
                        role="status"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Loader2
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />{{ ui('Operazione in corso…') }}
                    </p>
                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            @click="show('list')"
                            >{{ ui('Indietro') }}</Button
                        >
                        <Button
                            type="submit"
                            :variant="
                                mode === 'delete' ? 'destructive' : 'default'
                            "
                            :disabled="
                                form.processing ||
                                ((mode === 'move' || transfersOnDelete) &&
                                    !form.category_uuid) ||
                                (transfersOnDelete && !confirmed)
                            "
                            >{{
                                transfersOnDelete
                                    ? ui('Trasferisci ed elimina')
                                    : mode === 'delete'
                                      ? ui('Elimina categoria')
                                      : ui('Conferma')
                            }}</Button
                        >
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
