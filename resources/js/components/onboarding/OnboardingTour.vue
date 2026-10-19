<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { useOnboarding } from '@/composables/useOnboarding';
import { readDashboardQuickStartDismissed } from '@/lib/dashboard-quick-start.js';
import {
    findOnboardingTarget,
    onboardingPosition,
    trapOnboardingFocus,
} from '@/lib/onboarding-tour.js';
import type { OnboardingStep } from '@/onboarding/tours';
import { tours } from '@/onboarding/tours';
import { edit as editBanks } from '@/routes/banks';

const page = usePage();
const { t } = useI18n();
const onboarding = useOnboarding();
const mounted = ref(false);
const steps = ref<OnboardingStep[]>([]);
const index = ref(0);
const shown = new Set<string>();
const rect = ref<DOMRect | null>(null);
const dialog = ref<HTMLElement | null>(null);
const viewportProbe = ref<HTMLElement | null>(null);
const cardStyle = ref<Record<string, string>>({});
const blocked = ref(false);
const unavailable = ref(false);
const active = computed(() => steps.value[index.value]);
const visible = computed(
    () => !!active.value && !!rect.value && !blocked.value,
);
let previousFocus: HTMLElement | null = null;
const inertElements = new Map<HTMLElement, boolean>();
let generation = 0;
let frame = 0;
let startTimer: number | undefined;
let targetTimer: number | undefined;
let observer: MutationObserver | undefined;
let resizeObserver: ResizeObserver | undefined;
let locating = false;
let navigating = false;
let autoAttempted = false;
const removeRouterListeners: (() => void)[] = [];

function releaseBackground(): void {
    for (const [element, wasInert] of inertElements) {
        if (!wasInert) {
            element.inert = false;
        }
    }

    inertElements.clear();
}

function protectBackground(): void {
    for (const element of document.body.children) {
        if (
            !(element instanceof HTMLElement) ||
            element.hasAttribute('data-onboarding-layer') ||
            inertElements.has(element)
        ) {
            continue;
        }

        inertElements.set(element, element.inert);
        element.inert = true;
    }
}

function restoreFocus(): void {
    if (previousFocus?.isConnected && !previousFocus.closest('[inert]')) {
        previousFocus.focus({ preventScroll: true });
    } else if (onboarding.hasTour.value) {
        findOnboardingTarget(document, 'chrome.help')?.focus({
            preventScroll: true,
        });
    }

    previousFocus = null;
}

function stop(restore = true): void {
    generation += 1;
    window.clearTimeout(startTimer);
    window.clearTimeout(targetTimer);
    resizeObserver?.disconnect();
    locating = false;
    steps.value = [];
    rect.value = null;
    releaseBackground();

    if (restore) {
        restoreFocus();
    }
}

function hasOtherOverlay(): boolean {
    if (
        document.documentElement.hasAttribute('data-session-unverified') ||
        document.hidden
    ) {
        return true;
    }

    return [
        ...document.querySelectorAll<HTMLElement>(
            '[role="dialog"], [role="alertdialog"], [role="menu"], [role="listbox"]',
        ),
    ].some(
        (el) =>
            !el.closest('[data-onboarding-overlay]') &&
            el.getClientRects().length > 0 &&
            el.getAttribute('data-state') !== 'closed' &&
            el.getAttribute('aria-hidden') !== 'true' &&
            window.getComputedStyle(el).visibility !== 'hidden',
    );
}

function position(): void {
    const isBlocked = hasOtherOverlay();

    if (isBlocked !== blocked.value) {
        blocked.value = isBlocked;

        if (isBlocked) {
            releaseBackground();
        } else if (active.value) {
            void focusStep();
        }
    }

    if (!active.value || isBlocked) {
        return;
    }

    const target = findOnboardingTarget(document, active.value.target);

    if (!target) {
        rect.value = null;
        releaseBackground();

        if (!locating) {
            void focusStep();
        }

        return;
    }

    rect.value = target.getBoundingClientRect();

    if (!locating) {
        protectBackground();
    }

    const viewport = window.visualViewport;
    const probe = viewportProbe.value;
    const safe = probe ? getComputedStyle(probe) : null;
    const topInset = parseFloat(safe?.paddingTop ?? '0') || 0;
    const bottomInset = parseFloat(safe?.paddingBottom ?? '0') || 0;
    const leftInset = parseFloat(safe?.paddingLeft ?? '0') || 0;
    const rightInset = parseFloat(safe?.paddingRight ?? '0') || 0;
    const width =
        (viewport?.width ?? window.innerWidth) - leftInset - rightInset;
    const height =
        (viewport?.height ?? window.innerHeight) - topInset - bottomInset;
    const point = onboardingPosition(
        rect.value,
        {
            width: dialog.value?.offsetWidth ?? Math.min(352, width - 24),
            height: dialog.value?.offsetHeight ?? 260,
        },
        {
            left: (viewport?.offsetLeft ?? 0) + leftInset,
            top: (viewport?.offsetTop ?? 0) + topInset,
            width,
            height,
        },
    );
    cardStyle.value = {
        top: `${point.top}px`,
        left: `${point.left}px`,
        maxHeight: `${Math.max(80, height - 24)}px`,
        maxWidth: `${Math.max(80, width - 24)}px`,
    };
}

