import { useMediaQuery } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export function useMobileSheetViewport(baseBottomSpacing = '1rem') {
    const isMobileViewport = useMediaQuery('(max-width: 767px)');
    const keyboardInset = ref(0);

    function updateKeyboardInset(): void {
        if (
            !isMobileViewport.value ||
            typeof window === 'undefined' ||
            window.visualViewport === undefined
        ) {
            keyboardInset.value = 0;

            return;
        }

        const viewport = window.visualViewport;

        if (viewport === null) {
            keyboardInset.value = 0;

            return;
        }

        const nextInset = Math.max(
            0,
            Math.round(
                window.innerHeight - viewport.height - viewport.offsetTop,
            ),
        );

        keyboardInset.value = nextInset > 24 ? nextInset : 0;
    }

    function handleFocusIn(event: FocusEvent): void {
        if (!isMobileViewport.value) {
            return;
        }

        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        requestAnimationFrame(() => {
            target.scrollIntoView({
                block: 'center',
                inline: 'nearest',
                behavior: 'auto',
            });
        });
    }

    onMounted(() => {
        updateKeyboardInset();

        if (
            typeof window === 'undefined' ||
            window.visualViewport === undefined
        ) {
            return;
        }

        const viewport = window.visualViewport;

        if (viewport === null) {
            return;
        }

        viewport.addEventListener('resize', updateKeyboardInset);
        viewport.addEventListener('scroll', updateKeyboardInset);
        window.addEventListener('orientationchange', updateKeyboardInset);
    });

    onBeforeUnmount(() => {
        if (
            typeof window === 'undefined' ||
            window.visualViewport === undefined
        ) {
            return;
        }

        const viewport = window.visualViewport;

        if (viewport === null) {
            return;
        }

        viewport.removeEventListener('resize', updateKeyboardInset);
        viewport.removeEventListener('scroll', updateKeyboardInset);
        window.removeEventListener('orientationchange', updateKeyboardInset);
    });

    const mobileFooterStyle = computed(() =>
        isMobileViewport.value
            ? {
                  paddingBottom: `calc(env(safe-area-inset-bottom) + ${baseBottomSpacing} + ${keyboardInset.value}px)`,
              }
            : undefined,
    );

    const mobileScrollStyle = computed(() =>
        isMobileViewport.value
            ? {
                  paddingBottom: `${keyboardInset.value + 24}px`,
              }
            : undefined,
    );

    return {
        isMobileViewport,
        keyboardInset,
        mobileFooterStyle,
        mobileScrollStyle,
        handleFocusIn,
    };
}
