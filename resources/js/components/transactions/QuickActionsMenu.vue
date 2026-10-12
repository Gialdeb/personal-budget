<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { Zap, Plus, Settings2, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    active,
    index,
} from '@/actions/App/Http/Controllers/Settings/TransactionTemplateController';
import MobileQuickChoices from '@/components/transactions/MobileQuickChoices.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { useUiCopy } from '@/i18n/ui-copy';
import { groupQuickActions } from '@/lib/quick-action-groups';
import {
    transactionTemplateIcon,
    transactionTemplateColorStyle,
} from '@/lib/transaction-template-appearance';
import { show } from '@/routes/transactions';

const ui = useUiCopy();
const props = defineProps<{ year: number; month: number }>();
const mobile = useMediaQuery('(max-width: 767px)');
const open = ref(false);
const loading = ref(false);
const error = ref('');
const selectedCategory = ref('');
type QuickAction = {
    uuid: string;
    name: string;
    icon: string;
    color: string;
    shared_enabled: boolean;
    account_name: string | null;
    category: {
        uuid: string;
        name: string;
        full_path: string;
        icon: string | null;
        color: string | null;
    } | null;
    reference: { name: string; logo_url: string | null } | null;
};
const templates = ref<QuickAction[]>([]);
const groups = computed(() =>
    groupQuickActions(templates.value, ui('Senza categoria')),
);
const categoryChoices = computed(() =>
    groups.value.map((group) => ({
        value: group.uuid,
        label: group.name,
        icon: group.icon,
        color: group.color,
    })),
);
const selectedGroup = computed(() =>
    groups.value.find((group) => group.uuid === selectedCategory.value),
);
const failedLogos = ref(new Set<string>());
const colors: Record<string, string> = {
    primary: 'text-primary bg-primary/10',
    sky: 'text-sky-600 bg-sky-500/10 dark:text-sky-400',
    violet: 'text-violet-600 bg-violet-500/10 dark:text-violet-400',
    emerald: 'text-emerald-600 bg-emerald-500/10 dark:text-emerald-400',
    rose: 'text-rose-600 bg-rose-500/10 dark:text-rose-400',
    amber: 'text-amber-600 bg-amber-500/10 dark:text-amber-400',
};
watch(open, async (value) => {
    if (!value) {
        return;
    }

    loading.value = true;
    error.value = '';
    selectedCategory.value = '';
    templates.value = [];

    try {
        const response = await fetch(active.url(), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error();
        }

        templates.value = await response.json();

        if (groups.value.length === 1) {
            selectedCategory.value = groups.value[0].uuid;
        }
    } catch {
        error.value = ui('Impossibile caricare le azioni. Riprova.');
    } finally {
        loading.value = false;
    }
});
const templateHref = (uuid: string) =>
    show(
        { year: props.year, month: props.month },
        { query: { create: 1, template: uuid } },
    );
</script>

<template>
    <div class="shrink-0">
        <Button
            variant="ghost"
            size="icon"
            class="h-10 w-10 rounded-full border border-border bg-background shadow-sm"
            :aria-label="ui('⚡ Rapide')"
            @click="open = true"
        >
            <Zap class="size-4" aria-hidden="true" />
        </Button>
        <Sheet v-model:open="open">
            <SheetContent
                :side="mobile ? 'bottom' : 'right'"
                :class="
                    mobile
                        ? 'flex max-h-[85dvh] flex-col overflow-hidden rounded-t-3xl p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))]'
                        : 'flex w-full flex-col overflow-hidden p-6 sm:max-w-md'
                "
            >
                <SheetHeader class="pr-6">
                    <SheetTitle>{{ ui('⚡ Rapide') }}</SheetTitle>
                    <SheetDescription>{{
                        ui('Scegli una categoria, poi un’azione rapida.')
                    }}</SheetDescription>
                </SheetHeader>
                <div
                    class="min-h-0 min-w-0 flex-1 space-y-4 overflow-x-hidden overflow-y-auto"
                >
                    <p
                        v-if="loading"
                        role="status"
                        class="text-sm text-muted-foreground"
                    >
                        {{ ui('Caricamento…') }}
                    </p>
                    <p
                        v-else-if="error"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                    <p
                        v-else-if="!templates.length"
                        class="text-sm text-muted-foreground"
                    >
                        {{ ui('Crea la tua prima azione rapida.') }}
                    </p>
                    <template v-else>
                        <MobileQuickChoices
                            v-model="selectedCategory"
                            :options="categoryChoices"
                            appearance="category"
                            :aria-label="ui('Categorie')"
                        />
                        <section
                            v-if="selectedGroup"
                            :aria-label="selectedGroup.name"
                            class="space-y-2"
                        >
                            <h3 class="text-sm font-semibold">
                                {{ selectedGroup.name }}
                            </h3>
                            <Link
                                v-for="item in selectedGroup.templates"
                                :key="item.uuid"
                                :href="templateHref(item.uuid)"
                                class="flex min-h-16 items-center gap-3 rounded-2xl border border-border bg-card p-3 transition hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"
                                @click="open = false"
                            >
                                <span
                                    class="shrink-0 rounded-xl p-2.5"
                                    :class="
                                        colors[item.color] ?? colors.primary
                                    "
                                    :style="
                                        transactionTemplateColorStyle(
                                            item.color,
                                        )
                                    "
                                >
                                    <component
                                        :is="transactionTemplateIcon(item.icon)"
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span class="min-w-0 flex-1 space-y-1">
                                    <span
                                        class="block font-semibold break-words"
                                        >{{ item.name }}</span
                                    >
                                    <span
                                        class="block text-xs break-words text-muted-foreground"
                                    >
                                        {{ item.category?.full_path
                                        }}<template v-if="item.account_name">
                                            · {{ item.account_name }}</template
                                        >
                                    </span>
                                    <span
                                        v-if="item.reference"
                                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                    >
                                        <img
                                            v-if="
                                                item.reference.logo_url &&
                                                !failedLogos.has(
                                                    item.reference.logo_url,
                                                )
                                            "
                                            :src="item.reference.logo_url"
                                            alt=""
                                            class="size-5 shrink-0 rounded object-contain"
                                            @error="
                                                failedLogos.add(
                                                    item.reference.logo_url!,
                                                )
                                            "
                                        />
                                        <Wallet
                                            v-else
                                            class="size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span class="break-words">{{
                                            item.reference.name
                                        }}</span>
                                    </span>
                                </span>
                                <Badge
                                    v-if="item.shared_enabled"
                                    variant="secondary"
                                    class="shrink-0 rounded-full text-xs"
                                    >{{ ui('Condivisa') }}</Badge
                                >
                            </Link>
                        </section>
                    </template>
                </div>
                <div class="grid shrink-0 gap-1 border-t pt-2">
                    <Link
                        :href="index({ query: { create: 1 } })"
                        class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-primary hover:bg-accent"
                        @click="open = false"
                        ><Plus class="size-4" />{{
                            ui('Nuova azione rapida')
                        }}</Link
                    >
                    <Link
                        :href="index()"
                        class="flex min-h-11 items-center gap-3 rounded-xl px-3 hover:bg-accent"
                        @click="open = false"
                        ><Settings2 class="size-4" />{{
                            ui('Gestisci azioni')
                        }}</Link
                    >
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
