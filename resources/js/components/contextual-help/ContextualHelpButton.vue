<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CircleHelp, ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import PublicRichContentRenderer from '@/components/public/editorial/PublicRichContentRenderer.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { CurrentContextualHelpSharedData } from '@/types';

const page = usePage();

const contextualHelp = computed(
    () => (page.props.contextualHelp as CurrentContextualHelpSharedData | null) ?? null,
);
const hasContextualHelp = computed(() => contextualHelp.value !== null);
</script>

<template>
    <Sheet v-if="hasContextualHelp">
        <SheetTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="h-10 w-10 rounded-full border border-slate-200/80 bg-white/90 shadow-sm transition hover:bg-white dark:border-slate-800 dark:bg-slate-950/80 dark:hover:bg-slate-900"
                :aria-label="contextualHelp.title ?? 'Guida contestuale'"
            >
                <CircleHelp class="size-4" />
            </Button>
        </SheetTrigger>

        <SheetContent
            side="right"
            class="w-full overflow-y-auto border-l p-0 sm:max-w-2xl"
        >
            <div class="flex min-h-full flex-col bg-white dark:bg-slate-950">
                <SheetHeader
                    class="border-b border-slate-200/70 px-6 py-5 text-left dark:border-slate-800"
                >
                    <SheetTitle
                        class="text-2xl leading-tight font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                    >
                        {{ contextualHelp.title }}
                    </SheetTitle>
                </SheetHeader>

                <div class="flex-1 space-y-6 px-6 py-6">
                    <PublicRichContentRenderer
                        :content="contextualHelp.body ?? '<p></p>'"
                        class="text-[15px] leading-7"
                    />

                    <div
                        v-if="contextualHelp.knowledge_article"
                        class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/90 p-4 dark:border-slate-800 dark:bg-slate-900/80"
                    >
                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                            Approfondisci nella guida completa
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-400">
                            {{ contextualHelp.knowledge_article.title ?? 'Apri l’articolo collegato del Help Center' }}
                        </p>
                        <Link
                            :href="contextualHelp.knowledge_article.url"
                            class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[#b65642] transition hover:text-[#9e4838]"
                        >
                            Apri articolo
                            <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
