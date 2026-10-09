<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUiCopy } from '@/i18n/ui-copy';

const ui = useUiCopy();

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        isPublished: boolean;
        sortOrder: number;
        publishedAt?: string;
        showPublishedAt?: boolean;
        publishedAtHint?: string;
    }>(),
    {
        title: undefined,
        description: undefined,
        publishedAt: '',
        showPublishedAt: false,
        publishedAtHint: undefined,
    },
);

const emit = defineEmits<{
    'update:isPublished': [value: boolean];
    'update:sortOrder': [value: number];
    'update:publishedAt': [value: string];
}>();
</script>

<template>
    <Card class="rounded-[1.5rem] border-slate-200/80">
        <CardHeader>
            <CardTitle class="text-base">{{
                props.title ?? ui('Pubblicazione')
            }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-5">
            <label
                class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-4"
            >
                <input
                    :model-value="props.isPublished"
                    type="checkbox"
                    class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-950"
                    @change="
                        emit(
                            'update:isPublished',
                            ($event.target as HTMLInputElement).checked,
                        )
                    "
                />
                <span class="space-y-1">
                    <span class="block text-sm font-medium text-slate-950">
                        {{ ui('Contenuto pubblicato') }}
                    </span>
                    <span class="block text-sm leading-6 text-slate-600">
                        {{
                            props.description ??
                            ui(
                                'Se disattivi la pubblicazione, il contenuto resta nel database ma non compare nel Help Center pubblico.',
                            )
                        }}
                    </span>
                </span>
            </label>

            <div class="space-y-2">
                <Label for="sort-order">{{ ui('Sort order') }}</Label>
                <Input
                    id="sort-order"
                    :model-value="String(props.sortOrder)"
                    type="number"
                    min="0"
                    @update:model-value="
                        emit('update:sortOrder', Number($event || 0))
                    "
                />
            </div>

            <div v-if="props.showPublishedAt" class="space-y-2">
                <Label for="published-at">{{ ui('Published at') }}</Label>
                <Input
                    id="published-at"
                    :model-value="props.publishedAt"
                    type="datetime-local"
                    @update:model-value="
                        emit('update:publishedAt', String($event ?? ''))
                    "
                />
                <p class="text-xs leading-5 text-slate-500">
                    {{
                        props.publishedAtHint ??
                        ui(
                            'Se lasci vuoto e pubblichi l’articolo, viene usata la data corrente.',
                        )
                    }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