function schedulePosition(): void {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(position);
}

async function focusStep(): Promise<void> {
    const version = ++generation;
    locating = true;
    releaseBackground();
    window.clearTimeout(targetTimer);
    rect.value = null;
    const deadline = Date.now() + 1800;

    const locate = async (): Promise<void> => {
        if (version !== generation || !active.value || blocked.value) {
            locating = false;

            return;
        }

        const el = findOnboardingTarget(document, active.value.target);

        if (!el) {
            if (Date.now() < deadline) {
                targetTimer = window.setTimeout(() => void locate(), 100);
            } else {
                locating = false;
                steps.value.splice(index.value, 1);

                if (steps.value.length) {
                    index.value = Math.min(index.value, steps.value.length - 1);
                    void focusStep();
                } else {
                    unavailable.value = true;
                    stop();
                }
            }

            return;
        }

        el.scrollIntoView({ block: 'center', behavior: 'instant' });
        rect.value = el.getBoundingClientRect();
        shown.add(active.value.id);
        await nextTick();

        if (version !== generation) {
            return;
        }

        position();

        if (!blocked.value) {
            protectBackground();
            resizeObserver?.disconnect();
            resizeObserver?.observe(el);

            if (dialog.value) {
                resizeObserver?.observe(dialog.value);
            }

            dialog.value?.focus({ preventScroll: true });
        }

        locating = false;
    };
    await locate();
}

async function start(manual = false): Promise<void> {
    if (
        !onboarding.hasTour.value ||
        navigating ||
        document.documentElement.hasAttribute('data-session-unverified')
    ) {
        return;
    }

    stop(false);
    autoAttempted = true;
    unavailable.value = false;
    previousFocus =
        document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
    const version = generation;
    const identity = page.props.auth?.user?.uuid;

    const section = onboarding.section.value;

    if (!section) {
        return;
    }

    if (
        section === 'dashboard' &&
        !onboarding.state.value.legacy_imported &&
        identity
    ) {
        let dismissed = false;

        let canReadLegacy = true;

        try {
            dismissed = readDashboardQuickStartDismissed(identity);
        } catch {
            // Leave migration pending when storage is inaccessible.
            canReadLegacy = false;

            if (!manual) {
                return;
            }
        }

        const migrated = canReadLegacy
            ? await onboarding.persist('migrate', dismissed)
            : false;

        if (
            version !== generation ||
            identity !== page.props.auth?.user?.uuid
        ) {
            return;
        }

        // Do not start automatically if the old preference could not be imported.
        if (!migrated && !manual) {
            return;
        }
    }

    if (
        !manual &&
        (!onboarding.state.value.enabled ||
            onboarding.state.value.tours[section])
    ) {
        return;
    }

    if (manual) {
        await onboarding.persist('reset');
    }

    if (version !== generation || !onboarding.hasTour.value) {
        return;
    }

    shown.clear();
    steps.value = tours[section].filter(
        (step) =>
            !step.setup ||
            ((page.props.quick_start as { show?: boolean } | undefined)
                ?.show === true &&
                !onboarding.state.value.quick_start_dismissed),
    );
    index.value = 0;
    blocked.value = hasOtherOverlay();

    if (!blocked.value) {
        await focusStep();
    }
}

function scheduleAutoStart(): void {
    if (autoAttempted || navigating || !onboarding.hasTour.value) {
        return;
    }

    window.clearTimeout(startTimer);
    startTimer = window.setTimeout(() => {
        if (!document.documentElement.hasAttribute('data-session-unverified')) {
            void start();
        }
    }, 650);
}

function finish(action: 'complete' | 'skip' | 'disable'): void {
    if (shown.size) {
        void onboarding.persist(action);
    }

    stop();
}

function next(): void {
    if (index.value === steps.value.length - 1) {
        finish('complete');

        return;
    }

    index.value += 1;
    void focusStep();
}

function back(): void {
    if (index.value > 0) {
        index.value -= 1;
        void focusStep();
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (!visible.value || !dialog.value) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        finish('skip');
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        next();
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        back();
    } else {
        trapOnboardingFocus(event, dialog.value);
    }
}

function openGuide(): void {
    stop();
    onboarding.openGuide();
}

watch(onboarding.replayRequest, () => {
    if (mounted.value) {
        void start(true);
    }
});
watch(
    () => [page.component, page.url, page.props.auth?.user?.uuid],
    () => {
        if (!mounted.value) {
            return;
        }

        autoAttempted = false;
        stop(false);
        scheduleAutoStart();
    },
);

