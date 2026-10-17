<script setup lang="ts">
import { useIntersectionObserver, useMediaQuery } from '@vueuse/core';
import {
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    LoaderCircle,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import TrackedItemIdentity from '@/components/tracked-items/TrackedItemIdentity.vue';
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
import { formatCurrency } from '@/lib/currency';
import type { RecurringEntryFormOptions } from '@/types';

type Candidate = NonNullable<
    RecurringEntryFormOptions['existing_plan_candidates']
>[number];
const props = defineProps<{
    candidates: Candidate[];
    locale: string;
    scrollContainer?: HTMLElement | null;
}>();
const { t } = useI18n();
const ui = useUiCopy();
const isMobile = useMediaQuery('(max-width: 767px)');
const preview = ref<HTMLElement | null>(null);
const revealed = ref(false);
const { stop: stopObserving } = useIntersectionObserver(
    preview,
    ([entry]) => {
        if (entry?.isIntersecting) {
            revealed.value = true;
            stopObserving();
        }
    },
    { root: computed(() => props.scrollContainer ?? null), threshold: 0.15 },
);
function scrollToPreview(): void {
    const container = props.scrollContainer;
    const target = preview.value;

    if (!container || !target) {
        return;
    }

    requestAnimationFrame(() => {
        container.scrollTo({
            top:
                container.scrollTop +
                target.getBoundingClientRect().top -
                container.getBoundingClientRect().top -
                16,
            behavior: matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 'instant'
                : 'smooth',
        });
    });
}
function hasInstallmentAmount(
    candidate: Candidate,
): candidate is Candidate & { installment_amount: number } {
    return (
        typeof candidate.installment_amount === 'number' &&
        Number.isFinite(candidate.installment_amount) &&
        candidate.installment_amount > 0
    );
}
defineExpose({ scrollToPreview });
const carousel = ref<HTMLElement | null>(null);
const position = ref(0);
const detailOpen = ref(false);
const detailUrl = ref('');
const detailTitle = ref('');
const loading = ref(false);
let previousFocus: HTMLElement | null = null;
let previousScroll = 0;

watch(
    () => props.candidates.map((candidate) => candidate.uuid).join(','),
    async () => {
        position.value = 0;
        await nextTick();
        carousel.value?.scrollTo({ left: 0, behavior: 'instant' });
    },
);
function navigate(index: number): void {
    const container = carousel.value;

    if (!container) {
        return;
    }

    const next = Math.max(0, Math.min(props.candidates.length - 1, index));
    const cards = container.querySelectorAll<HTMLElement>('article');
    container.scrollTo({
        left: cards[next].offsetLeft - cards[0].offsetLeft,
        behavior: matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'instant'
            : 'smooth',
    });
}
function updatePosition(): void {
    const container = carousel.value;

    if (!container) {
        return;
    }

    const cards = container.querySelectorAll<HTMLElement>('article');
    const step =
        cards.length > 1
            ? cards[1].offsetLeft - cards[0].offsetLeft
            : container.clientWidth;
    position.value = Math.max(
        0,
        Math.min(
            props.candidates.length - 1,
            Math.round(container.scrollLeft / step),
        ),
    );
}
function onKeydown(event: KeyboardEvent): void {
    if (event.target !== event.currentTarget) {
        return;
    }

    const index =
        event.key === 'ArrowRight'
            ? position.value + 1
            : event.key === 'ArrowLeft'
              ? position.value - 1
              : event.key === 'Home'
                ? 0
                : event.key === 'End'
                  ? props.candidates.length - 1
                  : null;

    if (index === null) {
        return;
    }

    event.preventDefault();
    navigate(index);
}
function openDetail(
    event: MouseEvent,
    candidate: Candidate,
    reactivate = false,
): void {
    if (
        !isMobile.value ||
        event.ctrlKey ||
        event.metaKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    event.preventDefault();
    previousFocus = event.currentTarget as HTMLElement;
    previousScroll = props.scrollContainer?.scrollTop ?? 0;
    detailUrl.value = reactivate ? candidate.reactivation_url : candidate.url;
    detailTitle.value = candidate.title;
    loading.value = true;
    detailOpen.value = true;
}
function restoreForm(event: Event): void {
    event.preventDefault();
    // Reka releases the nested focus scope in its own next task.
    void nextTick(() => {
        window.setTimeout(() => {
            previousFocus?.focus({ preventScroll: true });
            // The form centers focused controls in the next animation frame.
            requestAnimationFrame(() => {
                props.scrollContainer?.scrollTo({
                    top: previousScroll,
                    behavior: 'instant',
                });
            });
        }, 0);
    });
}
function dateLabel(value: string): string {
    const [year, month, day] = value.split('-').map(Number);

    return new Intl.DateTimeFormat(props.locale, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
}
</script>

<template>
    <section
        ref="preview"
        class="min-w-0 space-y-2 transition-[opacity,translate] duration-600 ease-out motion-reduce:translate-y-0 motion-reduce:opacity-100 motion-reduce:transition-none"
        :class="
            revealed ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'
        "
        :data-revealed="revealed"
        :aria-label="ui('Piani esistenti')"
    >
        <div class="flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold">{{ ui('Piani esistenti') }}</h3>
            <div
                v-if="candidates.length > 1"
                class="flex shrink-0 items-center gap-1"
            >
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="hidden size-9 md:inline-flex"
                    :disabled="position === 0"
                    :aria-label="ui('Piano precedente')"
                    @click="navigate(position - 1)"
                    ><ChevronLeft class="size-4"
                /></Button>
                <span
                    class="text-xs text-muted-foreground tabular-nums"
                    aria-live="polite"
                    >{{ position + 1 }} {{ ui('di') }}
                    {{ candidates.length }}</span
                >
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="hidden size-9 md:inline-flex"
                    :disabled="position === candidates.length - 1"
                    :aria-label="ui('Piano successivo')"
                    @click="navigate(position + 1)"
                    ><ChevronRight class="size-4"
                /></Button>
            </div>
        </div>
        <div
            ref="carousel"
            class="relative flex w-full min-w-0 snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain rounded-2xl pb-1 outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :tabindex="candidates.length > 1 ? 0 : undefined"
            :aria-label="ui('Piani esistenti')"
            @scroll.passive="updatePosition"
            @keydown="onKeydown"
        >
            <article
                v-for="(candidate, index) in candidates"
                :key="candidate.uuid"
                :inert="index !== position"
                :aria-hidden="index !== position"
                class="flex w-full min-w-0 shrink-0 snap-start flex-col gap-3 rounded-2xl border bg-muted/20 p-3 sm:p-4"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <TrackedItemIdentity
                        :name="candidate.reference_name"
                        :logo-url="candidate.reference_logo_url"
                        compact
                        :show-name="false"
                    />
                    <div class="min-w-0 flex-1">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p
                                class="min-w-0 text-sm font-semibold break-words"
                            >
                                {{ candidate.title }}
                            </p>
                            <Badge
                                variant="secondary"
                                class="shrink-0 text-[11px]"
                                >{{
                                    t(
                                        `transactions.recurring.enums.planStatus.${candidate.status}`,
                                    )
                                }}</Badge
                            >
                        </div>
                        <p
                            class="mt-1 text-xs break-words text-muted-foreground"
                        >
                            {{ candidate.reference_name
                            }}<template v-if="candidate.account_name">
                                · {{ candidate.account_name }}</template
                            >
                        </p>
                    </div>
                </div>
                <div class="space-y-1 text-sm">
                    <p
                        v-if="
                            candidate.total_amount != null &&
                            Number.isFinite(candidate.total_amount)
                        "
                    >
                        <span class="text-muted-foreground"
                            >{{ ui('Importo totale') }}:</span
                        >
                        {{
                            formatCurrency(
                                candidate.total_amount,
                                candidate.currency,
                                locale,
                            )
                        }}
                    </p>
                    <p v-if="hasInstallmentAmount(candidate)">
                        <span class="text-muted-foreground"
                            >{{ ui('Prossima rata') }}:</span
                        >
                        <strong class="font-semibold">{{
                            formatCurrency(
                                candidate.installment_amount,
                                candidate.currency,
                                locale,
                            )
                        }}</strong>
                    </p>
                    <p
                        v-else-if="
                            candidate.amount != null &&
                            Number.isFinite(candidate.amount)
                        "
                        class="font-semibold"
                    >
                        {{
                            formatCurrency(
                                candidate.amount,
                                candidate.currency,
                                locale,
                            )
                        }}
                    </p>
                    <p
                        v-else-if="candidate.total_amount != null"
                        class="text-xs text-muted-foreground"
                    >
                        {{ ui('Importo rata non disponibile') }}
                    </p>
                    <p v-else-if="candidate.total_amount == null">
                        {{ ui('Importo variabile') }} · {{ candidate.currency }}
                    </p>
                    <p
                        v-if="
                            hasInstallmentAmount(candidate) &&
                            candidate.installment_remaining_amount != null &&
                            Number.isFinite(
                                candidate.installment_remaining_amount,
                            ) &&
                            candidate.installment_remaining_amount <
                                candidate.installment_amount
                        "
                        class="text-xs text-muted-foreground"
                    >
                        {{ ui('Residuo rata') }}:
                        {{
                            formatCurrency(
                                candidate.installment_remaining_amount,
                                candidate.currency,
                                locale,
                            )
                        }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            candidate.recurrence_interval > 1
                                ? candidate.recurrence_interval
                                : ''
                        }}
                        {{
                            t(
                                `transactions.recurring.${candidate.recurrence_interval > 1 ? 'pauseUnits' : 'form.repeatPresets'}.${candidate.recurrence_type}`,
                            )
                        }}<template
                            v-if="
                                candidate.status === 'cancelled' &&
                                candidate.cancelled_on
                            "
                        >
                            · {{ ui('Annullato il') }}
                            {{ dateLabel(candidate.cancelled_on) }}</template
                        ><template v-else-if="candidate.next_due_on">
                            · {{ ui('Prossima scadenza') }}
                            {{ dateLabel(candidate.next_due_on) }}</template
                        ><template v-else-if="candidate.activated_on">
                            · {{ ui('Decorrenza') }}
                            {{ dateLabel(candidate.activated_on) }}</template
                        >
                    </p>
                </div>
                <div class="mt-auto flex flex-wrap gap-x-4 gap-y-1">
                    <a
                        :href="candidate.url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex min-h-11 items-center gap-1 text-xs font-semibold text-primary underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-2"
                        @click="openDetail($event, candidate)"
                        >{{ ui('Visualizza dettagli')
                        }}<ExternalLink v-if="!isMobile" class="size-3"
                    /></a>
                    <a
                        v-if="candidate.can_reactivate"
                        :href="candidate.reactivation_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex min-h-11 items-center text-xs font-semibold text-primary underline-offset-4 hover:underline focus-visible:rounded focus-visible:outline-2"
                        @click="openDetail($event, candidate, true)"
                        >{{ ui('Riattiva piano esistente') }}</a
                    >
                </div>
            </article>
        </div>
        <p class="text-xs leading-relaxed text-muted-foreground">
            {{
                ui(
                    'Puoi sempre creare un altro piano con lo stesso riferimento.',
                )
            }}
        </p>
        <Sheet v-model:open="detailOpen">
            <SheetContent
                data-recurring-plan-detail
                side="bottom"
                class="z-[210] h-[100dvh] max-h-[100dvh] w-full gap-0 overflow-hidden rounded-t-2xl p-0 motion-reduce:transition-none motion-reduce:data-[state=closed]:animate-none motion-reduce:data-[state=open]:animate-none"
                @close-auto-focus="restoreForm"
            >
                <SheetHeader
                    class="shrink-0 border-b px-4 py-4 pr-12 text-left"
                >
                    <SheetTitle class="text-base">{{ detailTitle }}</SheetTitle>
                    <SheetDescription>{{
                        ui('Chiudi il dettaglio per tornare al form compilato.')
                    }}</SheetDescription>
                </SheetHeader>
                <div class="relative min-h-0 flex-1">
                    <LoaderCircle
                        v-if="loading"
                        class="absolute top-4 left-1/2 size-5 animate-spin text-muted-foreground"
                        :aria-label="ui('Caricamento')"
                    />
                    <iframe
                        v-if="detailOpen"
                        :src="detailUrl"
                        :title="ui('Visualizza dettagli')"
                        class="h-full w-full border-0 bg-background"
                        @load="loading = false"
                    />
                </div>
            </SheetContent>
        </Sheet>
    </section>
</template>
