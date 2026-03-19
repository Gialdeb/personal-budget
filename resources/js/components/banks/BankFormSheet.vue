<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { store, update } from '@/routes/banks';
import type { UserBankItem } from '@/types';

const props = defineProps<{
    open: boolean;
    bank?: UserBankItem | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [message: string];
}>();

const form = useForm({
    mode: 'custom',
    name: '',
    slug: '',
    is_active: true,
});

let slugDirty = false;

const isEditing = computed(
    () => props.bank !== null && props.bank !== undefined,
);

watch(
    () => [props.open, props.bank] as const,
    ([open, bank]) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        slugDirty = false;

        if (bank) {
            form.defaults({
                mode: 'custom',
                name: bank.name,
                slug: bank.slug,
                is_active: bank.is_active,
            });
            form.reset();
            slugDirty = bank.slug !== slugify(bank.name);

            return;
        }

        form.defaults({
            mode: 'custom',
            name: '',
            slug: '',
            is_active: true,
        });
        form.reset();
    },
    { immediate: true },
);

watch(
    () => form.name,
    (value) => {
        if (!slugDirty) {
            form.slug = slugify(value);
        }
    },
);

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function closeSheet(): void {
    emit('update:open', false);
}

function setActiveState(checked: boolean | 'indeterminate'): void {
    form.is_active = checked === true;
}

function submit(): void {
    if (isEditing.value && props.bank) {
        form.patch(update.url(props.bank.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved', 'Banca personalizzata aggiornata con successo.');
                closeSheet();
            },
        });

        return;
    }

    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', 'Banca personalizzata creata con successo.');
            closeSheet();
        },
    });
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full border-l p-0 sm:max-w-xl">
            <div class="flex h-full flex-col">
                <SheetHeader
                    class="border-b border-slate-200/80 px-6 py-6 dark:border-slate-800"
                >
                    <SheetTitle>
                        {{
                            isEditing
                                ? 'Modifica banca personalizzata'
                                : 'Nuova banca personalizzata'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{
                            isEditing
                                ? 'Aggiorna i dati della banca personale disponibile solo per il tuo profilo.'
                                : 'Aggiungi una banca personalizzata quando non è presente nel catalogo condiviso.'
                        }}
                    </SheetDescription>
                </SheetHeader>

                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-5">
                            <div class="grid gap-2">
                                <Label for="name">Nome banca</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    placeholder="Es. Banca locale, Cassa condominio"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    v-model="form.slug"
                                    @update:model-value="slugDirty = true"
                                    class="h-11 rounded-2xl border-slate-200 dark:border-slate-800"
                                    placeholder="banca-locale"
                                />
                                <InputError :message="form.errors.slug" />
                            </div>
                        </div>

                        <label
                            class="flex items-start gap-3 rounded-2xl bg-slate-50/90 p-4 dark:bg-slate-900/80"
                        >
                            <Checkbox
                                :model-value="form.is_active"
                                @update:model-value="setActiveState"
                            />
                            <div>
                                <p
                                    class="text-sm font-medium text-slate-950 dark:text-slate-50"
                                >
                                    Banca attiva
                                </p>
                                <p
                                    class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Le banche disattive restano in archivio ma
                                    non dovrebbero comparire nelle scelte
                                    operative.
                                </p>
                            </div>
                        </label>

                        <div
                            class="flex flex-col gap-3 border-t border-slate-200/80 pt-5 sm:flex-row sm:justify-end dark:border-slate-800"
                        >
                            <Button
                                type="button"
                                variant="secondary"
                                class="h-11 rounded-2xl px-5"
                                @click="closeSheet"
                            >
                                Annulla
                            </Button>
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="h-11 rounded-2xl px-5"
                            >
                                {{
                                    isEditing ? 'Salva modifiche' : 'Crea banca'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
