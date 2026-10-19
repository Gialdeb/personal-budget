import { usePage } from '@inertiajs/vue3';
import { computed, ref, shallowRef, watch } from 'vue';
import { createOnboardingStore } from '@/lib/onboarding-state.js';
import type { OnboardingState } from '@/onboarding/tours';
import { screenForComponent } from '@/onboarding/tours';
import { update } from '@/routes/onboarding';

const snapshot = shallowRef<OnboardingState>();
const replayRequest = ref(0);
const guideRequest = ref(0);
const saveFailed = ref(false);
const defaults: OnboardingState = {
    enabled: true,
    tours: {},
    quick_start_dismissed: false,
    legacy_imported: false,
    revision: 0,
};
const store = createOnboardingStore(
    async (body: object): Promise<OnboardingState> => {
        const response = await fetch(update.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            throw new Error('Onboarding save failed');
        }

        return response.json();
    },
    (state: OnboardingState | undefined) => {
        snapshot.value = state;
    },
);

export function useOnboarding() {
    const page = usePage();
    watch(
        () => [page.props.auth?.user?.uuid, page.props.onboarding] as const,
        ([identity, shared]) => {
            store.sync(identity ?? null, shared);
            saveFailed.value = false;
        },
        { immediate: true, flush: 'sync' },
    );
    const state = computed(() => snapshot.value ?? defaults);
    const section = computed(() => {
        const url = new URL(page.url, 'https://onboarding.local');

        if (
            page.component.startsWith('settings/') &&
            (url.pathname === '/settings' ||
                url.searchParams.get('mobile') === 'launcher')
        ) {
            return 'settings-home' as const;
        }

        if (
            page.component.startsWith('reports/') &&
            url.searchParams.get('mobile') === 'launcher'
        ) {
            return 'report-hub' as const;
        }

        return screenForComponent[page.component] ?? null;
    });
    const hasTour = computed(
        () => page.props.onboarding != null && section.value != null,
    );

    async function persist(
        action: 'complete' | 'skip' | 'disable' | 'reset' | 'migrate',
        legacyDismissed = false,
    ): Promise<boolean> {
        const identity = page.props.auth?.user?.uuid;
        const saved = await store.update({
            action,
            section: section.value ?? 'dashboard',
            quick_start_dismissed: legacyDismissed,
        });

        if (identity === page.props.auth?.user?.uuid) {
            saveFailed.value = !saved;
        }

        return saved;
    }

    return {
        state,
        section,
        hasTour,
        replayRequest,
        guideRequest,
        saveFailed,
        persist,
        replay: () => {
            replayRequest.value += 1;
        },
        openGuide: () => {
            guideRequest.value += 1;
        },
    };
}