onMounted(() => {
    mounted.value = true;
    resizeObserver = new ResizeObserver(schedulePosition);
    observer = new MutationObserver(() => {
        schedulePosition();

        if (!active.value) {
            scheduleAutoStart();
        }
    });
    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: [
            'class',
            'hidden',
            'aria-hidden',
            'data-state',
            'data-session-unverified',
        ],
    });
    document.addEventListener('visibilitychange', schedulePosition);
    window.addEventListener('resize', schedulePosition);
    window.addEventListener('scroll', schedulePosition, true);
    window.addEventListener('keydown', onKeydown, true);
    window.visualViewport?.addEventListener('resize', schedulePosition);
    window.visualViewport?.addEventListener('scroll', schedulePosition);
    removeRouterListeners.push(
        router.on('start', () => {
            navigating = true;
            stop(false);
        }),
        router.on('finish', () => {
            navigating = false;
            scheduleAutoStart();
        }),
    );
    scheduleAutoStart();
});

onBeforeUnmount(() => {
    stop(false);
    observer?.disconnect();
    cancelAnimationFrame(frame);
    removeRouterListeners.forEach((remove) => remove());
    document.removeEventListener('visibilitychange', schedulePosition);
    window.removeEventListener('resize', schedulePosition);
    window.removeEventListener('scroll', schedulePosition, true);
    window.removeEventListener('keydown', onKeydown, true);
    window.visualViewport?.removeEventListener('resize', schedulePosition);
    window.visualViewport?.removeEventListener('scroll', schedulePosition);
});
</script>

<template>
    <Teleport v-if="mounted" to="body">
        <div
            data-onboarding-layer
            ref="viewportProbe"
            class="pointer-events-none invisible fixed"
            style="
                padding: env(safe-area-inset-top) env(safe-area-inset-right)
                    env(safe-area-inset-bottom) env(safe-area-inset-left);
            "
            aria-hidden="true"
        />
        <div
            v-if="visible && active && rect"
            data-onboarding-layer
            data-onboarding-overlay
            class="fixed inset-0 z-[160]"
        >
            <div class="absolute inset-0" @click.stop />
            <div
                class="pointer-events-none absolute rounded-xl ring-2 ring-primary"
                :style="{
                    top: `${rect.top - 6}px`,
                    left: `${rect.left - 6}px`,
                    width: `${rect.width + 12}px`,
                    height: `${rect.height + 12}px`,
                    boxShadow: '0 0 0 9999px rgb(0 0 0 / 0.55)',
                }"
            />
            <div
                ref="dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="onboarding-title"
                aria-describedby="onboarding-body"
                tabindex="-1"
                class="absolute w-[22rem] overflow-y-auto overscroll-contain rounded-2xl border bg-card p-4 text-card-foreground shadow-xl outline-none"
                :style="cardStyle"
            >
                <p class="text-xs text-muted-foreground" aria-live="polite">
                    {{
                        t('onboarding.progress', {
                            current: index + 1,
                            total: steps.length,
                        })
                    }}
                </p>
                <h2 id="onboarding-title" class="mt-2 text-lg font-semibold">
                    {{ t(`onboarding.steps.${active.id}.title`) }}
                </h2>
                <p id="onboarding-body" class="mt-2 text-sm leading-6">
                    {{ t(`onboarding.steps.${active.id}.body`) }}
                </p>
                <Button
                    v-if="active.setup"
                    as-child
                    variant="outline"
                    class="mt-3"
                    ><Link :href="editBanks()" @click="finish('skip')">{{
                        t('dashboard.quickStart.cta')
                    }}</Link></Button
                >
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-2"
                >
                    <Button size="sm" variant="ghost" @click="finish('skip')">{{
                        t('onboarding.skip')
                    }}</Button>
                    <div class="flex gap-2">
                        <Button
                            v-if="index > 0"
                            size="sm"
                            variant="outline"
                            @click="back"
                            >{{ t('onboarding.back') }}</Button
                        >
                        <Button size="sm" @click="next">{{
                            index === steps.length - 1
                                ? t('onboarding.done')
                                : t('onboarding.next')
                        }}</Button>
                    </div>
                </div>
                <Button
                    v-if="page.props.contextualHelp"
                    variant="link"
                    size="sm"
                    class="mt-2"
                    @click="openGuide"
                    >{{ t('onboarding.guide') }}</Button
                >
                <button
                    type="button"
                    class="mt-3 block w-full text-center text-xs text-muted-foreground underline"
                    @click="finish('disable')"
                >
                    {{ t('onboarding.disable') }}
                </button>
            </div>
        </div>
        <div
            v-if="
                onboarding.hasTour.value &&
                (onboarding.saveFailed.value || unavailable)
            "
            data-onboarding-layer
            role="status"
            class="fixed inset-x-4 top-[calc(env(safe-area-inset-top)+1rem)] z-[160] mx-auto max-w-md rounded-xl border bg-card p-4 text-sm text-card-foreground shadow-lg"
        >
            <p>
                {{
                    t(
                        onboarding.saveFailed.value
                            ? 'onboarding.saveError'
                            : 'onboarding.unavailable',
                    )
                }}
            </p>
            <Button variant="link" size="sm" @click="onboarding.replay">{{
                t('onboarding.replay')
            }}</Button>
            <Button
                v-if="page.props.contextualHelp"
                variant="link"
                size="sm"
                @click="openGuide"
                >{{ t('onboarding.guide') }}</Button
            >
        </div>
    </Teleport>
</template>
